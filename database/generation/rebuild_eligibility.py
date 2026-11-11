"""Independent, evidence-based prerequisite checks for fictional rebuild review.

Consumes a reviewed source rule and an actual proposed transcript. Never emits
permissions, placement results, equivalencies, or earned credit for pending work.
"""
import re

POINTS = {'A': 4, 'A-': 3.7, 'B+': 3.3, 'B': 3, 'B-': 2.7, 'C+': 2.3,
          'C': 2, 'C-': 1.7, 'D+': 1.3, 'D': 1, 'D-': .7, 'F': 0}


def result(status, reason, children=None):
    value = {'status': status, 'reason': reason}
    if children is not None:
        value['children'] = children
    return value


def combine(children, any_of=False):
    if not children:
        return result('review-required', 'Empty prerequisite expression.')
    order = ['satisfied', 'conditional', 'review-required', 'unsatisfied'] if any_of else ['unsatisfied', 'review-required', 'conditional', 'satisfied']
    for state in order:
        if any(c['status'] == state for c in children):
            return result(state, 'At least one route.' if any_of else 'All conditions.', children)


class Eligibility:
    def __init__(self, courses, attempts, term_positions, target_term, person, course_key, source_lab_codes=None):
        self.courses, self.attempts = courses, attempts
        self.positions, self.target = term_positions, term_positions[target_term]
        self.person, self.course_key = person, course_key
        self.source_lab_codes = set(source_lab_codes or [])

    def evidence(self, scope, authority=None):
        record = self.person.get('registrarEvidence', {}).get(scope, {})
        approved = record.get('status') == 'approved' and record.get('scope') == scope
        approved = approved and all(record.get(k) for k in ['approvalId', 'approvedBy', 'approvedAt'])
        approved = approved and type(record.get('studentId')) is int and record['studentId'] == self.person.get('studentId')
        approved = approved and type(record.get('effectiveTermPosition')) is int and record['effectiveTermPosition'] <= self.target
        approved = approved and (authority is None or record.get('authority') == authority)
        # Explicit simulated forecasts may carry a conditional decision. They
        # are never approved earned work or unconditional registration rights.
        conditional = record.get('status') == 'conditional' and record.get('fictionalSimulation') is True
        conditional = conditional and record.get('scope') == scope and type(record.get('studentId')) is int and record.get('studentId') == self.person.get('studentId')
        conditional = conditional and all(record.get(k) for k in ['approvalId', 'approvedBy', 'approvedAt'])
        conditional = conditional and type(record.get('effectiveTermPosition')) is int and record['effectiveTermPosition'] <= self.target
        conditional = conditional and (authority is None or record.get('authority') == authority)
        return result('satisfied' if approved else 'conditional' if conditional else 'review-required', 'Scoped reviewed evidence: ' + scope)

    def code(self, attempt):
        return self.courses[attempt['courseKey']]['courseCode']

    def rows_for(self, code, concurrent=False, version=None):
        rows = [a for a in self.attempts if self.code(a) == code and
                (self.positions[a['termId']] <= self.target if concurrent else self.positions[a['termId']] < self.target)]
        if version is not None:
            rows = [a for a in rows if self.courses[a['courseKey']]['catalogVersion'] == version]
        return sorted(rows, key=lambda a: self.positions[a['termId']])

    def grade(self, attempt, minimum=None, transfer_only=False):
        grade = attempt.get('grade')
        origin = attempt.get('origin', 'local')
        if transfer_only and origin != 'transfer':
            return result('unsatisfied', 'This route specifically requires approved transfer credit.')
        if origin == 'transfer':
            scope = 'equivalence:' + self.code(attempt)
            proof = attempt.get('equivalenceApproval', {})
            valid = proof.get('status') == 'approved' and proof.get('scope') == scope
            valid = valid and all(proof.get(k) for k in ['approvalId', 'approvedBy', 'approvedAt'])
            if not valid:
                return result('review-required', 'Transfer equivalence is not approved.')
            if grade == 'TC':
                grade = proof.get('minimumBaseGrade')
        if attempt.get('status') != 'completed':
            return result('conditional', 'Pending course; recheck final grade before registration/completion.')
        if grade == 'CR':
            return result('satisfied' if minimum is None else 'review-required', 'Credit grade does not prove a letter-grade minimum.')
        if grade not in POINTS:
            return result('review-required', 'Missing or unrecognized completed grade.')
        threshold = POINTS.get(minimum, POINTS['D-']) if minimum is not None else POINTS['D-']
        if minimum is not None and minimum not in POINTS:
            return result('review-required', 'Unknown minimum grade in source rule.')
        return result('satisfied' if POINTS[grade] >= threshold else 'unsatisfied', 'Completed grade compared with ' + (minimum or 'the passing floor') + '.')

    def course(self, code, minimum=None, concurrent=False, version=None, transfer_only=False):
        rows = self.rows_for(code, concurrent, version)
        if len({self.courses[a['courseKey']]['catalogVersion'] for a in rows}) > 1:
            return result('review-required', code + ' has multiple historical meanings.')
        finals = [a for a in rows if a['status'] == 'completed' and a.get('grade') not in ['I', 'W', 'NR']]
        final = finals[-1] if finals else None
        check = self.grade(final, minimum, transfer_only) if final else result('unsatisfied', code + ' has not been completed in time.')
        if check['status'] == 'satisfied':
            return check
        pending = [a for a in rows if a['status'] in ['in-progress', 'planned'] and (final is None or self.positions[a['termId']] > self.positions[final['termId']])]
        if pending:
            projected = self.grade(pending[-1], minimum, transfer_only)
            if projected['status'] in ['conditional', 'review-required']:
                return projected
        return check

    def completed(self):
        latest = {}
        for a in sorted(self.attempts, key=lambda a: self.positions[a['termId']]):
            if self.positions[a['termId']] >= self.target or a['status'] != 'completed' or a.get('grade') in ['I', 'W', 'NR']:
                continue
            latest[self.code(a)] = a
        return list(latest.values())

    def earned(self, subject=None):
        return sum(self.courses[a['courseKey']]['credits'] for a in self.completed()
                   if (subject is None or re.match(r'[A-Z]+', self.code(a))[0] == subject)
                   and self.grade(a)['status'] == 'satisfied')

    def credit_condition(self, minimum, subject=None):
        earned = self.earned(subject)
        if earned >= minimum:
            return result('satisfied', 'Completed credits: ' + str(earned) + ' / ' + str(minimum))
        completed = {self.code(a) for a in self.completed() if self.grade(a)['status'] == 'satisfied'}
        pending = {}
        for a in self.attempts:
            if self.positions[a['termId']] >= self.target or a['status'] not in ['in-progress', 'planned'] or self.code(a) in completed:
                continue
            if subject is None or re.match(r'[A-Z]+', self.code(a))[0] == subject:
                pending[self.code(a)] = self.courses[a['courseKey']]['credits']
        if earned + sum(pending.values()) >= minimum:
            return result('conditional', 'Pending prior-term courses could meet the credit threshold; no pending credits are earned.')
        return result('unsatisfied', 'Completed credits: ' + str(earned) + ' / ' + str(minimum))

    def proficiency(self, key, concurrent=False, minimum='C'):
        required = {'ECI': ['EL1000'], 'CompositionI': ['EL1000'],
                    'ECII': ['EL2216'], 'CompositionII': ['EL2216'],
                    'EMS': ['EL1000', 'EL2216'], 'writing': ['EL1000', 'EL2216']}
        if key == 'MATH_PROFICIENCY':
            return combine([self.course(c, minimum, concurrent) for c in
                            ['MA1020', 'MA2000', 'MA2050', 'MA2080', 'MA2090', 'MA2300', 'MA2310', 'MA2320', 'MA3030']], True)
        if key not in required:
            return result('review-required', 'Unrecognized source proficiency token: ' + key)
        return combine([self.course(c, minimum, concurrent) for c in required[key]])

    def evaluate(self, rule, concurrent=False):
        t = rule.get('type')
        if t in ['and', 'or']:
            return combine([self.evaluate(c, concurrent) for c in rule.get('rules', [])], t == 'or')
        if t in ['corequisite-condition', 'completed-or-concurrent-condition']:
            return self.evaluate(rule['rule'], True)
        if t in ['course', 'approved-transfer-course']:
            minimum = rule.get('minimumBaseGrade', rule.get('minimumGrade'))
            return self.course(rule['courseCode'], minimum, concurrent, rule.get('catalogVersion'), t == 'approved-transfer-course')
        if t == 'no-course-prerequisites':
            verified = rule.get('sourceExplicitlyStatesNone') or rule.get('basis') == 'No prerequisite or corequisite is listed in the supplied complete catalog entry.'
            return result('satisfied' if verified else 'review-required', 'Reviewed complete course entry prerequisite statement.')
        if t in ['proficiency', 'completed-or-concurrent-proficiency']:
            return self.proficiency(rule['key'], concurrent or t == 'completed-or-concurrent-proficiency')
        if t == 'completed-writing-proficiency':
            components = rule.get('components') or ['CompositionI', 'CompositionII']
            return combine([self.proficiency(k, concurrent, rule.get('minimumCourseGrade', 'C')) for k in components])
        if t == 'approval':
            return self.evidence('course-permission:' + self.course_key, rule.get('authority'))
        if t == 'scoped-evidence':
            return self.evidence(rule['scope'], rule.get('authority'))
        if t == 'standing-in-program':
            tag = {'Computer and Information Science, B.S.': 'CIS', 'Management Information Systems, B.S.': 'MIS'}.get(self.person['major'], self.person['major'])
            threshold = {'Freshman': 0, 'Sophomore': 32, 'Junior': 57, 'Senior': 88}.get(rule['standing'])
            if threshold is None:
                return result('review-required', 'Unknown standing threshold.')
            if tag != rule['program']:
                return result('unsatisfied', 'Declared program does not match the required standing route.')
            return self.credit_condition(threshold)
        if t in ['minimum-earned-credits', 'earned-subject-credits']:
            return self.credit_condition(rule['minimum'], rule.get('subject'))
        if t == 'minimum-institutional-gpa':
            rows = [a for a in self.completed() if a.get('origin', 'local') == 'local' and a.get('grade') in POINTS]
            credits = sum(self.courses[a['courseKey']]['credits'] for a in rows)
            if not credits:
                return result('review-required', 'No prior local graded GPA evidence.')
            gpa = sum(POINTS[a['grade']] * self.courses[a['courseKey']]['credits'] for a in rows) / credits
            return result('satisfied' if gpa >= rule['minimum'] else 'unsatisfied', 'Prior institutional GPA: ' + str(round(gpa, 4)))
        if t == 'subject-course-count':
            codes = {self.code(a) for a in self.attempts if re.match(r'[A-Z]+', self.code(a))[0] == rule['subject']
                     and rule.get('minimumLevel', 0) <= int(re.search(r'\d+', self.code(a))[0]) <= rule.get('maximumLevel', 99999)}
            checks = [self.course(c, rule.get('minimumGrade'), concurrent) for c in sorted(codes)]
            satisfied = sum(c['status'] == 'satisfied' for c in checks)
            pending = sum(c['status'] == 'conditional' for c in checks)
            state = 'satisfied' if satisfied >= rule['minimumCourses'] else 'conditional' if satisfied + pending >= rule['minimumCourses'] else 'unsatisfied'
            return result(state, 'Distinct qualifying subject courses: ' + str(satisfied), checks)
        if t == 'program-field':
            matches = any(field in name for field in rule['eligibleFields'] for name in [self.person['major'], self.person.get('minor') or ''])
            return result('satisfied' if matches else 'unsatisfied', 'Allowed declared major/minor field.')
        if t == 'completed-laboratory-science':
            codes = {self.code(a) for a in self.attempts if self.code(a) in self.source_lab_codes}
            checks = [self.course(c) for c in sorted(codes)]
            combined = combine(checks, True) if checks else result('unsatisfied', 'No explicit laboratory course in the proposed history.')
            if combined['status'] in ['satisfied', 'conditional']:
                return combined
            if any(re.match(r'^(BS|CP|PX)\d', self.code(a)) for a in self.completed()):
                return result('review-required', 'Possible combined lecture/lab science needs source classification review.')
            if any(self.positions[a['termId']] < self.target and a['status'] in ['in-progress', 'planned'] and re.match(r'^(BS|CP|PX)\d', self.code(a)) for a in self.attempts):
                return result('review-required', 'Pending science coursework may include a lab; its classification and final grade need review.')
            return combined
        return result('review-required', rule.get('reason', 'Source condition requires review: ' + str(t)))
