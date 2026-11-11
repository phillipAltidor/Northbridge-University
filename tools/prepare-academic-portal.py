#!/usr/bin/env python3
"""Materialize an audited fictional candidate for an isolated portal rehearsal.

Local section keys remain globally unique. Official term-scoped CRNs and exact
meeting rows are stored separately, so neither borrowed CRNs nor Cartesian
day/period combinations can masquerade as an official offering.
"""
import argparse
import collections
import copy
import hashlib
import importlib.util
import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'database/generation'))
from rebuild_oldwestbury import MAJORS, MINORS, canonical, Builder
from academic_meetings import meetings


def digest(value):
    return hashlib.sha256(json.dumps(value, sort_keys=True).encode()).hexdigest()


def prepare(source, candidate, schedule):
    spec = importlib.util.spec_from_file_location('rebuild_audit', ROOT / 'tools/audit-academic-rebuild.py')
    module = importlib.util.module_from_spec(spec); spec.loader.exec_module(module)
    audit = module.audit(source, candidate, schedule)
    if audit['structuralErrors'] or audit['scheduleValidationErrors'] or schedule['status'] != 'candidate-scheduled' or 'complete-student-faculty-room-schedule-not-verified' in audit['activationBlockers']:
        raise ValueError('A complete independently audited schedule is required for portal preparation')
    b = Builder(source); b.enable_source_proof_planning()
    if 'northbridgeProjectPolicies' in candidate:
        b.enable_project_policies()
    people = {p['studentId']: p for p in candidate['students']}
    ug = set(people)
    old_ug_sections = {s['CRN'] for s in source['Course_Section'] if b.oldcourses[s['course_ID']]['course_Type'] == 'Undergraduate'}
    tables = {}
    for table in ['Course', 'Major', 'Minor', 'Student', 'Undergraduate', 'Full_Time_Undergraduate', 'Part_Time_Undergraduate', 'Time_Slot', 'Time_Slot_Day', 'Time_Slot_Period', 'Period']:
        tables[table] = copy.deepcopy(source[table])
    for table in ['Course_Section', 'Faculty_History', 'Course_Section_Update_Log']:
        tables[table] = [copy.deepcopy(r) for r in source[table] if r['CRN'] not in old_ug_sections]
    for table in ['Enrollment', 'Student_History', 'Attendance', 'Student_Major', 'Student_Minor']:
        tables[table] = [copy.deepcopy(r) for r in source[table] if r['student_ID'] not in ug]
    # Saved schedule snapshots must be rebuilt after replacing section identities.
    tables['Master_Schedule'] = []
    major_ids = {name: 101 + i for i, name in enumerate(MAJORS)}
    minor_ids = {name: 101 + i for i, name in enumerate(MINORS)}
    major_depts = [1, 1, 2, 2, 3, 4, 5, 6, 7, 8, 9, 10]
    minor_depts = [1, 2, 2, 2, 3, 4, 5, 6, 7, 10, 9, 10]
    if set(major_ids.values()) & {r['major_ID'] for r in source['Major']} or set(minor_ids.values()) & {r['minor_ID'] for r in source['Minor']}:
        raise ValueError('Reserved program identifiers already exist')
    tables['Major'] += [{'major_ID': major_ids[n], 'major_Name': n, 'dept_ID': major_depts[i]} for i, n in enumerate(MAJORS)]
    tables['Minor'] += [{'minor_ID': minor_ids[n], 'minor_Name': n, 'dept_ID': minor_depts[i]} for i, n in enumerate(MINORS)]
    departments = {row['major_ID']: row['dept_ID'] for row in tables['Major']}
    completed_credits = collections.Counter()
    for a in candidate['attempts']:
        if a['status'] == 'completed':
            completed_credits[a['studentId']] += candidate['courses'][a['courseKey']]['credits']
    for row in tables['Student']:
        if row['student_ID'] in ug:
            sid = row['student_ID']; row['major_ID'] = major_ids[people[sid]['major']]
            credit = completed_credits[sid]; row['student_Year'] = 4 if credit >= 88 else 3 if credit >= 57 else 2 if credit >= 32 else 1
    for row in tables['Undergraduate']:
        row['dept_ID'] = departments[major_ids[people[row['student_ID']]['major']]]
    for table in ['Full_Time_Undergraduate', 'Part_Time_Undergraduate']:
        for row in tables[table]:
            sid = row['student_ID']; row['credits_Earned'] = completed_credits[sid]
            if people[sid]['activeOriginalCohort']:
                credits = completed_credits[sid]
                row['Status'] = 'Senior' if credits >= 88 else 'Junior' if credits >= 57 else 'Sophomore' if credits >= 32 else 'Freshman'
    for sid, person in people.items():
        tables['Student_Major'].append({'student_ID': sid, 'major_ID': major_ids[person['major']], 'date_Of_Choice': '2020-08-01'})
        if person['minor']:
            tables['Student_Minor'].append({'student_ID': sid, 'minor_ID': minor_ids[person['minor']], 'date_Of_Choice': '2020-08-01'})
    course_definitions = copy.deepcopy(candidate['courses'])
    # Include the remaining described undergraduate catalog courses, not only
    # those taken in this candidate. Unknown credit definitions remain held.
    held_courses = []
    for code, metadata in b.meta.items():
        if int(re.search(r'\d+', code)[0]) >= 6000 or code in course_definitions:
            continue
        if metadata['creditMinimum'] is None:
            held_courses.append(code)
            continue
        b.add(code); course_definitions[code] = copy.deepcopy(b.courses[code])
    course_ids = {key: key.replace('@2020-2022', 'H2020') for key in course_definitions}
    original_course_ids = {c['course_ID'] for c in source['Course']}
    if original_course_ids & set(course_ids.values()) or len(set(course_ids.values())) != len(course_ids):
        raise ValueError('New course identity collides with protected legacy meanings')
    academic_courses = []
    for key, course in course_definitions.items():
        credit = course['credits']
        if credit != int(credit):
            raise ValueError('Legacy portal integer credits need a schema migration before materializing ' + key)
        local = course_ids[key]
        tables['Course'].append({'course_ID': local, 'dept_ID': course['departmentId'], 'course_Name': course['title'], 'course_Credits': int(credit), 'course_DSC': course['description'][:500], 'course_Type': 'Undergraduate'})
        academic_courses.append({'course_ID': local, 'version_Key': key, 'definition_JSON': course,
                                 'rule_JSON': b.rules.get(key, {'type': 'review-required'}),
                                 'properties_JSON': candidate.get('reviewedDegreeProperties', {}).get('courseProperties', {}).get(key)})
    terms = {a['termId']: a['termCode'] for a in candidate['attempts']}
    attempts = {(a['studentId'], a['termId'], a['courseKey']): a for a in candidate['attempts']}
    source_rows, meeting_rows = [], []
    start_crn = max(r['CRN'] for r in source['Course_Section']) + 10000
    start_slot = max(r['time_Slot_ID'] for r in source['Time_Slot']) + 10000
    for serial, sec in enumerate(schedule['sections']):
        crn, slot = start_crn + serial, start_slot + serial
        key = sec['courseKey']; code = course_ids[key]; tid = sec['termId']
        pattern = meetings(sec)
        days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']
        def clock(minute):
            return f'{minute // 60:02}:{minute % 60:02}:00'
        day_label = '/'.join(dict.fromkeys(days[m[0]] for m in pattern)) or 'TBA'
        time_label = '; '.join(dict.fromkeys(days[m[0]] + ' ' + clock(m[1])[:5] + '-' + clock(m[2])[:5] for m in pattern)) or 'TBA / untimed'
        tables['Time_Slot'].append({'time_Slot_ID': slot, 'time_Slot_Day': day_label[:100], 'time_Slot_Period': time_label[:100]})
        # Do not add lossy day×period mappings for exact source meetings.
        tables['Course_Section'].append({'CRN': crn, 'course_ID': code, 'section_No': sec['deliveryGroup'], 'faculty_ID': sec['facultyId'], 'time_Slot_ID': slot, 'room_ID': sec['roomId'], 'semester_ID': tid, 'available_Seats': sec['capacity'] - len(sec['studentIds'])})
        source_rows.append({'CRN': crn, 'official_CRN': sec['officialCRN'], 'source_Section': sec['sourceSectionLabel'], 'source_Term': sec['term'], 'delivery_Group': sec['deliveryGroup'], 'source_JSON': {k: v for k, v in sec.items() if k != 'studentIds'}})
        for number, (day, begin, end, lo, hi) in enumerate(pattern):
            import datetime as dt
            meeting_rows.append({'CRN': crn, 'ordinal': number, 'day_Of_Week': days[day], 'start_Time': clock(begin), 'end_Time': clock(end), 'start_Date': dt.date.fromordinal(lo).isoformat(), 'end_Date': dt.date.fromordinal(hi).isoformat()})
        if tid not in [1, 2]:
            tables['Faculty_History'].append({'faculty_ID': sec['facultyId'], 'CRN': crn, 'course_ID': code, 'semester_ID': tid})
        for sid in sec['studentIds']:
            attempt = attempts[sid, tid, key]
            base = {'student_ID': sid, 'CRN': crn, 'semester_ID': tid, 'Grade': attempt['grade']}
            if attempt['status'] == 'completed':
                tables['Student_History'].append(base | {'course_ID': code})
            # Existing portal reports and seat triggers retain a registration
            # row for completed work as well as the completed-history record.
            tables['Enrollment'].append(base)
    return {'status': 'prepared-for-isolated-portal-rehearsal-not-activation', 'candidateSHA256': digest(candidate),
            'scheduleSHA256': digest(schedule), 'sourceSnapshotSHA256': digest(source),
            'replacementTables': tables, 'academicCourses': academic_courses,
            'academicPrograms': b.programs, 'heldUnknownCreditCourses': held_courses,
            'academicSections': source_rows, 'academicMeetings': meeting_rows,
            'academicStudents': candidate['students'], 'sourceAudit': audit,
            'originalPeopleAndAccountsPreserved': True, 'graduateAcademicRowsPreserved': True,
            'historicalAttendancePolicy': 'Old UG attendance is archived with the original snapshot; no attendance is fabricated for converted sections.',
            'workingDatabaseChanged': False}


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    for name in ['source', 'candidate', 'schedule', 'output']:
        parser.add_argument('--' + name, required=True)
    args = parser.parse_args()
    for value in vars(args).values():
        if not str(Path(value).resolve()).startswith('/private/tmp/'):
            raise SystemExit('Private temporary paths required')
    if Path(args.output).exists():
        raise SystemExit('Refusing to overwrite a portal rehearsal plan')
    values = [json.loads(Path(getattr(args, name)).read_text()) for name in ['source', 'candidate', 'schedule']]
    result = prepare(*values)
    Path(args.output).write_text(json.dumps(result, indent=2) + '\n'); Path(args.output).chmod(0o600)
    print(json.dumps({'status': result['status'], 'students': len(result['academicStudents']), 'sections': len(result['academicSections']), 'exactMeetingRows': len(result['academicMeetings']), 'workingDatabaseChanged': False}))
