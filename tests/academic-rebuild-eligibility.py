#!/usr/bin/env python3
"""Independent checks for actual history eligibility, not planner agreement."""
import copy
import json
import sys
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'database/generation'))
from rebuild_eligibility import Eligibility, combine, result
from rebuild_remaining_proofs import proof_rules


class EligibilityTests(unittest.TestCase):
    def context(self, rows, target=3, key='CS4501', major='Computer and Information Science, B.S.'):
        codes = {a['courseKey'] for a in rows} | {key}
        meta = {c: {'courseCode': c, 'catalogVersion': '2026-2027', 'credits': 4,
                    'title': 'Science Laboratory' if c.endswith('2401') else 'Lecture'} for c in codes}
        return Eligibility(meta, rows, {1: 0, 2: 1, 3: 2, 4: 3}, target,
                           {'studentId': 1, 'major': major, 'minor': None}, key)

    def row(self, code='CS2511', term=1, grade='B', status='completed', **extra):
        return {'courseKey': code, 'termId': term, 'grade': grade, 'status': status, **extra}

    def course(self, code='CS2511', minimum='C'):
        return {'type': 'course', 'courseCode': code, 'minimumGrade': minimum}

    def permission(self):
        return {'type': 'approval', 'authority': 'course-instructor'}

    def test_completed_course_route_does_not_need_alternative_permission(self):
        ctx = self.context([self.row()])
        self.assertEqual(ctx.evaluate({'type': 'or', 'rules': [self.course(), self.permission()]})['status'], 'satisfied')

    def test_permission_is_still_required_when_joined_with_and(self):
        ctx = self.context([self.row()])
        self.assertEqual(ctx.evaluate({'type': 'and', 'rules': [self.course(), self.permission()]})['status'], 'review-required')

    def test_missing_course_is_not_hidden_by_missing_permission(self):
        ctx = self.context([])
        self.assertEqual(ctx.evaluate({'type': 'and', 'rules': [self.course(), self.permission()]})['status'], 'unsatisfied')

    def test_minimum_grade_is_not_just_a_course_presence_check(self):
        self.assertEqual(self.context([self.row(grade='C-')]).evaluate(self.course())['status'], 'unsatisfied')

    def test_latest_final_failure_replaces_earlier_passing_attempt(self):
        ctx = self.context([self.row(grade='A'), self.row(term=2, grade='F')])
        self.assertEqual(ctx.evaluate(self.course())['status'], 'unsatisfied')
        self.assertEqual(ctx.earned(), 0)

    def test_withdrawal_does_not_erase_prior_final_grade(self):
        ctx = self.context([self.row(grade='B'), self.row(term=2, grade='W')])
        self.assertEqual(ctx.evaluate(self.course())['status'], 'satisfied')

    def test_a_future_completed_course_cannot_satisfy_an_earlier_prerequisite(self):
        self.assertEqual(self.context([self.row(term=4)]).evaluate(self.course())['status'], 'unsatisfied')

    def test_same_term_course_needs_an_explicit_concurrent_condition(self):
        ctx = self.context([self.row(term=3)])
        self.assertEqual(ctx.evaluate(self.course())['status'], 'unsatisfied')
        self.assertEqual(ctx.evaluate({'type': 'corequisite-condition', 'rule': self.course()})['status'], 'satisfied')

    def test_pending_course_is_conditional_and_never_earned(self):
        ctx = self.context([self.row(status='in-progress', grade=None)])
        self.assertEqual(ctx.evaluate(self.course())['status'], 'conditional')
        self.assertEqual(ctx.earned(), 0)
        self.assertEqual(ctx.evaluate({'type': 'minimum-earned-credits', 'minimum': 4})['status'], 'conditional')
        self.assertEqual(ctx.evaluate({'type': 'minimum-earned-credits', 'minimum': 88})['status'], 'unsatisfied')

    def test_planned_work_is_also_only_a_conditional_scenario(self):
        ctx = self.context([self.row(status='planned', grade=None)])
        self.assertEqual(ctx.evaluate(self.course())['status'], 'conditional')
        self.assertEqual(ctx.earned(), 0)

    def test_completed_writing_requires_both_courses_at_the_required_grade(self):
        ctx = self.context([self.row('EL1000'), self.row('EL2216', grade='C-')])
        self.assertEqual(ctx.evaluate({'type': 'completed-writing-proficiency', 'key': 'EMS'})['status'], 'unsatisfied')

    def test_local_course_does_not_satisfy_a_transfer_specific_route(self):
        ctx = self.context([self.row()])
        self.assertEqual(ctx.evaluate({**self.course(), 'type': 'approved-transfer-course'})['status'], 'unsatisfied')

    def test_transfer_tc_requires_reviewed_equivalence_and_base_grade(self):
        row = self.row(grade='TC', origin='transfer')
        self.assertEqual(self.context([row]).evaluate(self.course())['status'], 'review-required')
        row['equivalenceApproval'] = {'status': 'approved', 'scope': 'equivalence:CS2511', 'approvalId': 'demo-1',
                                      'approvedBy': 'fictional-test-registrar', 'approvedAt': '2026-11-07', 'minimumBaseGrade': 'C'}
        self.assertEqual(self.context([row]).evaluate(self.course())['status'], 'satisfied')
        self.assertEqual(self.context([row]).evaluate(self.course(minimum='B'))['status'], 'unsatisfied')

    def test_credit_grade_does_not_prove_a_letter_grade_minimum(self):
        ctx = self.context([self.row(grade='CR')])
        self.assertEqual(ctx.evaluate(self.course())['status'], 'review-required')
        self.assertEqual(ctx.evaluate(self.course(minimum=None))['status'], 'satisfied')

    def test_lab_science_is_not_inferred_from_a_lecture_course(self):
        ctx = self.context([self.row('BS2400')])
        self.assertEqual(ctx.evaluate({'type': 'completed-laboratory-science'})['status'], 'review-required')
        ctx = self.context([self.row('BS2401')])
        self.assertEqual(ctx.evaluate({'type': 'completed-laboratory-science'})['status'], 'review-required')
        ctx.source_lab_codes = {'BS2401'}
        self.assertEqual(ctx.evaluate({'type': 'completed-laboratory-science'})['status'], 'satisfied')

    def test_boolean_approval_does_not_satisfy_placement(self):
        ctx = self.context([])
        ctx.person['registrarEvidence'] = {'placement:ECI': {'approved': True}}
        self.assertEqual(ctx.evaluate({'type': 'scoped-evidence', 'scope': 'placement:ECI'})['status'], 'review-required')

    def test_permission_is_bound_to_person_authority_course_and_effective_term(self):
        ctx = self.context([])
        scope = 'course-permission:CS4501'
        record = {'status': 'approved', 'scope': scope, 'studentId': 1, 'approvalId': 'fictional-test',
                  'approvedBy': 'fictional-test-instructor', 'approvedAt': '2026-11-07',
                  'authority': 'course-instructor', 'effectiveTermPosition': 1}
        ctx.person['registrarEvidence'] = {scope: record}
        self.assertEqual(ctx.evaluate(self.permission())['status'], 'satisfied')
        for field, bad in [('studentId', 2), ('authority', 'other-authority'), ('effectiveTermPosition', 3), ('effectiveTermPosition', True)]:
            ctx.person['registrarEvidence'][scope] = {**record, field: bad}
            self.assertEqual(ctx.evaluate(self.permission())['status'], 'review-required')

    def test_the_checker_does_not_create_or_modify_approval_evidence(self):
        ctx = self.context([])
        before = copy.deepcopy(ctx.person)
        ctx.evaluate(self.permission())
        self.assertEqual(ctx.person, before)


class SourceProofTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        source = json.loads((ROOT / 'database/staging/academic-alignment/rules/all-course-prerequisites.json').read_text())
        cls.raw = {r['courseVersionOrCode']: r['rule'] for r in source['courses']}
        cls.proofs = proof_rules(cls.raw)

    def test_changing_the_source_requires_a_new_review(self):
        changed = copy.deepcopy(self.raw)
        changed['BU5171']['sourceText'] += ' changed'
        with self.assertRaises(ValueError):
            proof_rules(changed)

    def test_a_senior_business_course_cannot_be_cleared_for_a_freshman(self):
        ctx = EligibilityTests().context([EligibilityTests().row('BU4110')], key='BU5171', major='Accounting, B.S.')
        self.assertEqual(ctx.evaluate(self.proofs['BU5171']['rule'])['status'], 'unsatisfied')

    def test_broken_course_references_are_retained_as_review_conditions(self):
        text = json.dumps(self.proofs['ED5925@2020-2022']['rule'])
        self.assertIn('ED400 reference is unresolved', text)
        self.assertNotIn('"courseCode": "ED400"', text)
        self.assertIn('CE4200', json.dumps(self.proofs['ED4220']['rule']))

    def test_math_ambiguity_is_not_unconditionally_approved(self):
        self.assertIn('review-required', json.dumps(self.proofs['ED3900']['rule']))


if __name__ == '__main__':
    unittest.main()
