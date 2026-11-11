"""Recover clear portions and sufficient routes of remaining source conditions.

Ambiguous or broken text remains a review leaf. A sufficient proof is not a
claim that alternative legitimate routes are prohibited by Old Westbury.
"""
import hashlib
import json
from pathlib import Path


def course(code, minimum=None, version=None):
    node = {'type': 'course', 'courseCode': code, 'minimumGrade': minimum}
    if version:
        node['catalogVersion'] = version
    return node


def all_of(*nodes):
    return {'type': 'and', 'rules': list(nodes)}


def any_of(*nodes):
    return {'type': 'or', 'rules': list(nodes)}


def review(reason):
    return {'type': 'review-required', 'reason': reason}


def proof_rules(original):
    pinned = json.loads(Path(__file__).with_name('rebuild_remaining_source_hashes.json').read_text())
    for code, expected in pinned.items():
        if hashlib.sha256(json.dumps(original[code], sort_keys=True).encode()).hexdigest() != expected:
            raise ValueError('Re-review changed prerequisite source: ' + code)
    writing = {'type': 'completed-writing-proficiency', 'components': ['CompositionI', 'CompositionII'], 'minimumCourseGrade': 'C'}
    admissions_path = Path(__file__).resolve().parents[1] / 'staging/academic-alignment/supplemental-sources/education-admissions.html'
    admissions_sha = 'c16b28ec59c03286eb006dd1a31a8bbe952191ab41da4e1e991be76739ab5d05'
    if hashlib.sha256(admissions_path.read_bytes()).hexdigest() != admissions_sha:
        raise ValueError('Re-review changed school admission source')
    admission = all_of(
        {'type': 'minimum-earned-credits', 'minimum': 32},
        {'type': 'minimum-institutional-gpa', 'minimum': 3.0},
        course('MA1020', 'C'),
        {'type': 'subject-course-count', 'subject': 'MA', 'minimumLevel': 2000, 'maximumLevel': 5999, 'minimumCourses': 1, 'minimumGrade': 'C'},
        course('EL1000', 'C'), course('EL2216', 'C'), course('ED3700'), course('ED3820'), course('ED3900', 'C'),
        {'type': 'scoped-evidence', 'scope': 'school-of-education-admission'})
    permission = {'type': 'approval', 'authority': 'course-instructor'}
    education = [course('ED3700'), course('ED3900'), admission]
    second_math = any_of(*(course(c, 'C') for c in ['MA2000', 'MA2050', 'MA2010', 'MA2080', 'MA2090']))
    math_sufficient = all_of(course('MA1020', 'C'), second_math)
    values = {
        'BS5400': any_of(permission, {'type': 'approval', 'authority': 'department-chair'}),
        'BU5171': all_of({'type': 'minimum-earned-credits', 'minimum': 88}, any_of(
            all_of(course('BU4110', 'C-'), any_of(course('BU3250', 'C-'), course('MA2300', 'C-')), course('MA2000', 'C-')),
            review('Mixed business/math grouping: retain other possible source routes for review.'))),
        'ED3900': all_of({'type': 'minimum-earned-credits', 'minimum': 32}, {'type': 'minimum-institutional-gpa', 'minimum': 3.0},
            {'type': 'corequisite-condition', 'rule': all_of(course('EL1000', 'C'), course('EL2216', 'C'), course('ED3700', 'C'),
                any_of(math_sufficient, review('Math and/or wording: both listed math parts prove a sufficient route; other routes need review.')))}),
        'ED4050@2020-2022': all_of(*education),
        'ED4200': all_of(*education),
        'ED4121': all_of(*education, {'type': 'proficiency', 'key': 'MATH_PROFICIENCY'}, {'type': 'completed-laboratory-science'}),
        'ED4122': all_of(*education, course('MA1020'), any_of(
            any_of(*(course(c) for c in ['MA2000', 'MA2050', 'MA2080', 'MA2090', 'MA2300', 'MA2310', 'MA2320', 'MA3030', 'MA3160', 'MA3210', 'MA3520'])),
            review('A different second math course needs source review.'))),
        'ED4220': all_of(*education, {'type': 'corequisite-condition', 'rule': review('Source names CE4200; do not silently invent an ED4200 equivalence.')}),
        'ED5925@2020-2022': all_of(*education, course('ED4200'), any_of(course('ED4050', version='2020-2022'), course('ED4850')),
            review('Historical source ED400 reference is unresolved; no nonexistent course is added.')),
        'EL1000': {'type': 'scoped-evidence', 'scope': 'placement:ECI'},
        'EL2216': {'type': 'scoped-evidence', 'scope': 'placement:ECII'},
        'ML1000': {'type': 'scoped-evidence', 'scope': 'language-background:French:beginner'},
        'PX5500': any_of({'type': 'minimum-earned-credits', 'minimum': 88}, permission),
        'MC3835': any_of(all_of(any_of(course('AS2802'), course('MC2802')), any_of(course('AS3800'), course('MC2802')), writing),
            permission, review('Other parses of the mixed media prerequisite need review.')),
    }
    return {code: {'rule': rule, 'sourceRuleSHA256': pinned[code],
                    'status': 'read-only-source-proof-overlay-not-active-catalog-change',
                    'admissionsSourceSHA256': admissions_sha if code.startswith('ED') else None,
                    'ambiguityIsNotAutomaticallySatisfied': True}
            for code, rule in values.items()}
