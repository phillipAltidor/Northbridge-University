"""Plan semester-by-semester cohort progression without accessing the database."""
import argparse
import copy
import json
import math
from collections import Counter, defaultdict
from datetime import date
from pathlib import Path

from ortools.sat.python import cp_model
from rebuild_fall_2022 import require, stable

MODES = {'Full_Time_Undergraduate': 800, 'Part_Time_Undergraduate': 400,
         'Full_Time_Graduate': 275, 'Part_Time_Graduate': 125}
TERMS = [4, 5, 6, 7, 8, 9, 10, 2]
PASSING = {'A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C'}


def term_key(name):
    season, year = name.split()
    return int(year) * 2 + (season == 'Fall')


def shifted(value, years):
    parsed = date.fromisoformat(value)
    try:
        return parsed.replace(year=parsed.year + years).isoformat()
    except ValueError:
        return parsed.replace(year=parsed.year + years, day=28).isoformat()


def schedule(tables, term, main):
    courses = {r['course_ID']: r for r in tables['Course']}
    users = {r['user_ID']: r for r in tables['User']}
    teachers = {r['faculty_ID']: r for r in tables['Faculty']}
    departments = {(r['faculty_ID'], r['dept_ID']): r for r in tables['Faculty_Department']}
    part = {r['faculty_ID']: r for r in tables['Part_Time_Faculty']}
    rooms = {r['room_ID']: r for r in tables['Room']}
    name = next(r['semester_Name'] for r in tables['Semester'] if r['semester_ID'] == term)
    season, year = name.split()
    start = year + ('-09-01' if season == 'Fall' else '-02-01')
    old_sections = [r for r in tables['Course_Section'] if r['semester_ID'] == term]
    old_keys = {(r['course_ID'], r['section_No']): r for r in old_sections}
    templates = defaultdict(list)
    for row in sorted(tables['Course_Section'], key=lambda r: (r['semester_ID'] != term, r['semester_ID'] != 1, r['CRN'])):
        templates[row['course_ID']].append(row)
    rosters = defaultdict(dict)
    for sid, records in main.items():
        for course, grade in records.items():
            rosters[course][sid] = grade
    crn = max(r['CRN'] for r in tables['Course_Section']) + 1
    faculty_load = Counter()
    room_load = Counter()
    planned = []
    memberships = {}
    for course, people in sorted(rosters.items()):
        number = math.ceil(len(people) / 10)
        require(len(people) >= 5 * number, 'Offering has too few students: ' + course)
        people_ids = sorted(people)
        offset = 0
        for section_number in range(1, number + 1):
            size = len(people) // number + (section_number <= len(people) % number)
            old = old_keys.get((course, section_number))
            template = old or (templates[course] or [r for r in tables['Course_Section'] if courses[r['course_ID']]['dept_ID'] == courses[course]['dept_ID']])[0]
            dept = courses[course]['dept_ID']
            candidates = []
            for fid, teacher in teachers.items():
                if (fid, dept) not in departments or faculty_load[fid] >= (2 if teacher['faculty_Type'] == 'Full-Time' else 1):
                    continue
                if shifted(users[fid]['DOB'], 22) > start:
                    continue
                hired = max(departments[fid, dept]['date_Of_Appointment'], part.get(fid, {}).get('DOA', '1900-01-01'))
                candidates.append((hired > start, fid != template['faculty_ID'], faculty_load[fid], fid))
            require(candidates, f'Faculty capacity exceeded in {name}, department {dept}.')
            fid = min(candidates)[-1]
            for record, field in [(departments[fid, dept], 'date_Of_Appointment')] + ([(part[fid], 'DOA')] if fid in part else []):
                if record[field] > start:
                    record[field] = start
            faculty_load[fid] += 1
            preferred_room = rooms[template['room_ID']]
            candidates = [r for r in rooms.values() if r['room_Type'] == preferred_room['room_Type'] and room_load[r['room_ID']] < 12]
            require(candidates, 'Room capacity exceeded in ' + name)
            room = min(candidates, key=lambda r: (r['Bldg_ID'] != preferred_room['Bldg_ID'], room_load[r['room_ID']], r['room_ID'] != template['room_ID'], r['room_ID']))['room_ID']
            room_load[room] += 1
            selected_crn = old['CRN'] if old else crn
            if not old:
                crn += 1
            planned.append({'CRN': selected_crn, 'course_ID': course, 'section_No': section_number, 'faculty_ID': fid,
                            'time_Slot_ID': template['time_Slot_ID'], 'room_ID': room, 'semester_ID': term, 'available_Seats': 10 - size})
            memberships[selected_crn] = {sid: people[sid] for sid in people_ids[offset:offset + size]}
            offset += size
    slots = {r['time_Slot_ID']: r for r in tables['Time_Slot']}
    periods = {r['period_ID']: r for r in tables['Period']}
    slot_period = {r['time_Slot_ID']: periods[r['period_ID']] for r in tables['Time_Slot_Period']}
    model = cp_model.CpModel()
    variables = {r['CRN']: model.NewIntVar(0, 11, str(r['CRN'])) for r in planned}
    resources = defaultdict(list)
    objective = []
    for section in planned:
        ident = section['CRN']
        duration = 90 if courses[section['course_ID']]['course_Credits'] == 3 else 180 if rooms[section['room_ID']]['room_Type'] == 'Lab' else 120
        choices = {}
        for slot_id in range(37, 73):
            p = slot_period[slot_id]
            minutes = lambda x: int(x[:2]) * 60 + int(x[3:5])
            if minutes(p['end_Time']) - minutes(p['start_Time']) != duration:
                continue
            block = ['08:00:00', '11:30:00', '15:00:00', '18:30:00'].index(p['start_Time'])
            pattern = ['Monday, Wednesday', 'Tuesday, Thursday', 'Friday'].index(slots[slot_id]['time_Slot_Day'])
            choices[pattern * 4 + block] = slot_id
        require(len(choices) == 12, 'Incomplete time slots.')
        section['_choices'] = choices
        if section['time_Slot_ID'] in choices.values():
            color = next(k for k, v in choices.items() if v == section['time_Slot_ID'])
            changed = model.NewBoolVar('changed_' + str(ident))
            model.Add(variables[ident] != color).OnlyEnforceIf(changed)
            model.Add(variables[ident] == color).OnlyEnforceIf(changed.Not())
            objective.append(changed)
        resources['faculty', section['faculty_ID']].append(ident)
        resources['room', section['room_ID']].append(ident)
        for sid in memberships[ident]:
            resources['student', sid].append(ident)
    for ids in resources.values():
        if len(ids) > 1:
            model.AddAllDifferent([variables[ident] for ident in ids])
    model.Minimize(sum(objective))
    solver = cp_model.CpSolver()
    solver.parameters.max_time_in_seconds = 60
    solver.parameters.num_search_workers = 4
    solver.parameters.random_seed = term
    result = solver.Solve(model)
    require(result in [cp_model.FEASIBLE, cp_model.OPTIMAL], 'Cannot schedule ' + name)
    for section in planned:
        section['time_Slot_ID'] = section.pop('_choices')[solver.Value(variables[section['CRN']])]
    tables['Course_Section'] = [r for r in tables['Course_Section'] if r['semester_ID'] != term] + planned
    tables['Faculty_History'] = [r for r in tables['Faculty_History'] if r['semester_ID'] != term]
    if term != 2:
        tables['Faculty_History'] += [{k: r[k] for k in ['faculty_ID', 'CRN', 'course_ID', 'semester_ID']} for r in planned]
    target_table = 'Enrollment' if term == 2 else 'Student_History'
    tables[target_table] = [r for r in tables[target_table] if r['semester_ID'] != term]
    for section in planned:
        for sid, grade in memberships[section['CRN']].items():
            record = {'student_ID': sid, 'CRN': section['CRN'], 'semester_ID': term, 'Grade': grade}
            if term != 2:
                record['course_ID'] = section['course_ID']
                record = {k: record[k] for k in ['student_ID', 'CRN', 'course_ID', 'semester_ID', 'Grade']}
            tables[target_table].append(record)
    if term == 2:
        removed = {r['CRN'] for r in old_sections}
        tables['Attendance'] = [r for r in tables['Attendance'] if r['CRN'] not in removed]
    return {'semester': name, 'semester_ID': term, 'population': 1600, 'registered_students': sum(bool(v) for v in main.values()),
            'sections': len(planned), 'courses': len(rosters), 'registrations': sum(map(len, main.values())), 'solver': solver.StatusName(result)}


