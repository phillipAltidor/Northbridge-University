"""Generate a staged Fall 2022 rebuild; never connect to or modify MySQL."""
import argparse
import copy
import hashlib
import json
import math
from collections import Counter, defaultdict
from datetime import date
from pathlib import Path

from ortools.sat.python import cp_model


def require(condition, message):
    if not condition:
        raise RuntimeError(message)


def stable(value):
    return int(hashlib.sha256(str(value).encode()).hexdigest()[:8], 16)


def build(source):
    tables = copy.deepcopy(source)
    index = lambda table, key: {row[key]: row for row in tables[table]}
    courses = index('Course', 'course_ID')
    users = index('User', 'user_ID')
    students = index('Student', 'student_ID')
    rooms = index('Room', 'room_ID')
    faculty = index('Faculty', 'faculty_ID')
    modes = {}
    for table in ['Full_Time_Undergraduate', 'Part_Time_Undergraduate', 'Full_Time_Graduate', 'Part_Time_Graduate']:
        for row in tables[table]:
            modes[row['student_ID']] = table
    prerequisites = defaultdict(set)
    for row in tables['Course_Prerequisite']:
        prerequisites[row['course_ID']].add(row['prerequisite_course_ID'])

    def ancestors(course):
        result = set()
        for prior in prerequisites[course]:
            result.add(prior)
            result.update(ancestors(prior))
        return result

    ancestors_by_course = {course: ancestors(course) for course in courses}
    def depth(course):
        return max((depth(parent) + 1 for parent in prerequisites[course]), default=0)
    old_target = [row for row in tables['Student_History'] if row['semester_ID'] == 3]
    continuing = {row['student_ID'] for row in old_target}
    require(len(continuing) == 200, 'Unexpected initial Fall 2022 roster; inspect before rebuilding.')
    require(len(students) == 1600, 'Expected the original 1,600-student source population.')
    new_ids = {sid: 700001 + n for n, sid in enumerate(sorted(set(students) - continuing))}
    require(not set(new_ids.values()) & set(users), 'Generated IDs overlap existing users.')
    generated = set(new_ids.values())
    origin = {new: sid for sid, new in new_ids.items()}
    cohort = sorted(continuing | generated)
    require(len(cohort) == 1600, 'Fall 2022 population must be 1,600.')
    sections_index = index('Course_Section', 'CRN')
    main = defaultdict(dict)
    for row in old_target:
        main[row['student_ID']][row['course_ID']] = row['Grade']
    for row in tables['Enrollment']:
        if row['semester_ID'] == 1 and row['student_ID'] in new_ids:
            course = sections_index[row['CRN']]['course_ID']
            main[new_ids[row['student_ID']]][course] = ['A', 'A-', 'B+', 'B', 'B-', 'C+'][stable((new_ids[row['student_ID']], course)) % 6]

    # Drop only tiny newly generated offerings, moving their students to viable electives.
    while True:
        sizes = Counter(course for records in main.values() for course in records)
        tiny = sorted(course for course, size in sizes.items() if size < 5)
        if not tiny:
            break
        course = tiny[0]
        for sid in sorted(sid for sid, records in main.items() if course in records):
            require(sid in generated, 'A preserved historical course has an under-minimum roster.')
            other = set(main[sid]) - {course}
            candidates = [c for c, size in sizes.items() if size >= 5 and c not in other and c != course
                          and courses[c]['course_Type'] == courses[course]['course_Type']
                          and not ancestors_by_course[c] & other
                          and all(c not in ancestors_by_course[current] for current in other)]
            require(candidates, 'No valid replacement for a tiny offering.')
            candidates.sort(key=lambda c: (courses[c]['dept_ID'] != courses[course]['dept_ID'], len(ancestors_by_course[c]), sizes[c], c))
            del main[sid][course]
            main[sid][candidates[0]] = 'B+'

    # Balance generated electives against the existing department teaching limits.
    capacities = Counter()
    faculty_types = index('Faculty', 'faculty_ID')
    for row in tables['Faculty_Department']:
        capacities[row['dept_ID']] += 2 if faculty_types[row['faculty_ID']]['faculty_Type'] == 'Full-Time' else 1
    for _ in range(5000):
        sizes = Counter(course for records in main.values() for course in records)
        demand = Counter()
        for course, size in sizes.items():
            demand[courses[course]['dept_ID']] += math.ceil(size / 10)
        overloaded = [dept for dept in demand if demand[dept] > capacities[dept]]
        if not overloaded:
            break
        dept = overloaded[0]
        options = []
        for sid in sorted(generated):
            for course in main[sid]:
                if courses[course]['dept_ID'] != dept or sizes[course] <= 5:
                    continue
                other = set(main[sid]) - {course}
                for candidate, size in sizes.items():
                    target_dept = courses[candidate]['dept_ID']
                    if target_dept == dept or demand[target_dept] >= capacities[target_dept] or candidate in main[sid]:
                        continue
                    if courses[candidate]['course_Type'] != courses[course]['course_Type'] or ancestors_by_course[candidate] & other or any(candidate in ancestors_by_course[c] for c in other):
                        continue
                    options.append((sizes[course] % 10 != 1, size % 10 == 0, len(ancestors_by_course[candidate]), sid, course, candidate))
        require(options, 'No valid elective allocation within faculty capacity.')
        _, _, _, sid, course, candidate = min(options)
        del main[sid][course]
        main[sid][candidate] = 'B+'
    else:
        raise RuntimeError('Department allocation did not converge.')

    # Only prerequisites are added before Fall 2022; full earlier cohort histories remain a later step.
    prior = defaultdict(dict)
    ordered_prior = [11, 12, 13, 14]
    term_dates = {11: '2020-09-01', 12: '2021-02-01', 13: '2021-09-01', 14: '2022-02-01', 3: '2022-09-01'}

    def record_prior(sid, course, deadline):
        require(deadline >= 0, 'Prerequisite chain exceeds the available background semesters.')
        require(course not in main[sid], 'A current course cannot also be prerequisite history.')
        old = prior[sid].get(course)
        if old is not None and old <= deadline:
            return
        prior[sid][course] = deadline
        for parent in prerequisites[course]:
            record_prior(sid, parent, deadline - 1)

    for sid in sorted(generated):
        for course in main[sid]:
            for parent in prerequisites[course]:
                record_prior(sid, parent, 3)

    # Ensure small prerequisite cohorts still produce valid sections of at least five.
    for _ in range(2000):
        sizes = Counter((ordered_prior[term], course) for records in prior.values() for course, term in records.items())
        tiny = sorted((term, course, size) for (term, course), size in sizes.items() if size < 5)
        if not tiny:
            break
        term_id, course, size = tiny[0]
        term = ordered_prior.index(term_id)
        require(depth(course) <= term, 'Prerequisite offering cannot fit its background semester.')
        candidates = []
        for sid in sorted(generated):
            original = students[origin[sid]]
            dob = date.fromisoformat(users[origin[sid]]['DOB']).replace(year=date.fromisoformat(users[origin[sid]]['DOB']).year - 4)
            if original['student_Type'] != courses[course]['course_Type'] or course in main[sid] or course in prior[sid]:
                continue
            if ancestors_by_course[course] & set(main[sid]):
                continue
            if dob.isoformat() > str(int(term_dates[term_id][:4]) - 18) + term_dates[term_id][4:]:
                continue
            mode = modes[origin[sid]]
            maximum = 4 if mode == 'Full_Time_Undergraduate' else 3 if mode == 'Full_Time_Graduate' else 2
            if sum(t == term for t in prior[sid].values()) >= maximum:
                continue
            if any(course in ancestors_by_course[c] and t <= term for c, t in prior[sid].items()):
                continue
            candidates.append(sid)
        require(candidates, 'Cannot repair a prerequisite roster without violating chronology or study load.')
        sid = min(candidates, key=lambda sid: (courses[course]['dept_ID'] != next(m['dept_ID'] for m in tables['Major'] if m['major_ID'] == students[origin[sid]]['major_ID']), len(prior[sid]), sid))
        record_prior(sid, course, term)
    else:
        raise RuntimeError('Prerequisite roster repair did not converge.')

    new_profile_tables = ['User', 'Login', 'Student', 'Undergraduate', 'Graduate_Student',
                          'Full_Time_Undergraduate', 'Part_Time_Undergraduate', 'Full_Time_Graduate',
                          'Part_Time_Graduate', 'Student_Major']
    source_profiles = {table: {row.get('student_ID', row.get('user_ID')): row for row in source[table]} for table in new_profile_tables}
    cohort_records = []
    for sid in sorted(generated):
        original_id = origin[sid]
        for table in new_profile_tables:
            original = source_profiles[table].get(original_id)
            if original is None:
                continue
            row = copy.deepcopy(original)
            key = 'user_ID' if table in ['User', 'Login'] else 'student_ID'
            row[key] = sid
            if table == 'User':
                row['last_Name'] = source['User'][(stable(sid) % len(source['User']))]['last_Name']
                birth = date.fromisoformat(row['DOB'])
                try:
                    row['DOB'] = birth.replace(year=birth.year - 4).isoformat()
                except ValueError:
                    row['DOB'] = birth.replace(year=birth.year - 4, day=28).isoformat()
                first_term = min([term_dates[ordered_prior[t]] for t in prior[sid].values()] + ['2022-09-01'])
                latest_birth = str(int(first_term[:4]) - 18) + first_term[4:]
                if row['DOB'] > latest_birth:
                    adjusted = date.fromisoformat(row['DOB'])
                    try:
                        row['DOB'] = adjusted.replace(year=int(first_term[:4]) - 19).isoformat()
                    except ValueError:
                        row['DOB'] = adjusted.replace(year=int(first_term[:4]) - 19, day=28).isoformat()
                row['street'] = f'{100 + sid % 8900} University Avenue'
            elif table == 'Login':
                row.update(user_Email=f'alumni{sid}@northbridge.edu', user_Password=None, no_Of_Tries=3, lock_var=1)
            elif table in ['Full_Time_Undergraduate', 'Part_Time_Undergraduate']:
                row['Status'] = 'Former Student'
                row['credits_Earned'] = 4 * (len(prior[sid]) + len(main[sid]))
            elif table in ['Full_Time_Graduate', 'Part_Time_Graduate']:
                row['credits_Earned'] = 4 * (len(prior[sid]) + len(main[sid]))
                row['Thesis'] = None
            elif table == 'Student_Major':
                row['date_Of_Choice'] = min([term_dates[ordered_prior[t]] for t in prior[sid].values()] + ['2022-09-01'])
            tables[table].append(row)
        cohort_records.append({'student_ID': sid, 'source_study_plan_ID': original_id, 'population_semester': 'Fall 2022',
                               'first_recorded_semester': min(prior[sid].values(), default=4),
                               'study_mode': modes[original_id], 'registered': bool(main[sid])})

    new_rosters = defaultdict(lambda: defaultdict(dict))
    for sid, records in prior.items():
        for course, term in records.items():
            new_rosters[ordered_prior[term]][course][sid] = ['A', 'A-', 'B+', 'B', 'C+'][stable((sid, course)) % 5]
    for sid, records in main.items():
        for course, grade in records.items():
            new_rosters[3][course][sid] = grade or 'B+'

    faculty_departments = {(row['faculty_ID'], row['dept_ID']): row for row in tables['Faculty_Department']}
    part_faculty = index('Part_Time_Faculty', 'faculty_ID')
    preferred = defaultdict(list)
    for section in sorted(source['Course_Section'], key=lambda row: (row['semester_ID'] != 1, row['CRN'])):
        preferred[section['course_ID']].append(section)
    existing_target = [row for row in source['Course_Section'] if row['semester_ID'] == 3]
    target_keys = {(row['course_ID'], row['section_No']): row for row in existing_target}
    used_crns = {row['CRN'] for row in source['Course_Section']}
    next_crn = max(used_crns) + 1
    tables['Student_History'] = [row for row in tables['Student_History'] if row['semester_ID'] != 3]
    tables['Faculty_History'] = [row for row in tables['Faculty_History'] if row['semester_ID'] != 3]
    tables['Course_Section'] = [row for row in tables['Course_Section'] if row['semester_ID'] != 3]
    summaries = []
    day_map = defaultdict(list)
    for row in tables['Time_Slot_Day']:
        day_map[row['time_Slot_ID']].append(row['day_ID'])
    periods = index('Period', 'period_ID')
    slot_period = {row['time_Slot_ID']: periods[row['period_ID']] for row in tables['Time_Slot_Period']}
    slots = index('Time_Slot', 'time_Slot_ID')
    student_index = index('Student', 'student_ID')

    for term_id in ordered_prior + [3]:
        existing = [row for row in tables['Course_Section'] if row['semester_ID'] == term_id]
        loads = Counter(row['faculty_ID'] for row in existing)
        room_loads = Counter(row['room_ID'] for row in existing)
        course_numbers = Counter()
        for row in existing:
            course_numbers[row['course_ID']] = max(course_numbers[row['course_ID']], row['section_No'])
        planned = []
        rosters = {}
        for course, people in sorted(new_rosters[term_id].items()):
            count = math.ceil(len(people) / 10)
            require(len(people) >= 5 * count, 'Invalid section size.')
            roster = sorted(people, key=lambda sid: (sid not in continuing, origin.get(sid, sid), sid))
            sizes = [len(roster) // count + (n < len(roster) % count) for n in range(count)]
            offset = 0
            for n, size in enumerate(sizes):
                number = course_numbers[course] + n + 1
                old = target_keys.get((course, number)) if term_id == 3 else None
                templates = preferred[course] or [row for row in source['Course_Section'] if courses[row['course_ID']]['dept_ID'] == courses[course]['dept_ID'] and rooms[row['room_ID']]['room_Type'] == 'Lecture']
                require(templates, 'No teaching-room template for ' + course)
                template = old or templates[min(n, len(templates) - 1)]
                room_type = rooms[template['room_ID']]['room_Type']
                dept = courses[course]['dept_ID']
                eligible = []
                for fid, teacher in faculty.items():
                    if (fid, dept) not in faculty_departments:
                        continue
                    maximum = 2 if teacher['faculty_Type'] == 'Full-Time' else 1
                    if loads[fid] >= maximum:
                        continue
                    birth = date.fromisoformat(users[fid]['DOB'])
                    minimum_hire = birth.replace(year=birth.year + 22).isoformat()
                    if minimum_hire > term_dates[term_id]:
                        continue
                    appointment = faculty_departments[(fid, dept)]['date_Of_Appointment']
                    hire = part_faculty[fid]['DOA'] if fid in part_faculty else appointment
                    eligible.append((max(appointment, hire) > term_dates[term_id], fid != template['faculty_ID'], loads[fid], fid, minimum_hire))
                require(eligible, f'Insufficient faculty capacity in department {dept}, semester {term_id}; planned {len(planned)}, course {course}.')
                _, _, _, fid, minimum_hire = min(eligible)
                for record, field in [(faculty_departments[(fid, dept)], 'date_Of_Appointment')] + ([(part_faculty[fid], 'DOA')] if fid in part_faculty else []):
                    if record[field] > term_dates[term_id]:
                        record[field] = max(minimum_hire, term_dates[term_id][:4] + '-08-15' if term_dates[term_id][5:7] == '09' else str(int(term_dates[term_id][:4]) - 1) + '-08-15')
                loads[fid] += 1
                building = rooms[template['room_ID']]['Bldg_ID']
                candidates = [row for row in rooms.values() if row['room_Type'] == room_type and row['Bldg_ID'] == building]
                room = min(candidates, key=lambda r: (room_loads[r['room_ID']], r['room_ID'] != template['room_ID'], r['room_ID']))['room_ID']
                room_loads[room] += 1
                if old:
                    crn = old['CRN']
                else:
                    crn = next_crn
                    next_crn += 1
                section = {'CRN': crn, 'course_ID': course, 'section_No': number, 'faculty_ID': fid,
                           'time_Slot_ID': template['time_Slot_ID'], 'room_ID': room, 'semester_ID': term_id, 'available_Seats': 10 - size}
                planned.append(section)
                rosters[crn] = {sid: people[sid] for sid in roster[offset:offset + size]}
                offset += size

        # Shared students, instructors, and rooms must occupy different day/time blocks.
        resource_sections = defaultdict(list)
        for section in existing + planned:
            crn = section['CRN']
            resource_sections['faculty', section['faculty_ID']].append(crn)
            resource_sections['room', section['room_ID']].append(crn)
        fixed_rosters = defaultdict(set)
        for row in tables['Student_History']:
            if row['semester_ID'] == term_id:
                fixed_rosters[row['CRN']].add(row['student_ID'])
        for crn, people in fixed_rosters.items():
            for sid in people:
                resource_sections['student', sid].append(crn)
        for crn, people in rosters.items():
            for sid in people:
                resource_sections['student', sid].append(crn)
        model = cp_model.CpModel()
        variables = {row['CRN']: model.NewIntVar(0, 11, f'section_{row["CRN"]}') for row in planned}
        fixed = {row['CRN']: row for row in existing}
        seen = set()
        starts = ['08:00:00', '11:30:00', '15:00:00', '18:30:00']
        objective = []
        for section in planned:
            crn = section['CRN']
            duration = 90 if courses[section['course_ID']]['course_Credits'] == 3 else 180 if rooms[section['room_ID']]['room_Type'] == 'Lab' else 120
            compatible = {}
            for slot_id in range(37, 73):
                if slot_id not in slots:
                    continue
                p = slot_period[slot_id]
                hours = lambda value: int(value[:2]) * 60 + int(value[3:5])
                if hours(p['end_Time']) - hours(p['start_Time']) != duration:
                    continue
                block = starts.index(p['start_Time'])
                pattern = ['Monday, Wednesday', 'Tuesday, Thursday', 'Friday'].index(slots[slot_id]['time_Slot_Day'])
                compatible[pattern * 4 + block] = slot_id
            require(len(compatible) == 12, 'Missing candidate time slots.')
            section['_candidates'] = compatible
            preferred_slot = section['time_Slot_ID']
            if preferred_slot in compatible.values():
                color = next(c for c, slot in compatible.items() if slot == preferred_slot)
                changed = model.NewBoolVar(f'changed_{crn}')
                model.Add(variables[crn] != color).OnlyEnforceIf(changed)
                model.Add(variables[crn] == color).OnlyEnforceIf(changed.Not())
                objective.append(changed)
        for crns in resource_sections.values():
            for i, a in enumerate(crns):
                for b in crns[i + 1:]:
                    pair = tuple(sorted((a, b)))
                    if pair in seen:
                        continue
                    seen.add(pair)
                    if a in variables and b in variables:
                        model.Add(variables[a] != variables[b])
                    elif a in variables or b in variables:
                        new, old = (a, b) if a in variables else (b, a)
                        old_slot = fixed[old]['time_Slot_ID']
                        section = next(row for row in planned if row['CRN'] == new)
                        for color, slot in section['_candidates'].items():
                            if set(day_map[slot]) & set(day_map[old_slot]) and slot_period[slot]['start_Time'] < slot_period[old_slot]['end_Time'] and slot_period[old_slot]['start_Time'] < slot_period[slot]['end_Time']:
                                model.Add(variables[new] != color)
        if objective:
            model.Minimize(sum(objective))
        solver = cp_model.CpSolver()
        solver.parameters.max_time_in_seconds = 40
        solver.parameters.num_search_workers = 4
        solver.parameters.random_seed = 2022
        result = solver.Solve(model)
        require(result in [cp_model.OPTIMAL, cp_model.FEASIBLE], f'No valid meeting plan for semester {term_id}.')
        for section in planned:
            section['time_Slot_ID'] = section.pop('_candidates')[solver.Value(variables[section['CRN']])]
            tables['Course_Section'].append(section)
            tables['Faculty_History'].append({key: section[key] for key in ['faculty_ID', 'CRN', 'course_ID', 'semester_ID']})
            for sid, grade in rosters[section['CRN']].items():
                tables['Student_History'].append({'student_ID': sid, 'CRN': section['CRN'], 'course_ID': section['course_ID'], 'semester_ID': term_id, 'Grade': grade})
        summaries.append({'semester_ID': term_id, 'generated_sections': len(planned), 'courses': len(new_rosters[term_id]),
                          'registrations': sum(map(len, rosters.values())), 'solver_status': solver.StatusName(result)})

    registered = {sid for sid, records in main.items() if records}
    require({row['student_ID'] for row in tables['Student_History'] if row['semester_ID'] == 3} == registered, 'Roster population mismatch.')
    require(all(row['student_ID'] in student_index for row in tables['Student_History']), 'Missing student subtype.')
    for sid in generated:
        mode = modes[origin[sid]]
        load = 4 * len(main[sid])
        if load:
            require(12 <= load <= 16 if mode == 'Full_Time_Undergraduate' else load == 12 if mode == 'Full_Time_Graduate' else 4 <= load <= 8,
                    f'Invalid Fall 2022 credit load for {sid}.')
    changes = {table: rows for table, rows in tables.items() if rows != source[table]}
    return {'target_semester': 'Fall 2022', 'population': len(cohort), 'registered_students': len(registered),
            'cohort': cohort_records, 'continuing_students': sorted(continuing), 'summaries': summaries,
            'replacement_tables': changes, 'source_digest': hashlib.sha256(json.dumps(source, sort_keys=True, separators=(',', ':')).encode()).hexdigest()}


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--source', required=True)
    parser.add_argument('--output', required=True)
    args = parser.parse_args()
    source = json.loads(Path(args.source).read_text())
    plan = build(source)
    Path(args.output).write_text(json.dumps(plan))
    Path(args.output).chmod(0o600)
    print(json.dumps({key: plan[key] for key in ['target_semester', 'population', 'registered_students', 'summaries']}, indent=2))
