#!/usr/bin/env python3
"""Regression checks for consequential replacement-data scheduling rules."""
import datetime as dt
import json
import sys
import unittest
import importlib.util
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'database/generation'))
from academic_meetings import meetings, overlap
from rebuild_rule_interpretations import interpretations, recover_reviewed_entries
from rebuild_historical_offerings import former_name_templates, credit_bounds


def interval(day=0, start=600, end=660, lo='2026-11-02', hi='2026-11-30'):
    return [(day, start, end, dt.date.fromisoformat(lo).toordinal(), dt.date.fromisoformat(hi).toordinal())]


class MeetingTests(unittest.TestCase):
    def test_partial_time_overlap_is_a_conflict(self):
        self.assertTrue(overlap(interval(), interval(start=659, end=700)))

    def test_back_to_back_classes_are_allowed(self):
        self.assertFalse(overlap(interval(), interval(start=660, end=720)))

    def test_separate_parts_of_term_can_reuse_a_resource(self):
        self.assertFalse(overlap(interval(hi='2026-11-10'), interval(lo='2026-11-11')))

    def test_overlapping_date_ranges_need_a_real_shared_weekday(self):
        # Their date intersection is Wednesday only, with no shared Monday.
        self.assertFalse(overlap(interval(lo='2026-11-02', hi='2026-11-04'), interval(lo='2026-11-04', hi='2026-11-09')))

    def test_separate_weekdays_are_allowed(self):
        self.assertFalse(overlap(interval(), interval(day=1)))

    def test_official_meeting_rows_do_not_form_a_cross_product(self):
        source = {'meetings': [
            {'monday': True, 'beginTime': '1000', 'endTime': '1100', 'startDate': '11/02/2026', 'endDate': '11/30/2026'},
            {'wednesday': True, 'beginTime': '1200', 'endTime': '1300', 'startDate': '11/02/2026', 'endDate': '11/30/2026'},
        ]}
        rows = meetings(source)
        self.assertEqual([(r[0], r[1], r[2]) for r in rows], [(0, 600, 660), (2, 720, 780)])
        self.assertFalse(overlap(rows, interval(day=0, start=720, end=780)))

    def test_unknown_time_is_not_an_invented_meeting(self):
        self.assertEqual(meetings({'meetings': [{'monday': True, 'beginTime': None, 'endTime': None}]}), [])


class InterpretationTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        document = json.loads((ROOT / 'database/staging/academic-alignment/rules/all-course-prerequisites.json').read_text())
        cls.original = {r['courseVersionOrCode']: r['rule'] for r in document['courses']}
        cls.result = interpretations(cls.original)

    def test_chemistry_chain_and_grade_are_retained(self):
        self.assertEqual(self.result['CP2130']['rule']['rules'], [{'type': 'course', 'courseCode': 'CP2120', 'minimumGrade': 'C'}])

    def test_physics_alternative_does_not_remove_math_requirement(self):
        rule = self.result['PX3000']['rule']
        self.assertEqual(rule['type'], 'and')
        self.assertEqual(rule['rules'][0]['type'], 'or')
        self.assertEqual(rule['rules'][1]['courseCode'], 'MA2310')

    def test_biology_prerequisites_are_not_silently_waived(self):
        rules = self.result['BS3400']['rule']['rules']
        self.assertEqual({r['courseCode'] for r in rules if r['type'] == 'course'},
                         {'BS2400', 'BS2401', 'BS2410', 'BS2411', 'CP2120', 'CP2121'})
        self.assertIn({'type': 'proficiency', 'key': 'ECII'}, rules)

    def test_permission_placement_and_ambiguous_rules_stay_gated(self):
        for code in ['BS5400', 'EL1000', 'EL2216', 'BU5171', 'ED4220', 'ED5925@2020-2022']:
            self.assertNotIn(code, self.result)

    def test_source_changes_require_an_interpretation_review(self):
        changed = dict(self.original)
        changed['CP2130'] = {**changed['CP2130'], 'sourceText': 'CP2120 and MA2000'}
        with self.assertRaises(ValueError):
            interpretations(changed)

    def test_lab_corequisite_omitted_by_old_parser_is_recovered(self):
        recovered = recover_reviewed_entries(self.original)
        self.assertEqual(recovered['PX2201']['rule'], {'type': 'completed-or-concurrent-condition',
                                                     'rule': {'type': 'course', 'courseCode': 'PX2200', 'minimumGrade': None}})

    def test_complete_entry_review_is_required_before_removing_missing_flag(self):
        recovered = recover_reviewed_entries(self.original)
        self.assertEqual(recovered['AS1512']['rule']['type'], 'no-course-prerequisites')
        self.assertEqual(len(recovered['AS1512']['sourceEntrySHA256']), 64)
        self.assertNotIn('EL1000', recovered)


