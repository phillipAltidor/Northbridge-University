#!/usr/bin/env python3
"""Create explicitly fictional Northbridge enrollment evidence for a demo rebuild.

The owner authorized replacing the fictional academic data. These records are
simulated local records, never Old Westbury placements, signatures, transcripts,
or evidence of real fieldwork. Completed qualifications are checked separately.
No grades, course attempts, identities, meetings or teaching assignments change.
"""
import argparse
import collections
import copy
import hashlib
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'database/generation'))
from rebuild_oldwestbury import Builder
from rebuild_eligibility import Eligibility


def digest(value):
    return hashlib.sha256(json.dumps(value, sort_keys=True).encode()).hexdigest()


def leaves(rule, kind):
    if rule.get('type') == kind:
        yield rule
    for child in rule.get('rules', []):
        yield from leaves(child, kind)
    if 'rule' in rule:
        yield from leaves(rule['rule'], kind)


def attach(source, parent, schedule):
    if schedule['candidateSHA256'] != digest(parent) or schedule['status'] != 'candidate-scheduled':
        raise ValueError('Exact complete parent schedule required')
    candidate = copy.deepcopy(parent)
    b = Builder(source); b.enable_source_proof_planning(); b.enable_project_policies()
    if candidate['northbridgeProjectPolicies'] != b.project_policies:
        raise ValueError('Project policy manifest mismatch')
    histories = collections.defaultdict(list)
    for row in candidate['attempts']:
        histories[row['studentId']].append(row)
    assignments = {(sid, s['termId'], s['courseKey']): s['facultyId'] for s in schedule['sections'] for sid in s['studentIds']}
    counts = collections.Counter()
    for person in candidate['students']:
        sid = person['studentId']; rows = sorted(histories[sid], key=lambda a: b.termpos[a['termId']])
        if person.get('registrarEvidence'):
            raise ValueError('Refusing to overwrite existing academic evidence')
        records = {}; person['registrarEvidence'] = records
        for row in rows:
            key, tid = row['courseKey'], row['termId']; position = b.termpos[tid]
            rule = b.rules.get(key, {'type': 'review-required'})
            requirements = [(n['scope'], n.get('authority')) for n in leaves(rule, 'scoped-evidence')]
            # Select only one permission authority when the source offers OR.
            permissions = list(leaves(rule, 'approval'))
            initial = Eligibility(candidate['courses'], rows, b.termpos, tid, person, key, b.source_laboratories).evaluate(rule)
            if permissions and initial['status'] not in ['satisfied', 'conditional']:
                requirements.append(('course-permission:' + key, permissions[0].get('authority')))
            for scope, authority in requirements:
                if scope in records:
                    continue
                context = Eligibility(candidate['courses'], rows, b.termpos, tid, person, key, b.source_laboratories)
                state = 'approved'; basis = 'Explicit synthetic Northbridge profile/consent decision under owner-authorized fictional rebuild.'
                if scope == 'placement:ECII':
                    qualification = context.course('EL1000', 'C')
                    if qualification['status'] != 'satisfied':
                        state = 'conditional' if qualification['status'] == 'conditional' else 'pending-review'
                    basis = 'Simulated ECII placement record, with prior Composition I grade checked separately; no placement score is invented.'
                if scope == 'school-of-education-admission':
                    academic = copy.deepcopy(rule)
                    # Evaluate the admission subexpression without its formal
                    # admission log, rather than deriving admission from a grade.
                    admission = next((n for n in leaves(rule, 'and') if any(c.get('scope') == scope for c in n.get('rules', []))), None)
                    if admission is None:
                        raise ValueError('Missing explicit admission academic conditions')
                    academic = {'type': 'and', 'rules': [n for n in admission['rules'] if n.get('scope') != scope]}
                    qualification = context.evaluate(academic)
                    state = 'approved' if qualification['status'] == 'satisfied' else 'conditional' if qualification['status'] == 'conditional' else 'pending-review'
                    basis = 'Simulated formal Northbridge admission log following the separately checked 32-credit, GPA, writing, math and education-course requirements.'
                if scope == 'project-review:ED5925:ED400':
                    basis = 'Simulated Northbridge academic-review record under NB-REBUILD-ED5925-001; undefined ED400 is not treated as a completed course or verified equivalency.'
                event = row['termCode'][:4] + ('-08-01' if row['termCode'].endswith('09') else '-01-01')
                record = {'status': state, 'scope': scope, 'studentId': sid,
                          'approvalId': 'NB-SIM-' + digest([sid, scope, position])[:24],
                          'approvedBy': 'Northbridge synthetic academic records', 'approvedAt': event,
                          'effectiveTermPosition': position, 'termOrder': int(row['termCode']),
                          'createdAt': '2026-11-07', 'fictionalSimulation': True,
                          'isOldWestburyApproval': False, 'basis': basis}
                if authority:
                    record['authority'] = authority
                    record['fictionalFacultyId'] = assignments[sid, tid, key]
                records[scope] = record; counts[scope.split(':')[0] + ':' + state] += 1
    # Rebind only after proving that the entire scheduling input is unchanged.
    old = copy.deepcopy(parent); new = copy.deepcopy(candidate)
    for person in new['students']:
        person.pop('registrarEvidence', None)
    if new != old:
        raise ValueError('Evidence attachment changed non-evidence candidate inputs')
    output = copy.deepcopy(schedule)
    output['parentCandidateSHA256'] = digest(parent); output['candidateSHA256'] = digest(candidate)
    output['evidenceOnlyRebind'] = True
    output['reuseBasis'] = 'Exact equality of courses, attempts, rosters, profiles excluding new simulated registrar evidence, source rules and meetings; full independent audit remains required.'
    return candidate, output, dict(counts)


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    for name in ['source', 'candidate', 'schedule', 'output', 'schedule-output']:
        parser.add_argument('--' + name, required=True)
    args = parser.parse_args()
    for value in vars(args).values():
        if not str(Path(value).resolve()).startswith('/private/tmp/'):
            raise SystemExit('Private paths required')
    for value in [args.output, args.schedule_output]:
        if Path(value).exists():
            raise SystemExit('Refusing to overwrite evidence or schedules')
    source, candidate, schedule = [json.loads(Path(p).read_text()) for p in [args.source, args.candidate, args.schedule]]
    candidate, schedule, counts = attach(source, candidate, schedule)
    for path, value in [(args.output, candidate), (args.schedule_output, schedule)]:
        Path(path).write_text(json.dumps(value, indent=2) + '\n'); Path(path).chmod(0o600)
    print(json.dumps({'status': 'explicit-fictional-northbridge-evidence-added-not-real-university-approvals', 'recordCounts': counts, 'workingDatabaseChanged': False}))
