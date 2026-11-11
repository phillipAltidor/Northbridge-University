#!/usr/bin/env python3
"""Review actual proposed histories independently of the permissive planner."""
import argparse
import collections
import hashlib
import json
import os
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'database/generation'))
from rebuild_oldwestbury import Builder
from rebuild_eligibility import Eligibility
from rebuild_remaining_proofs import proof_rules
from rebuild_source_properties import lab_science_evidence
from rebuild_project_policies import project_rules


def effective_rules(source, candidate):
    b = Builder(source)
    raw = {r['courseVersionOrCode']: r['rule'] for r in json.loads((ROOT / 'database/staging/academic-alignment/rules/all-course-prerequisites.json').read_text())['courses']}
    overlays = proof_rules(raw)
    laboratories = lab_science_evidence(b.meta)
    effective = dict(b.rules)
    from rebuild_foundation_proofs import foundation_rules
    foundations=foundation_rules(raw)
    if 'foundationPrerequisiteProofs'in candidate:
        if candidate['foundationPrerequisiteProofs']!=foundations:raise ValueError('Foundation source proofs changed')
        effective.update({k:v['rule']for k,v in foundations.items()})
    effective.update({k: v['rule'] for k, v in overlays.items()})
    if 'northbridgeProjectPolicies' in candidate:
        policies = project_rules(overlays, raw)
        if candidate['northbridgeProjectPolicies'] != policies:
            raise ValueError('Project rule manifest changed')
        effective.update({k: v['rule'] for k, v in policies.items()})
    if 'entryPlacementPolicy' in candidate:
        policy = json.loads((ROOT / 'database/staging/academic-alignment/ma1020-entry-placement-policy.json').read_text())
        if candidate['entryPlacementPolicy'] != policy or not candidate.get('stagingOnly') or candidate.get('activationAllowed') is not False:
            raise ValueError('Entry placement overlay is allowed only in an explicitly staged candidate')
        effective['MA1020'] = policy['rule']
    return b, effective, laboratories, overlays


def run(source, candidate):
    b, effective, laboratories, overlays = effective_rules(source, candidate)
    histories = collections.defaultdict(list)
    for row in candidate['attempts']:
        histories[row['studentId']].append(row)
    people = {s['studentId']: s for s in candidate['students']}
    totals = collections.defaultdict(collections.Counter)
    courses = collections.defaultdict(collections.Counter)
    details = []
    source_gated = set(json.loads((ROOT / 'database/generation/rebuild_remaining_source_hashes.json').read_text()))
    course_route_counts = collections.Counter()
    for sid, rows in histories.items():
        person = people[sid]
        for row in rows:
            key = row['courseKey']
            context = Eligibility(candidate['courses'], rows, b.termpos, row['termId'], person, key, laboratories)
            rule = effective.get(key, {'type': 'review-required', 'reason': 'No reviewed prerequisite rule.'})
            verdict = context.evaluate(rule)
            phase = 'forecast-only' if row['status'] == 'planned' else 'current-registration' if row['status'] == 'in-progress' else 'completed-history'
            state = verdict['status']
            totals[phase][state] += 1
            courses[key][state] += 1
            if key in source_gated and state == 'satisfied':
                course_route_counts[key] += 1
            details.append({'studentId': sid, 'courseKey': key, 'termCode': row['termCode'], 'phase': phase,
                            'result': verdict, 'effectiveRuleSHA256': hashlib.sha256(json.dumps(rule, sort_keys=True).encode()).hexdigest()})
    summary = {'status': 'independent-academic-prerequisite-review-not-registration-authorization',
               'asOf': candidate.get('asOf'), 'studentsReviewed': len(people), 'studentsWithAttempts': len(histories),
               'studentsWithoutAttempts': len(people) - len(histories), 'attemptsReviewed': len(details),
               'phaseStatusCounts': {k: dict(v) for k, v in totals.items()},
               'courseStatusCounts': {k: dict(v) for k, v in sorted(courses.items())},
               'previouslyGatedAttemptsWithSufficientCourseProof': dict(sorted(course_route_counts.items())),
               'sourceProofOverlays': overlays,
               'laboratoryScienceSourceEvidence': laboratories,
               'permissionsPlacementAndEquivalencesFabricated': False,
               'futureWorkCountedAsEarnedCredit': False, 'workingDatabaseChanged': False,
               'privateStudentIdentifiersPublished': False}
    return summary, details


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--source', required=True)
    parser.add_argument('--candidate', required=True)
    parser.add_argument('--output', required=True)
    args = parser.parse_args()
    for path in [args.source, args.candidate, args.output]:
        if not str(Path(path).resolve()).startswith('/private/tmp/'):
            raise SystemExit('Private paths required')
    source = json.loads(Path(args.source).read_text())
    candidate = json.loads(Path(args.candidate).read_text())
    summary, details = run(source, candidate)
    summary['candidateFileSHA256'] = hashlib.sha256(Path(args.candidate).read_bytes()).hexdigest()
    os.umask(0o077)
    rules_path = Path(args.output).with_name('effective-prerequisite-rules.json')
    rules_path.write_text(json.dumps(effective_rules(source, candidate)[1], indent=2) + '\n')
    rules_path.chmod(0o600)
    summary['effectiveRulesFileSHA256'] = hashlib.sha256(rules_path.read_bytes()).hexdigest()
    Path(args.output).write_text(json.dumps({'summary': summary, 'results': details}, indent=2) + '\n')
    Path(args.output).chmod(0o600)
    (ROOT / 'database/staging/academic-alignment/rebuild-prerequisite-review.json').write_text(json.dumps(summary, indent=2) + '\n')
    print(json.dumps({k: summary[k] for k in ['status', 'studentsReviewed', 'attemptsReviewed', 'phaseStatusCounts', 'previouslyGatedAttemptsWithSufficientCourseProof', 'workingDatabaseChanged']}))