class FormerNameTests(unittest.TestCase):
    def source(self, code, note):
        return {'courseCode': code, 'sourceSHA256': 'a' * 64, 'sourceFields': {'note': [note]}}

    def test_explicit_former_name_and_matching_credits_enable_a_template(self):
        meta = {'PX2000': {'creditMinimum': 3.0, 'creditMaximum': 3.0}}
        raw = [self.source('PX2000', 'Previously named “CP 2220 Structure of Physics I”')]
        offers = {('202209', 'CP2220'): [{'creditHours': None, 'creditHourLow': 3, 'creditHourHigh': None}]}
        accepted, rejected = former_name_templates(meta, raw, offers)
        self.assertEqual(accepted['PX2000']['formerCode'], 'CP2220')
        self.assertFalse(accepted['PX2000']['verifiedTranscriptEquivalence'])
        self.assertFalse(rejected)

    def test_conflicting_lab_note_cannot_borrow_a_lecture_crn(self):
        meta = {'PX2201': {'creditMinimum': 1.0, 'creditMaximum': 1.0}}
        raw = [self.source('PX2201', 'Previously named “CP 2240 General Physics I”')]
        offers = {('202209', 'CP2240'): [{'creditHours': 3, 'creditHourLow': 3, 'creditHourHigh': None}]}
        accepted, rejected = former_name_templates(meta, raw, offers)
        self.assertNotIn('PX2201', accepted)
        self.assertEqual(rejected[0]['formerCodeInNote'], 'CP2240')

    def test_similar_codes_without_a_former_name_declaration_are_not_mapped(self):
        meta = {'PX2700': {'creditMinimum': 4.0, 'creditMaximum': 4.0}}
        raw = [self.source('PX2700', 'Offered in Fall.')]
        offers = {('202209', 'CP2700'): [{'creditHours': 4}]}
        accepted, _ = former_name_templates(meta, raw, offers)
        self.assertFalse(accepted)

    def test_unknown_or_variable_credits_do_not_prove_a_fixed_credit_match(self):
        meta = {'PX2000': {'creditMinimum': 3.0, 'creditMaximum': 3.0}}
        raw = [self.source('PX2000', 'Previously named “CP 2220 Structure of Physics I”')]
        for row in [{}, {'creditHours': None, 'creditHourLow': 3, 'creditHourHigh': 4}]:
            accepted, _ = former_name_templates(meta, raw, {('202209', 'CP2220'): [row]})
            self.assertFalse(accepted)


class FictionalRepairTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        spec = importlib.util.spec_from_file_location('elective_repair', ROOT / 'tools/repair-academic-elective.py')
        cls.module = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(cls.module)

    def test_a_required_course_in_unparsed_multiline_prose_is_detected(self):
        pattern = re.compile(r'\bPH\s*2000\b')
        self.assertTrue(self.module.mentions({'type': 'review-required', 'sourceText': 'C or better in PH\n2000'}, pattern))
        self.assertFalse(self.module.mentions({'sourceText': 'PH20000'}, pattern))

    def test_a_new_elective_with_permission_conditions_stays_excluded(self):
        self.assertTrue(self.module.gated({'type': 'and', 'rules': [
            {'type': 'course', 'courseCode': 'PY2010'}, {'type': 'approval', 'authority': 'course-instructor'}]}))

    def test_active_records_cannot_be_used_as_a_fictional_repair_candidate(self):
        with self.assertRaisesRegex(ValueError, 'inactive fictional rebuild'):
            self.module.repair({}, {'status': 'active', 'workingDatabaseChanged': False}, '202209', 'PH2000')


if __name__ == '__main__':
    unittest.main()
