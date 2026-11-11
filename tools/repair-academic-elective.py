#!/usr/bin/env python3
"""Repair a proposed fictional elective, without rewriting real/active records.

Keep the term, grade, credits, required courses, and prerequisite ordering.
This proposes new fictional data; it is never a verified course equivalency.
"""
import argparse
import collections
import hashlib
import json
import math
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'database/generation'))
from rebuild_oldwestbury import Builder, canonical


def gated(node):
    if node['type'] in ['approval', 'review-required', 'unresolved-course-reference']:
        return True
    return any(gated(c) for c in node.get('rules', [])) or ('rule' in node and gated(node['rule']))


def mentions(node, pattern):
    if isinstance(node, str):
        return bool(pattern.search(node))
    if isinstance(node, dict):
        return any(mentions(value, pattern) for value in node.values())
    if isinstance(node, list):
        return any(mentions(value, pattern) for value in node)
    return False


def repair(source, candidate, term, course):
    if candidate.get('status') != 'generated-not-applied' or candidate.get('workingDatabaseChanged') is not False:
        raise ValueError('Only an inactive fictional rebuild candidate can be repaired')
    b = Builder(source)
    if 'prerequisiteSourceProofs' in candidate:
        b.enable_source_proof_planning()
    if 'northbridgeProjectPolicies' in candidate:
        b.enable_project_policies()
    b.courses = candidate['courses']
    b.prior = {k: set(v) for k, v in candidate['prerequisiteEdges'].items()}
    b.coreq = {k: set(v) for k, v in candidate['corequisiteEdges'].items()}
    people = {p['studentId']: p for p in candidate['students']}
    histories = collections.defaultdict(list)
    for a in candidate['attempts']:
        histories[a['studentId']].append(a)
    ge = collections.defaultdict(set)
    for row in b.ge:
        if not row['lectureLabBundleCandidate']:
            ge[row['sourceCourseCode']].add(row['domain'])
    original = b.courses[course]
    named = re.compile(r'\b' + re.sub(r'([A-Z]+)(\d+)', r'\1\\s*\2', canonical(course)) + r'\b', re.I)
    parent_hash = hashlib.sha256(json.dumps(candidate, sort_keys=True).encode()).hexdigest()
    for row in candidate['attempts']:
        if row['termCode'] != term or row['courseKey'] != course:
            continue
        person = people[row['studentId']]
        if course in person['targetCourses']:
            continue
        rows = histories[row['studentId']]
        position = b.termpos[row['termId']]
        # Do not remove even an optional course if a later source rule names it,
        # including unparsed prose that a permissive planner might overlook.
        later = [a for a in rows if b.termpos[a['termId']] > position]
        if any(mentions(b.rules.get(a['courseKey'], {}), named) for a in later):
            continue
        done = {a['courseKey']: a['termId'] for a in rows if b.termpos[a['termId']] < position}
        selected = {a['courseKey'] for a in rows if a['termCode'] == term and a is not row}
        used = {a['courseKey'] for a in rows}
        plan = {'major': person['major'], 'minor': person['minor'], 'courses': person['targetCourses']}
        candidates = []
        for alternative, definition in b.courses.items():
            if 'reviewedDegreeProperties' in candidate and alternative not in candidate['reviewedDegreeProperties']['courseProperties']:
                continue
            if alternative in used or alternative == course or definition['credits'] != original['credits']:
                continue
            if original['liberalArts'] and not definition['liberalArts']:
                continue
            if original['upperDivision'] and not definition['upperDivision']:
                continue
            if not ge[canonical(course)] <= ge[canonical(alternative)]:
                continue
            if gated(b.rules.get(alternative, {'type': 'review-required'})):
                continue
            if not b.sources(term, alternative) or not b.ready(alternative, done, selected, plan):
                continue
            if not b.meetings_fit(term, selected | {alternative}):
                continue
            n = len(candidate['rosters'][term].get(alternative, []))
            delta = math.ceil((n + 1) / 10) - math.ceil(n / 10)
            d = definition['departmentId']
            existing = candidate['sectionCountsByDepartment'][term].get(str(d), 0)
            if d != original['departmentId'] and existing + delta + b.grad_load[row['termId'], d] > b.dept_caps[d]:
                continue
            candidates.append((delta, d == original['departmentId'], -n, alternative))
        if not candidates:
            continue
        alternative = min(candidates)[-1]
        old_ids = candidate['rosters'][term][course]
        old_ids.remove(row['studentId'])
        candidate['rosters'][term].setdefault(alternative, []).append(row['studentId'])
        row['courseKey'] = alternative
        counts = collections.Counter()
        for key, ids in candidate['rosters'][term].items():
            counts[b.courses[key]['departmentId']] += math.ceil(len(ids) / 10)
        candidate['sectionCountsByDepartment'][term] = {str(k): v for k, v in sorted(counts.items())}
        candidate.setdefault('fictionalElectiveRepairs', []).append({
            'parentCandidateSHA256': parent_hash, 'term': term, 'studentId': row['studentId'],
            'oldCourse': course, 'newCourse': alternative,
            'reason': 'Move an optional elective off an over-subscribed official meeting pattern.',
            'requiredMajorAndMinorCoursesPreserved': True, 'gradeAndCreditsPreserved': True,
            'generalEducationDomainsPreserved': True, 'verifiedTranscriptEquivalence': False,
        })
        return {'term': term, 'oldCourse': course, 'newCourse': alternative, 'studentsChanged': 1,
                'attemptCountChanged': False, 'workingDatabaseChanged': False}
    raise ValueError('No eligible optional elective can be substituted safely')


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    for name in ['source', 'candidate', 'term', 'course', 'output']:
        parser.add_argument('--' + name, required=True)
    args = parser.parse_args()
    for p in [args.source, args.candidate, args.output]:
        if not str(Path(p).resolve()).startswith('/private/tmp/'):
            raise SystemExit('Private input/output paths required')
    if Path(args.output).exists():
        raise SystemExit('Refusing to overwrite a candidate')
    source = json.loads(Path(args.source).read_text())
    candidate = json.loads(Path(args.candidate).read_text())
    result = repair(source, candidate, args.term, args.course)
    Path(args.output).write_text(json.dumps(candidate, indent=2) + '\n')
    Path(args.output).chmod(0o600)
    print(json.dumps(result))
