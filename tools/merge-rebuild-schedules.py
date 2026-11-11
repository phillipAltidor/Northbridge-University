#!/usr/bin/env python3
"""Reuse unchanged semester solutions only after exact input comparison and audit."""
import argparse
import hashlib
import importlib.util
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def digest(value):
    return hashlib.sha256(json.dumps(value, sort_keys=True).encode()).hexdigest()


def merge(source, parent, candidate, previous, replacements):
    if previous['candidateSHA256'] != digest(parent):
        raise ValueError('Parent schedule does not match its candidate')
    for key in ['students', 'courses', 'prerequisiteEdges', 'corequisiteEdges', 'historicalOfferingTemplates', 'prerequisiteSourceProofs', 'sourceLaboratoryScienceEvidence', 'northbridgeProjectPolicies', 'reviewedDegreeProperties']:
        if parent.get(key) != candidate.get(key):
            raise ValueError('Reusing semester solutions requires unchanged profiles and course rules: ' + key)
    old_terms = {}
    new_terms = {}
    for a in parent['attempts']:
        old_terms.setdefault(a['termCode'], []).append(a)
    for a in candidate['attempts']:
        new_terms.setdefault(a['termCode'], []).append(a)
    changed = {term for term in set(old_terms) | set(new_terms)
               if old_terms.get(term) != new_terms.get(term)
               or parent['rosters'].get(term) != candidate['rosters'].get(term)}
    sections = [s for s in previous['sections'] if s['term'] not in changed]
    reused = sorted({s['term'] for s in sections})
    failed = {f['term']: f for f in previous['failures'] if f['term'] not in changed}
    for replacement in replacements:
        if replacement['candidateSHA256'] != digest(candidate):
            raise ValueError('Replacement schedule belongs to a different candidate')
        terms = {s['term'] for s in replacement['sections']}
        if not terms <= changed:
            raise ValueError('Replacement solutions must cover changed terms only')
        if terms & {s['term'] for s in sections}:
            raise ValueError('Duplicate semester solution')
        sections.extend(replacement['sections'])
        for f in replacement['failures']:
            failed[f['term']] = f
    completed = sorted({s['term'] for s in sections})
    expected = sorted(candidate['rosters'])
    if failed or completed != expected:
        raise ValueError('All expected semester solutions are required for a complete merge')
    result = {'status': 'candidate-scheduled', 'candidateSHA256': digest(candidate),
              'parentCandidateSHA256': digest(parent), 'reusedScheduleTerms': reused,
              'changedTermsRescheduled': sorted(changed), 'expectedTerms': expected,
              'scheduledTerms': completed, 'knownMeetingTimesOnly': True,
              'reuseBasis': 'Exact profile, course-rule, transcript and roster comparison; independent full audit.',
              'workingDatabaseChanged': False, 'sections': sections, 'failures': []}
    spec = importlib.util.spec_from_file_location('rebuild_audit', ROOT / 'tools/audit-academic-rebuild.py')
    audit_module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(audit_module)
    review = audit_module.audit(source, candidate, result)
    if review['structuralErrors'] or review['scheduleValidationErrors']:
        raise ValueError('Independent academic or schedule audit failed')
    if 'complete-student-faculty-room-schedule-not-verified' in review['activationBlockers']:
        raise ValueError('Independent audit did not verify every semester')
    return result


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    for name in ['source', 'parent-candidate', 'candidate', 'parent-schedule', 'output']:
        parser.add_argument('--' + name, required=True)
    parser.add_argument('--replacement', action='append', required=True)
    args = parser.parse_args()
    inputs = [args.source, args.parent_candidate, args.candidate, args.parent_schedule] + args.replacement
    for p in inputs + [args.output]:
        if not str(Path(p).resolve()).startswith('/private/tmp/'):
            raise SystemExit('Private paths required')
    if Path(args.output).exists():
        raise SystemExit('Refusing to overwrite a schedule')
    source, parent, candidate, previous, *replacements = [json.loads(Path(p).read_text()) for p in inputs]
    result = merge(source, parent, candidate, previous, replacements)
    Path(args.output).write_text(json.dumps(result, indent=2) + '\n')
    Path(args.output).chmod(0o600)
    print(json.dumps({'status': result['status'], 'semesters': len(result['scheduledTerms']),
                      'sections': len(result['sections']), 'untouchedSemestersReused': len(result['reusedScheduleTerms']),
                      'changedSemestersRescheduled': result['changedTermsRescheduled'], 'workingDatabaseChanged': False}))
