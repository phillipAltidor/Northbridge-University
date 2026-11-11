#!/usr/bin/env python3
"""Review upcoming minimum enrollment; stage same-course rebalancing only, never apply."""
import argparse
import collections
import copy
import hashlib
import json
import os
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'database/generation'))
from academic_meetings import meetings, overlap
from ortools.sat.python import cp_model


def review(schedule, term, minimum=5):
    spring = [s for s in schedule['sections'] if s['term'] == term]
    if not spring:
        raise ValueError('Requested term is absent.')
    courses = collections.defaultdict(list)
    for section in spring:
        courses[section['courseKey']].append(section)
    affected = []
    for key, sections in sorted(courses.items()):
        low = sum(len(s['studentIds']) < minimum for s in sections)
        if not low:
            continue
        demand = sum(len(s['studentIds']) for s in sections)
        kind = ('course-demand-below-five' if demand < minimum else
                'rebalance-existing-sections' if demand >= minimum * len(sections) else
                'consolidation-review')
        affected.append({'courseKey': key, 'category': kind, 'courseEnrollment': demand,
                         'underMinimumSections': low,
                         'beforeGroupSizes': [len(s['studentIds']) for s in sections]})
    mutable = {r['courseKey'] for r in affected if r['category'] == 'rebalance-existing-sections'}
    patterns = {id(s): meetings(s) for s in spring}
    fixed = collections.defaultdict(list)
    for s in spring:
        if s['courseKey'] not in mutable:
            for sid in s['studentIds']:
                fixed[sid].extend(patterns[id(s)])
    model = cp_model.CpModel()
    options = collections.defaultdict(list)
    choices = {}
    rosters = {}
    goals = {}
    moves = []
    for key in sorted(mutable):
        sections = courses[key]
        original = {sid: i for i, s in enumerate(sections) for sid in s['studentIds']}
        if len(original) != sum(len(s['studentIds']) for s in sections):
            raise ValueError('Duplicate same-course student enrollment.')
        goal = model.NewBoolVar('resolve_' + key)
        goals[key] = goal
        for sid, current in original.items():
            alternatives = []
            for i, s in enumerate(sections):
                if overlap(patterns[id(s)], fixed[sid]):
                    continue
                variable = model.NewBoolVar(f'{key}_{sid}_{i}')
                choices[key, sid, i] = variable
                alternatives.append(variable)
                rosters.setdefault((key, i), []).append(variable)
                options[sid].append((key, i, variable, patterns[id(s)]))
                if i != current:
                    moves.append(variable)
            if not alternatives:
                raise ValueError('Original roster has no conflict-free assignment.')
            model.Add(sum(alternatives) == 1)
        for i, s in enumerate(sections):
            load = sum(rosters.get((key, i), []))
            model.Add(load <= s['capacity'])
            # Preserve already viable groups; do not worsen a smaller group.
            model.Add(load >= min(minimum, len(s['studentIds'])))
            model.Add(load >= minimum).OnlyEnforceIf(goal)
    for alternatives in options.values():
        for i, (key, _, variable, pattern) in enumerate(alternatives):
            for otherkey, _, other, otherpattern in alternatives[i + 1:]:
                if key != otherkey and overlap(pattern, otherpattern):
                    model.Add(variable + other <= 1)
    model.Minimize((len(moves) + 1) * sum(1 - g for g in goals.values()) + sum(moves))
    solver = cp_model.CpSolver()
    solver.parameters.max_time_in_seconds = 20
    solver.parameters.num_search_workers = 1
    solver.parameters.random_seed = 0
    status = solver.Solve(model)
    if status not in (cp_model.OPTIMAL, cp_model.FEASIBLE):
        raise RuntimeError('No verified proposal returned; no data changed.')
    staged = copy.deepcopy(schedule)
    staged_spring = [s for s in staged['sections'] if s['term'] == term]
    staged_courses = collections.defaultdict(list)
    for s in staged_spring:
        staged_courses[s['courseKey']].append(s)
    for key in mutable:
        for s in staged_courses[key]:
            s['studentIds'] = []
    moved = 0
    for (key, sid, i), variable in choices.items():
        if solver.Value(variable):
            staged_courses[key][i]['studentIds'].append(sid)
            moved += sid not in courses[key][i]['studentIds']
    # Independent verification, including fixed courses and cross-course changes.
    def membership(rows):
        return collections.Counter((s['courseKey'], sid) for s in rows for sid in s['studentIds'])
    if membership(spring) != membership(staged_spring):
        raise RuntimeError('Course membership changed.')
    occupied = collections.defaultdict(list)
    for s in staged_spring:
        if len(s['studentIds']) > s['capacity']:
            raise RuntimeError('Section capacity exceeded.')
        pattern = meetings(s)
        for sid in s['studentIds']:
            if overlap(pattern, occupied[sid]):
                raise RuntimeError('Staged student time conflict.')
            occupied[sid].extend(pattern)
    for record in affected:
        record['proposedGroupSizes'] = [len(s['studentIds']) for s in staged_courses[record['courseKey']]]
        record['resolvedByProposal'] = min(record['proposedGroupSizes']) >= minimum
    summary = {
        'status': 'minimum-enrollment-review-proposal-not-applied', 'term': term,
        'sectionsReviewed': len(spring), 'underMinimumSections': sum(r['underMinimumSections'] for r in affected),
        'affectedCourses': len(affected),
        'categories': dict(collections.Counter(r['category'] for r in affected)),
        'solverStatus': solver.StatusName(status), 'proposedRosterMoves': moved,
        'sectionsUnderMinimumAfterProposal': sum(len(s['studentIds']) < minimum for s in staged_spring),
        'coursesResolvedByProposal': sum(r['resolvedByProposal'] for r in affected),
        'courseMembershipPreserved': True, 'studentConflicts': 0,
        'sectionsCanceled': 0, 'databaseChanged': False, 'activationAllowed': False,
        'cutoffPolicy': 'No approved cancellation review deadline is configured; no cancellation is inferred.',
        'courses': affected,
    }
    staged['status'] = 'minimum-enrollment-proposal-awaiting-full-validation'
    staged['minimumEnrollmentReviewOnly'] = True
    return summary, staged


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--schedule', required=True)
    parser.add_argument('--output', required=True)
    parser.add_argument('--term', default='202702')
    args = parser.parse_args()
    source = Path(args.schedule).resolve()
    destination = Path(args.output).resolve()
    if not all(str(p).startswith('/private/tmp/') for p in (source, destination)):
        raise SystemExit('Private paths required.')
    os.umask(0o077)
    destination.mkdir(mode=0o700, exist_ok=False)
    summary, staged = review(json.loads(source.read_text()), args.term)
    summary['parentScheduleFileSHA256'] = hashlib.sha256(source.read_bytes()).hexdigest()
    proposal_text = json.dumps(staged, sort_keys=True) + '\n'
    summary['proposalScheduleFileSHA256'] = hashlib.sha256(proposal_text.encode()).hexdigest()
    (destination / 'minimum-enrollment-review.json').write_text(json.dumps(summary, indent=2) + '\n')
    (destination / 'proposed-schedule.json').write_text(proposal_text)
    print(json.dumps({k: v for k, v in summary.items() if k != 'courses'}, indent=2))