def build(source, manifest, output):
    tables = copy.deepcopy(source)
    courses = {r['course_ID']: r for r in tables['Course']}
    semesters = {r['semester_ID']: r['semester_Name'] for r in tables['Semester']}
    modes = {r['student_ID']: table for table in MODES for r in tables[table]}
    students = {r['student_ID']: r for r in tables['Student']}
    originals = {sid for sid in students if sid not in {r['student_ID'] for r in manifest['generated_profiles']}}
    metadata = {r['student_ID']: {'student_ID': r['student_ID'], 'mode': modes[r['student_ID']],
                'entry_year': 2023 - students[r['student_ID']]['student_Year'], 'origin': r['source_study_plan_ID']}
                for r in manifest['generated_profiles']}
    profiles = {table: {r.get('student_ID', r.get('user_ID')): r for r in tables[table]}
                for table in ['User', 'Login', 'Student', 'Undergraduate', 'Graduate_Student', 'Student_Major'] + list(MODES)}
    majors = {r['major_ID']: r['dept_ID'] for r in tables['Major']}
    prereqs = defaultdict(set)
    for r in tables['Course_Prerequisite']:
        prereqs[r['course_ID']].add(r['prerequisite_course_ID'])
    capacities = Counter()
    faculty = {r['faculty_ID']: r for r in tables['Faculty']}
    for r in tables['Faculty_Department']:
        capacities[r['dept_ID']] += 2 if faculty[r['faculty_ID']]['faculty_Type'] == 'Full-Time' else 1
    templates = {r['course_ID'] for r in tables['Course_Section'] if r['semester_ID'] in [1, 2]}
    next_id = max(800000, max(r['user_ID'] for r in tables['User'])) + 1
    summaries = []
    for term in TERMS:
        before = copy.deepcopy(tables)
        name = semesters[term]
        key = term_key(name)
        year = key // 2 if name.startswith('Fall') else key // 2 - 1
        history = defaultdict(set)
        for r in tables['Student_History']:
            if term_key(semesters[r['semester_ID']]) < key and r['Grade'] in PASSING:
                history[r['student_ID']].add(r['course_ID'])
        main = defaultdict(dict)
        section_index = {r['CRN']: r for r in tables['Course_Section']}
        for r in tables['Enrollment' if term == 2 else 'Student_History']:
            if r['semester_ID'] == term and r['student_ID'] in originals:
                main[r['student_ID']][section_index[r['CRN']]['course_ID']] = r['Grade']
        cohort = set(main)
        selected_generated = set()
        if term == 2:
            cohort = originals
            require(len(cohort) == 1600, 'Unexpected current student population.')
        else:
            for mode, desired in MODES.items():
                already = sum(modes[sid] == mode for sid in cohort)
                duration = 4 if mode == 'Full_Time_Undergraduate' else 6 if mode == 'Part_Time_Undergraduate' else 2 if mode == 'Full_Time_Graduate' else 3
                candidates = [sid for sid, info in metadata.items() if info['mode'] == mode and info['entry_year'] <= year < info['entry_year'] + duration]
                candidates.sort(key=lambda sid: (metadata[sid]['entry_year'], sid))
                added = candidates[:desired - already]
                while len(added) < desired - already:
                    template_id = min(sid for sid in originals if modes[sid] == mode and students[sid]['student_Year'] == 1)
                    sid = next_id
                    next_id += 1
                    for table, mapping in profiles.items():
                        if template_id not in mapping:
                            continue
                        record = copy.deepcopy(mapping[template_id])
                        record['user_ID' if table in ['User', 'Login'] else 'student_ID'] = sid
                        if table == 'User':
                            record['DOB'] = shifted(record['DOB'], year - 2026)
                            record['last_Name'] = tables['User'][stable(sid) % len(tables['User'])]['last_Name']
                            record['street'] = f'{100 + sid % 8900} College Avenue'
                        elif table == 'Login':
                            record.update(user_Email=f'alumni{sid}@northbridge.edu', user_Password=None, lock_var=1, no_Of_Tries=3)
                        elif table in MODES:
                            record['credits_Earned'] = 0
                            if 'Status' in record:
                                record['Status'] = 'Former Student'
                            if 'Thesis' in record:
                                record['Thesis'] = None
                        elif table == 'Student_Major':
                            record['date_Of_Choice'] = str(year) + '-09-01'
                        tables[table].append(record)
                    metadata[sid] = {'student_ID': sid, 'mode': mode, 'entry_year': year, 'origin': template_id}
                    students[sid] = next(r for r in tables['Student'] if r['student_ID'] == sid)
                    modes[sid] = mode
                    added.append(sid)
                cohort.update(added)
                selected_generated.update(added)
            require(len(cohort) == 1600, 'Cohort population mismatch.')
            inactive = set(sorted(selected_generated, key=lambda sid: stable((term, sid)))[:80])
            sizes = Counter(course for records in main.values() for course in records)
            for sid in sorted(selected_generated):
                main[sid]
                if sid in inactive:
                    continue
                count = 3 if modes[sid].startswith('Full_Time') else 2
                dept = majors[students[sid]['major_ID']]
                for _ in range(count):
                    demand = Counter()
                    for c, size in sizes.items():
                        demand[courses[c]['dept_ID']] += math.ceil(size / 10)
                    candidates = [c for c, r in courses.items() if r['course_Type'] == students[sid]['student_Type'] and c not in history[sid] and c not in main[sid] and prereqs[c] <= history[sid]]
                    require(candidates, 'No eligible next course for ' + str(sid))
                    candidates.sort(key=lambda c: (demand[courses[c]['dept_ID']] >= capacities[courses[c]['dept_ID']] - 1,
                        c not in templates, courses[c]['dept_ID'] != dept, 0 < sizes[c] < 5,
                        sizes[c] // 30, -len(prereqs[c]), stable((sid, c, term))))
                    course = candidates[0]
                    main[sid][course] = ['A', 'A-', 'B+', 'B', 'B-', 'C+'][stable((sid, course, term)) % 6]
                    sizes[course] += 1
            for _ in range(10000):
                sizes = Counter(c for records in main.values() for c in records)
                demand = Counter()
                for c, size in sizes.items():
                    demand[courses[c]['dept_ID']] += math.ceil(size / 10)
                tiny = [c for c, size in sizes.items() if size < 5]
                overloaded = [d for d, n in demand.items() if n > capacities[d]]
                if not tiny and not overloaded:
                    break
                course = tiny[0] if tiny else next(c for c in sizes if courses[c]['dept_ID'] == overloaded[0] and sizes[c] > 5)
                options = []
                for sid in selected_generated:
                    if course not in main[sid]:
                        continue
                    for c, size in sizes.items():
                        if c in main[sid] or c in history[sid] or courses[c]['course_Type'] != students[sid]['student_Type'] or not prereqs[c] <= history[sid]:
                            continue
                        d = courses[c]['dept_ID']
                        if overloaded and (d == courses[course]['dept_ID'] or demand[d] >= capacities[d]):
                            continue
                        if size < 5:
                            continue
                        options.append((size % 10 == 0, sizes[course] % 10 != 1, d != majors[students[sid]['major_ID']], sid, c))
                require(options, 'Cannot balance class sizes or teaching capacity in ' + name)
                _, _, _, sid, replacement = min(options)
                grade = main[sid].pop(course)
                main[sid][replacement] = grade
            else:
                raise RuntimeError('Offering repair did not converge.')
        summary = schedule(tables, term, main)
        earned = Counter()
        for r in tables['Student_History']:
            if r['Grade'] in PASSING:
                earned[r['student_ID']] += courses[r['course_ID']]['course_Credits']
        for mode in MODES:
            for r in tables[mode]:
                if r['student_ID'] in metadata:
                    r['credits_Earned'] = earned[r['student_ID']]
        for r in tables['Student']:
            if r['student_ID'] in metadata:
                info = metadata[r['student_ID']]
                r['student_Year'] = min(4 if r['student_Type'] == 'Undergraduate' else 2, max(1, year - info['entry_year'] + 1))
        plan = {'target_id': term, 'population': 1600, 'registered_students': summary['registered_students'],
                'target_semester': name, 'cohort_ids': sorted(cohort), 'summary': summary,
                'replacement_tables': {t: rows for t, rows in tables.items() if rows != before[t]},
                'cohort_metadata': sorted(metadata.values(), key=lambda r: r['student_ID'])}
        path = output / f'term_{term}.json'
        path.write_text(json.dumps(plan, separators=(',', ':')))
        path.chmod(0o600)
        summaries.append(summary)
        print(json.dumps(summary), flush=True)
    return summaries


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--source', required=True)
    parser.add_argument('--manifest', required=True)
    parser.add_argument('--output-dir', required=True)
    args = parser.parse_args()
    output = Path(args.output_dir)
    output.mkdir(mode=0o700, parents=True, exist_ok=True)
    build(json.loads(Path(args.source).read_text()), json.loads(Path(args.manifest).read_text()), output)
