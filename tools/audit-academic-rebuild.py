#!/usr/bin/env python3
"""Validate private replacement records; publish aggregate findings only.

This is an activation gate, not an authorization to waive academic conditions.
"""
import argparse
import collections
import hashlib
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'database/generation'))
from rebuild_oldwestbury import Builder
from academic_meetings import meetings, overlap, old_meetings
from rebuild_eligibility import Eligibility
from rebuild_degree_properties import reviewed_properties


def unresolved(rule):
    if rule['type'] in ['review-required', 'approval', 'unresolved-course-reference']:
        return True
    if rule['type'] in ['and', 'or']:
        # Conservative gate: a conditional branch still needs explicit evidence
        # or proof that a fully verified alternative was satisfied per attempt.
        return any(unresolved(r) for r in rule['rules'])
    if rule['type'] in ['corequisite-condition', 'completed-or-concurrent-condition']:
        return unresolved(rule['rule'])
    return False


def audit(source, candidate, schedule=None):
    b = Builder(source)
    errors = collections.Counter()
    attempts = collections.defaultdict(list)
    students = {s['studentId']: s for s in candidate['students']}
    old_students = {s['student_ID']: s for s in source['Student']}
    seen = set()
    courses = candidate['courses']
    if 'reviewedDegreeProperties' in candidate:
        expected_properties = reviewed_properties(courses)
        proposed_properties = candidate['reviewedDegreeProperties']
        if proposed_properties.get('sourceAttributeFileSHA256') != expected_properties['sourceAttributeFileSHA256']:
            errors['degree-property-source-file-mismatch'] += 1
        for key, facts in expected_properties['courseProperties'].items():
            if proposed_properties.get('courseProperties', {}).get(key) != facts:
                errors['reviewed-degree-property-evidence-mismatch'] += 1
            if any(courses[key].get(field) != facts[field] for field in ['upperDivision','level','liberalArts','classifications','labBearing']):
                errors['course-properties-disagree-with-reviewed-evidence'] += 1
    proof_mode = 'prerequisiteSourceProofs' in candidate
    if proof_mode:
        b.enable_source_proof_planning()
        if candidate['prerequisiteSourceProofs'] != b.source_proofs or candidate.get('sourceLaboratoryScienceEvidence') != b.source_laboratories:
            errors['prerequisite-source-proof-manifest-mismatch'] += 1
        if 'northbridgeProjectPolicies' in candidate:
            b.enable_project_policies()
            if candidate['northbridgeProjectPolicies'] != b.project_policies:
                errors['northbridge-project-policy-manifest-mismatch'] += 1
    if 'historicalOfferingTemplates' in candidate and candidate['historicalOfferingTemplates'] != b.former_templates:
        errors['former-name-template-evidence-mismatch'] += 1
    b.courses = courses
    b.prior = {k: set(v) for k, v in candidate['prerequisiteEdges'].items()}
    b.coreq = {k: set(v) for k, v in candidate['corequisiteEdges'].items()}
    b.requirements = collections.defaultdict(list)
    gated = collections.Counter()
    term_loads = collections.Counter()
    for a in candidate['attempts']:
        sid, tid, key = a['studentId'], a['termId'], a['courseKey']
        if sid not in b.ug_ids or sid not in students:
            errors['non-undergraduate-or-unknown-student'] += 1
        if key not in courses:
            errors['unknown-course-version'] += 1
            continue
        identity = (sid, tid, key)
        if identity in seen:
            errors['duplicate-attempt'] += 1
        seen.add(identity)
        if tid in [1, 2] and a['grade'] is not None:
            errors['current-or-future-final-grade'] += 1
        if tid not in [1, 2] and a['grade'] not in ['A', 'A-', 'B+', 'B']:
            errors['unexpected-rebuilt-fictional-grade'] += 1
        if not students[sid]['activeOriginalCohort'] and tid in [1, 2]:
            errors['historical-account-reactivated'] += 1
        if not b.sources(a['termCode'], key):
            errors['missing-official-term-source'] += 1
        if not proof_mode and unresolved(b.rules.get(key, {'type': 'review-required'})):
            gated[key] += 1
        term_loads[sid, tid] += courses[key]['credits']
        attempts[sid].append(a)
    for (sid, tid), credits in term_loads.items():
        lo, hi = (12, 16) if students[sid]['mode'] == 'Full-Time' else (4, 8)
        if not lo <= credits <= hi:
            errors['student-term-load-outside-bounds'] += 1
    readiness_failures = collections.Counter()
    for sid, rows in attempts.items():
        byterm = collections.defaultdict(list)
        for a in rows:
            byterm[a['termId']].append(a)
        done = {}
        for tid, entries in sorted(byterm.items(), key=lambda x: b.termpos[x[0]]):
            chosen = {a['courseKey'] for a in entries}
            plan = {'major': students[sid]['major'], 'minor': students[sid]['minor'], 'courses': students[sid]['targetCourses']}
            for a in entries:
                if proof_mode:
                    context = Eligibility(courses, rows, b.termpos, tid, students[sid], a['courseKey'], b.source_laboratories)
                    verdict = context.evaluate(b.rules.get(a['courseKey'], {'type': 'review-required'}))
                    if verdict['status'] == 'unsatisfied':readiness_failures[a['courseKey']] += 1
                    if verdict['status'] == 'review-required':gated[a['courseKey']] += 1
                elif not b.ready(a['courseKey'], done, chosen, plan):
                    readiness_failures[a['courseKey']] += 1
            done.update({a['courseKey']: tid for a in entries})
    if readiness_failures:
        errors['known-prerequisite-order-violations'] = sum(readiness_failures.values())
    for term, counts in candidate['sectionCountsByDepartment'].items():
        tid = next((a['termId'] for a in candidate['attempts'] if a['termCode'] == term), None)
        for department, count in counts.items():
            d = int(department)
            if count + b.grad_load[tid, d] > b.dept_caps[d]:
                errors['department-faculty-workload-capacity'] += 1
    if set(students) != b.ug_ids:
        errors['undergraduate-population-mismatch'] += 1
    schedule_errors = collections.Counter()
    scheduled_terms = set()
    if schedule:
        roster = set()
        faculty = {f['faculty_ID']: f for f in source['Faculty']}
        departments = collections.defaultdict(set)
        for row in source['Faculty_Department']:
            departments[row['faculty_ID']].add(row['dept_ID'])
        rooms = {r['room_ID']: r for r in source['Room']}
        occupied = collections.defaultdict(list)
        faculty_counts = collections.Counter()
        for sec in source['Course_Section']:
            if b.oldcourses[sec['course_ID']]['course_Type'] != 'Graduate':
                continue
            tid = sec['semester_ID']
            for kind, resource in [('faculty', sec['faculty_ID']), ('room', sec['room_ID'])]:
                occupied[tid, kind, resource].extend(old_meetings(source, sec))
            faculty_counts[tid, sec['faculty_ID']] += 1
        for sec in schedule['sections']:
            term, tid, key = sec['term'], sec['termId'], sec['courseKey']
            scheduled_terms.add(term)
            if sec['capacity'] != 10 or not 0 < len(sec['studentIds']) <= 10 or len(set(sec['studentIds'])) != len(sec['studentIds']):
                schedule_errors['invalid-roster-or-capacity'] += 1
            official = [s for s in b.sources(term, key) if s['courseReferenceNumber'] == sec['sourceReferenceCRN']]
            if not official or sec['meetings'] != official[0]['meetings'] or sec['sourceSectionLabel'] != official[0]['sequenceNumber']:
                schedule_errors['official-source-meeting-or-label-mismatch'] += 1
            if official:
                source_code = official[0]['subject'] + official[0]['courseNumber']
                expected_crn = official[0]['courseReferenceNumber'] if source_code == courses[key]['courseCode'] and '@' not in key else None
                if sec['officialCRN'] != expected_crn or sec['sourceCourseCode'] != source_code:
                    schedule_errors['official-crn-course-identity-mismatch'] += 1
                if 'sourceCourseTitle' in sec and sec['sourceCourseTitle'] != official[0]['courseTitle']:
                    schedule_errors['official-source-course-title-mismatch'] += 1
            if sec['departmentId'] != courses[key]['departmentId']:
                schedule_errors['course-department-mismatch'] += 1
            if '@' in key and sec['officialCRN'] is not None:
                schedule_errors['historical-version-claimed-unverified-official-crn'] += 1
            fid, rid = sec['facultyId'], sec['roomId']
            if fid not in faculty or sec['departmentId'] not in departments[fid]:
                schedule_errors['faculty-department-mismatch'] += 1
            faculty_counts[tid, fid] += 1
            periods = meetings(sec)
            if periods and (rid not in rooms or rooms[rid]['room_Type'] not in ['Lab', 'Lecture'] or (sec['labBearing'] and rooms[rid]['room_Type'] != 'Lab')):
                schedule_errors['invalid-teaching-room'] += 1
            resources = [('faculty', fid)] + ([('room', rid)] if rid is not None else [])
            for sid in sec['studentIds']:
                identity = (sid, tid, key)
                if identity in roster:
                    schedule_errors['duplicate-student-section-assignment'] += 1
                roster.add(identity)
                resources.append(('student', sid))
            for kind, resource in resources:
                scope = (tid, kind, resource)
                if overlap(periods, occupied[scope]):
                    schedule_errors[kind + '-meeting-conflict'] += 1
                occupied[scope].extend(periods)
        for (tid, fid), count in faculty_counts.items():
            if fid in faculty and count > (2 if faculty[fid]['faculty_Type'] == 'Full-Time' else 1):
                schedule_errors['faculty-workload-limit'] += 1
        expected_roster = {(a['studentId'], a['termId'], a['courseKey']) for a in candidate['attempts'] if a['termCode'] in scheduled_terms}
        if roster != expected_roster:
            schedule_errors['scheduled-term-roster-coverage'] += 1
    blockers = []
    if errors:
        blockers.append('structural-or-prerequisite-validation-failed')
    if gated:
        blockers.append('source-rules-require-resolution-or-scoped-evidence')
    if schedule_errors or schedule is None or schedule.get('status') != 'candidate-scheduled' or scheduled_terms != set(candidate['rosters']):
        blockers.append('complete-student-faculty-room-schedule-not-verified')
    if schedule and schedule.get('candidateSHA256') != hashlib.sha256(json.dumps(candidate, sort_keys=True).encode()).hexdigest():
        blockers.append('schedule-was-built-from-different-candidate')
    # Must be replaced with a real evaluator receipt before any activation tool
    # is added. Synthetic passing curriculum fixtures cannot satisfy this gate.
    blockers.append('actual-rebuilt-student-degree-evaluations-not-verified')
    blockers.append('portal-and-installation-seed-integration-not-verified')
    return {'status': 'blocked-from-activation' if blockers else 'ready-for-restoration-rehearsal',
            'reviewAsOf': '2026-11-07', 'policy': candidate['policy'],
            'undergraduateStudents': len(students), 'graduateStudentsProtected': len(b.graduate_ids),
            'majorProgramsRepresented': len({s['major'] for s in students.values()}),
            'minorProgramsRepresented': len({s['minor'] for s in students.values() if s['minor']}),
            'replacementAttempts': len(candidate['attempts']), 'courseVersionsUsed': len({a['courseKey'] for a in candidate['attempts']}),
            'underloadGroups': len(candidate['underloadGroups']), 'structuralErrors': dict(sorted(errors.items())),
            'knownPrerequisiteFailuresByCourse': dict(sorted(readiness_failures.items())),
            'independentlyCheckedScheduleTerms': sorted(scheduled_terms),
            'scheduleValidationErrors': dict(sorted(schedule_errors.items())),
            'scheduleSolverFailures': schedule.get('failures', []) if schedule else [],
            'sourceRuleReviewCourses': len(gated), 'sourceRuleReviewAttempts': sum(gated.values()),
            'sourceRuleReviewCountsByCourse': dict(sorted(gated.items())),
            'formerNameMeetingTemplatesReviewed': len(b.former_templates),
            'formerNameConflictsHeldForReview': b.rejected_former_templates,
            'fictionalElectiveRepairCount': len(candidate.get('fictionalElectiveRepairs', [])),
            'explicitSourceInterpretations': b.interpretations,
            'activationBlockers': blockers, 'workingDatabaseChanged': False,
            'privateStudentIdentifiersPublished': False,
            'candidateSHA256': hashlib.sha256(json.dumps(candidate, sort_keys=True).encode()).hexdigest()}


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--source', required=True)
    parser.add_argument('--candidate', required=True)
    parser.add_argument('--schedule')
    args = parser.parse_args()
    for p in [args.source, args.candidate, args.schedule]:
        if p and not str(Path(p).resolve()).startswith('/private/tmp/'):
            raise SystemExit('Private input paths required')
    source = json.loads(Path(args.source).read_text())
    candidate = json.loads(Path(args.candidate).read_text())
    schedule = json.loads(Path(args.schedule).read_text()) if args.schedule else None
    report = audit(source, candidate, schedule)
    report['candidateFileSHA256'] = hashlib.sha256(Path(args.candidate).read_bytes()).hexdigest()
    if args.schedule:
        report['scheduleFileSHA256'] = hashlib.sha256(Path(args.schedule).read_bytes()).hexdigest()
    output = ROOT / 'database/staging/academic-alignment/rebuild-candidate-audit.json'
    output.write_text(json.dumps(report, indent=2) + '\n')
    print(json.dumps({k: report[k] for k in ['status', 'replacementAttempts', 'structuralErrors', 'sourceRuleReviewCourses', 'activationBlockers']}))
