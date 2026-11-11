#!/usr/bin/env python3
"""Exercise activation safeguards against a private, real rebuild candidate."""
import argparse
import importlib.util
import json
from pathlib import Path

root = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('rebuild_audit', root / 'tools/audit-academic-rebuild.py')
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)
parser = argparse.ArgumentParser()
parser.add_argument('--source', required=True)
parser.add_argument('--candidate', required=True)
parser.add_argument('--schedule', required=True)
parser.add_argument('--parent-candidate')
args = parser.parse_args()
for path in [args.source, args.candidate, args.schedule]:
    if not str(Path(path).resolve()).startswith('/private/tmp/'):
        raise SystemExit('Private input paths required')
if args.parent_candidate and not str(Path(args.parent_candidate).resolve()).startswith('/private/tmp/'):
    raise SystemExit('Private parent candidate path required')
source, candidate, schedule = [json.loads(Path(p).read_text()) for p in [args.source, args.candidate, args.schedule]]
baseline = module.audit(source, candidate, schedule)
assert not baseline['structuralErrors'], 'Candidate structural audit failed'
assert not baseline['scheduleValidationErrors'], 'Proposed successful term schedule contains a conflict/source mismatch'
assert baseline['status'] == 'blocked-from-activation', 'Unreviewed academic evidence must not activate a candidate'
changed = dict(candidate)
changed['attempts'] = candidate['attempts'] + [candidate['attempts'][0]]
assert module.audit(source, changed)['structuralErrors'].get('duplicate-attempt'), 'Duplicate attempt went undetected'
changed['attempts'] = list(candidate['attempts'])
index = next(i for i, a in enumerate(changed['attempts']) if a['termId'] in [1, 2])
changed['attempts'][index] = {**changed['attempts'][index], 'grade': 'A'}
assert module.audit(source, changed)['structuralErrors'].get('current-or-future-final-grade'), 'Future/current final grade went undetected'
partial = dict(schedule)
partial['status'] = 'candidate-scheduled'
partial['sections'] = [s for s in schedule['sections'] if s['term'] == schedule['sections'][0]['term']]
assert 'complete-student-faculty-room-schedule-not-verified' in module.audit(source, candidate, partial)['activationBlockers'], 'A partial rehearsal was accepted as a complete schedule'
checks = 6
alias_index = next((i for i, s in enumerate(schedule['sections']) if s['sourceCourseCode'] != candidate['courses'][s['courseKey']]['courseCode'] or '@' in s['courseKey']), None)
if alias_index is not None:
    disguised = dict(schedule)
    disguised['sections'] = list(schedule['sections'])
    disguised['sections'][alias_index] = {**schedule['sections'][alias_index], 'officialCRN': schedule['sections'][alias_index]['sourceReferenceCRN']}
    assert module.audit(source, candidate, disguised)['scheduleValidationErrors'].get('official-crn-course-identity-mismatch'), 'A borrowed CRN was misrepresented as the official identity of another course/version'
    checks += 1
if args.parent_candidate:
    parent = json.loads(Path(args.parent_candidate).read_text())
    for key in ['students', 'courses', 'prerequisiteEdges', 'corequisiteEdges']:
        assert parent[key] == candidate[key], 'An elective repair altered profiles, course definitions or prerequisite rules'
    checks += 1
    identity = lambda a: (a['studentId'], a['termId'], a['courseKey'])
    old_rows = {identity(a): a for a in parent['attempts']}
    new_rows = {identity(a): a for a in candidate['attempts']}
    removed, added = set(old_rows) - set(new_rows), set(new_rows) - set(old_rows)
    assert len(removed) == len(added) == 1, 'Expected exactly one elective substitution'
    before, after = old_rows[next(iter(removed))], new_rows[next(iter(added))]
    assert {k: v for k, v in before.items() if k != 'courseKey'} == {k: v for k, v in after.items() if k != 'courseKey'}, 'Grade, semester, student or completion status changed'
    assert all(old_rows[k] == new_rows[k] for k in set(old_rows) & set(new_rows)), 'An unrelated attempt changed'
    checks += 1
    person = next(s for s in parent['students'] if s['studentId'] == before['studentId'])
    assert before['courseKey'] not in person['targetCourses'], 'A required major/minor course was removed'
    old_course, new_course = parent['courses'][before['courseKey']], candidate['courses'][after['courseKey']]
    assert old_course['credits'] == new_course['credits'], 'Earned credit value changed'
    assert not old_course['liberalArts'] or new_course['liberalArts'], 'Liberal arts credits were lost'
    assert not old_course['upperDivision'] or new_course['upperDivision'], 'Upper division credits were lost'
    checks += 1
    definitions = json.loads((root / 'database/staging/academic-alignment/rules/general-education-mappings.json').read_text())['rows']
    domains = lambda code: {r['domain'] for r in definitions if r['sourceCourseCode'] == code and not r['lectureLabBundleCandidate']}
    assert domains(old_course['courseCode']) <= domains(new_course['courseCode']), 'A general education domain was lost'
    assert len(schedule['reusedScheduleTerms']) == 13 and schedule['changedTermsRescheduled'] == [before['termCode']], 'Unchanged semesters were not explicitly identified'
    checks += 1
print('PASS: ' + str(checks) + ' private rebuild integration safeguards; no database writes or student identifiers published.')
