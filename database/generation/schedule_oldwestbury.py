#!/usr/bin/env python3
"""Schedule a private rebuild candidate. Never write the portal database.

Official term/CRN identifies the source offering; deliveryGroup identifies a
fictional Northbridge roster. A borrowed historical-version meeting template
never becomes an invented official CRN for that historical course version.
"""
import argparse
import collections
import hashlib
import json
from pathlib import Path

from ortools.sat.python import cp_model
from rebuild_oldwestbury import Builder, canonical

from academic_meetings import meetings, overlap, old_meetings
import datetime as dt


def solver(seconds=30):
    s = cp_model.CpSolver()
    s.parameters.max_time_in_seconds = seconds
    s.parameters.num_search_workers = 4
    s.parameters.random_seed = 19
    return s


def faculty_assignment(tables, sections, reserved, seconds=30):
    # Departments with disjoint faculty appointments are independent staffing
    # problems. Solving them separately avoids a large symmetric global search.
    appointments = collections.defaultdict(set)
    for row in tables['Faculty_Department']:
        appointments[row['faculty_ID']].add(row['dept_ID'])
    departments = sorted({s['departmentId'] for s in sections})
    if len(departments) > 1 and all(len(ds) == 1 for ds in appointments.values()):
        answer = [None] * len(sections)
        for department in departments:
            indexes = [i for i, s in enumerate(sections) if s['departmentId'] == department]
            assignment, state = faculty_assignment(tables, [sections[i] for i in indexes], reserved, seconds)
            if assignment is None:
                print('staffing department', department, state, flush=True)
                return None, state
            for i, fid in zip(indexes, assignment):
                answer[i] = fid
        return answer, 'feasible'
    faculty = {f['faculty_ID']: f for f in tables['Faculty']}
    eligible = collections.defaultdict(set)
    busy = collections.defaultdict(list)
    counts = collections.Counter()
    for f in tables['Faculty_Department']:
        eligible[f['dept_ID']].add(f['faculty_ID'])
    for sec in reserved:
        busy[sec['faculty_ID']].extend(old_meetings(tables, sec))
        counts[sec['faculty_ID']] += 1
    model = cp_model.CpModel()
    byfaculty = collections.defaultdict(list)
    first_occurrences = collections.defaultdict(list)
    variables = []
    domains = []
    for i, sec in enumerate(sections):
        options = []
        for fid in sorted(eligible[sec['departmentId']]):
            cap = 2 if faculty[fid]['faculty_Type'] == 'Full-Time' else 1
            if counts[fid] < cap and not overlap(sec['_meetings'], busy[fid]):
                options.append(fid)
        if not options:
            return None, 'no-eligible-faculty'
        var = model.NewIntVarFromDomain(cp_model.Domain.FromValues(options), 'faculty' + str(i))
        variables.append(var)
        domains.append(set(options))
        for fid in options:
            assigned = model.NewBoolVar('f' + str(i) + '_' + str(fid))
            model.Add(var == fid).OnlyEnforceIf(assigned)
            model.Add(var != fid).OnlyEnforceIf(assigned.Not())
            byfaculty[fid].append(assigned)
            first = model.NewIntVar(i, len(sections), 'first_' + str(i) + '_' + str(fid))
            model.Add(first == i).OnlyEnforceIf(assigned)
            model.Add(first == len(sections)).OnlyEnforceIf(assigned.Not())
            first_occurrences[fid].append(first)
        for j in range(i):
            if domains[i] & domains[j] and overlap(sec['_meetings'], sections[j]['_meetings']):
                model.Add(var != variables[j])
    for fid, flags in byfaculty.items():
        cap = 2 if faculty[fid]['faculty_Type'] == 'Full-Time' else 1
        model.Add(sum(flags) <= cap - counts[fid])
    interchangeable = collections.defaultdict(list)
    for fid in byfaculty:
        interchangeable[(tuple(sorted(appointments[fid])), faculty[fid]['faculty_Type'], counts[fid], tuple(sorted(busy[fid])))].append(fid)
    for ids in interchangeable.values():
        previous = None
        for fid in sorted(ids):
            first = model.NewIntVar(0, len(sections), 'first_used_' + str(fid))
            model.AddMinEquality(first, first_occurrences[fid])
            if previous is not None:
                model.Add(previous <= first)
            previous = first
    s = solver(seconds)
    status = s.Solve(model)
    if status not in (cp_model.OPTIMAL, cp_model.FEASIBLE):
        return None, s.StatusName(status)
    return [s.Value(v) for v in variables], 'feasible'


def assign_rooms(tables, sections, reserved):
    rooms = {r['room_ID']: r for r in tables['Room'] if r['room_Type'] in ['Lecture', 'Lab']}
    busy = collections.defaultdict(list)
    for sec in reserved:
        busy[sec['room_ID']].extend(old_meetings(tables, sec))
    result = [None] * len(sections)
    for i in sorted(range(len(sections)), key=lambda i: (-sections[i]['labBearing'], -len(sections[i]['_meetings']), i)):
        sec = sections[i]
        if not sec['_meetings']:
            continue
        options = [rid for rid, room in rooms.items()
                   if (room['room_Type'] == 'Lab' if sec['labBearing'] else True)
                   and not overlap(sec['_meetings'], busy[rid])]
        if not options:
            return None
        rid = min(options, key=lambda r: (rooms[r]['room_Type'] == 'Lab' and not sec['labBearing'], len(busy[r]), r))
        result[i] = rid
        busy[rid].extend(sec['_meetings'])
    return result


def choose_offerings(builder, tables, candidate, term, tid, cohort_size=10, forbidden_counts=None):
    """Choose meetings per learner cohort before packing ten-seat rosters.

    Packing students into arbitrary sections first can couple otherwise valid
    timetables and make a feasible dataset appear impossible.
    """
    bystudent = collections.defaultdict(list)
    for a in candidate['attempts']:
        if a['termCode'] == term:
            bystudent[a['studentId']].append(a['courseKey'])
    cohorts = collections.defaultdict(list)
    for sid, keys in bystudent.items():
        cohorts[tuple(sorted(keys))].append(sid)
    groups = []
    for keys, ids in sorted(cohorts.items()):
        for offset in range(0, len(ids), cohort_size):
            groups.append((keys, ids[offset:offset + cohort_size]))
    options = {}
    for key in candidate['rosters'][term]:
        deduplicated = {}
        for source in builder.sources(term, key):
            deduplicated.setdefault(tuple(meetings(source)), source)
        options[key] = list(deduplicated.items())
    model = cp_model.CpModel()
    variables, roster_flags = {}, collections.defaultdict(list)
    for g, (keys, ids) in enumerate(groups):
        for key in keys:
            var = model.NewIntVar(0, len(options[key]) - 1, 'meeting_' + str(g) + '_' + key)
            variables[g, key] = var
            for o in range(len(options[key])):
                flag = model.NewBoolVar('choice_' + str(g) + '_' + key + '_' + str(o))
                model.Add(var == o).OnlyEnforceIf(flag)
                model.Add(var != o).OnlyEnforceIf(flag.Not())
                roster_flags[key, o].append((flag, len(ids)))
        for i, left in enumerate(keys):
            for right in keys[i + 1:]:
                allowed = [(a, b) for a, (ap, _) in enumerate(options[left])
                           for b, (bp, _) in enumerate(options[right]) if not overlap(ap, bp)]
                if len(allowed) < len(options[left]) * len(options[right]):
                    if allowed:
                        model.AddAllowedAssignments([variables[g, left], variables[g, right]], allowed)
                    else:
                        model.AddBoolOr([])
    section_counts = collections.defaultdict(list)
    all_counts = []
    count_variables = {}
    for (key, option), flags in roster_flags.items():
        maximum = sum(n for _, n in flags)
        count = model.NewIntVar(0, (maximum + 9) // 10, 'sections_' + key + '_' + str(option))
        population = sum(flag * n for flag, n in flags)
        model.Add(count * 10 >= population)
        model.Add(count * 10 <= population + 9)
        section_counts[candidate['courses'][key]['departmentId']].append(count)
        count_variables[key, option] = count
        all_counts.append(count)
    for d, counts in section_counts.items():
        model.Add(sum(counts) <= builder.dept_caps[d] - builder.grad_load[tid, d])
    ordered_counts = sorted(count_variables)
    for previous in forbidden_counts or []:
        if previous.keys() != count_variables.keys():
            raise ValueError('Meeting option identity changed between resource retries')
        # Do not repeat a meeting-pattern section distribution that cannot be
        # staffed under the individual faculty workload/conflict constraints.
        model.AddForbiddenAssignments([count_variables[k] for k in ordered_counts], [[previous[k] for k in ordered_counts]])
    # Department workload totals alone do not limit simultaneous classes.
    # Model each actual recurring date with the number of parallel ten-seat
    # sections as demand, reserving preserved graduate teaching time too.
    calendar = collections.defaultdict(list)
    bounds = [(p[3], p[4]) for values in options.values() for pattern, _ in values for p in pattern]
    if bounds:
        lower, upper = min(p[0] for p in bounds), max(p[1] for p in bounds)
        def reserve(department, pattern, demand):
            for day, start, end, lo, hi in pattern:
                lo, hi = max(lo, lower), min(hi, upper)
                if lo > hi:
                    continue
                first = lo + (day - dt.date.fromordinal(lo).weekday()) % 7
                for date in range(first, hi + 1, 7):
                    calendar[department, date].append((start, end, demand))
        for (key, option), count in count_variables.items():
            reserve(candidate['courses'][key]['departmentId'], options[key][option][0], count)
        for sec in tables['Course_Section']:
            if sec['semester_ID'] == tid and builder.oldcourses[sec['course_ID']]['course_Type'] == 'Graduate':
                reserve(builder.oldcourses[sec['course_ID']]['dept_ID'], old_meetings(tables, sec), 1)
        faculty_by_department = collections.defaultdict(set)
        for appointment in tables['Faculty_Department']:
            faculty_by_department[appointment['dept_ID']].add(appointment['faculty_ID'])
        # Recurring weeks often have identical active meeting/demand patterns.
        # Keep one constraint per distinct daily pattern, preserving every date
        # transition without multiplying the same cumulative constraint 15 times.
        seen_daily_patterns = set()
        serial = 0
        for (department, date), entries in calendar.items():
            signature = (department, tuple(sorted((start, end,
                ('constant', demand) if isinstance(demand, int) else ('variable', demand.Index()))
                for start, end, demand in entries)))
            if signature in seen_daily_patterns:
                continue
            seen_daily_patterns.add(signature)
            intervals, demands = [], []
            for start, end, demand in entries:
                intervals.append(model.NewFixedSizeIntervalVar(start, end - start, 'teaching_day_' + str(serial)))
                demands.append(demand)
                serial += 1
            model.AddCumulative(intervals, demands, len(faculty_by_department[department]))
    model.Minimize(sum(all_counts))
    s = solver(45)
    # A fully constrained feasible timetable is sufficient. Proving that its
    # section count is globally minimal is unnecessary for a rehearsal.
    s.parameters.stop_after_first_solution = True
    status = s.Solve(model)
    if status not in (cp_model.OPTIMAL, cp_model.FEASIBLE):
        return None, s.StatusName(status)
    builder.last_meeting_counts = {k: s.Value(v) for k, v in count_variables.items()}
    rosters = collections.defaultdict(list)
    for g, (keys, ids) in enumerate(groups):
        for key in keys:
            rosters[key, s.Value(variables[g, key])].extend(ids)
    sections = []
    delivery_numbers = collections.Counter()
    for (key, option), ids in sorted(rosters.items()):
        pattern, source = options[key][option]
        course = candidate['courses'][key]
        source_code = source['subject'] + source['courseNumber']
        same_version = source_code == canonical(key) and '@' not in key
        for offset in range(0, len(ids), 10):
            delivery_numbers[key] += 1
            sections.append({'term': term, 'termId': tid, 'courseKey': key,
                             'departmentId': course['departmentId'], 'labBearing': course['labBearing'],
                             'deliveryGroup': delivery_numbers[key], 'studentIds': ids[offset:offset + 10],
                             'capacity': 10, '_meetings': pattern,
                             'officialCRN': source['courseReferenceNumber'] if same_version else None,
                             'sourceReferenceCRN': source['courseReferenceNumber'], 'sourceCourseCode': source_code,
                             'sourceCourseTitle': source['courseTitle'],
                             'sourceSectionLabel': source['sequenceNumber'], 'meetings': source['meetings'],
                             'instructionalMethodDescription': source['instructionalMethodDescription'],
                             'sourceRelationship': 'same-course-offering' if same_version else 'historical-or-alias-meeting-template'})
    return sections, s.StatusName(status)


def build(tables, candidate, only_term=None, cohort_size=10, faculty_seconds=30):
    builder = Builder(tables)
    oldcourses = {c['course_ID']: c for c in tables['Course']}
    term_ids = {str(a['termCode']): a['termId'] for a in candidate['attempts']}
    result, failures = [], []
    for term, rosters in candidate['rosters'].items():
        if only_term and term != only_term:
            continue
        reserved = [sec for sec in tables['Course_Section'] if sec['semester_ID'] == term_ids[term]
                    and oldcourses[sec['course_ID']]['course_Type'] == 'Graduate']
        excluded = []
        for retry in range(8):
            sections, status = choose_offerings(builder, tables, candidate, term, term_ids[term], cohort_size, excluded)
            if sections is None:
                break
            faculty, faculty_status = faculty_assignment(tables, sections, reserved, faculty_seconds)
            rooms = assign_rooms(tables, sections, reserved)
            if faculty is not None and rooms is not None:
                break
            # A timeout does not prove this distribution impossible. Retry
            # exclusion only follows a solver proof, never an UNKNOWN result.
            if faculty_status != 'INFEASIBLE':
                break
            excluded.append(builder.last_meeting_counts)
            print(term, 'retrying meeting choices after staffing infeasibility', retry + 1, flush=True)
        if sections is None:
            failures.append({'term': term, 'stage': 'student-meeting-conflicts', 'status': status})
            print(term, 'student schedule', status, flush=True)
            continue
        if faculty is None or rooms is None:
            failures.append({'term': term, 'stage': 'resource-assignment', 'faculty': faculty_status, 'rooms': 'feasible' if rooms is not None else 'infeasible'})
            print(term, 'faculty', faculty_status, 'rooms', rooms is not None, flush=True)
            continue
        for i, sec in enumerate(sections):
            sec['facultyId'], sec['roomId'] = faculty[i], rooms[i]
            for field in ['_meetings']:
                del sec[field]
        result.extend(sections)
        print(term, len(sections), 'sections with no known student, faculty or room meeting conflicts', flush=True)
    completed_terms=sorted({s['term'] for s in result})
    expected_terms=sorted(candidate['rosters'])
    complete=not failures and completed_terms==expected_terms
    return {'status': 'candidate-scheduled' if complete else 'candidate-blocked' if failures else 'candidate-partially-scheduled',
            'scheduledTerms': completed_terms, 'expectedTerms': expected_terms,
            'knownMeetingTimesOnly': True,
            'candidateSHA256': hashlib.sha256(json.dumps(candidate, sort_keys=True).encode()).hexdigest(),
            'officialCRNsAreTermScoped': True, 'parallelDeliveryGroupsAreNorthbridgeFictional': True,
            'workingDatabaseChanged': False, 'sections': result, 'failures': failures}


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--source', required=True)
    parser.add_argument('--candidate', required=True)
    parser.add_argument('--output', required=True)
    parser.add_argument('--term')
    parser.add_argument('--cohort-size', type=int, choices=[1, 5, 10], default=10)
    parser.add_argument('--faculty-seconds', type=int, choices=[30, 90, 180], default=30)
    args = parser.parse_args()
    for p in [args.source, args.candidate, args.output]:
        if not str(Path(p).resolve()).startswith('/private/tmp/'):
            raise SystemExit('Private temporary paths required')
    tables = json.loads(Path(args.source).read_text())
    candidate = json.loads(Path(args.candidate).read_text())
    output = build(tables, candidate, args.term, args.cohort_size, args.faculty_seconds)
    Path(args.output).write_text(json.dumps(output, indent=2) + '\n')
    Path(args.output).chmod(0o600)
    print(json.dumps({'status': output['status'], 'sections': len(output['sections']), 'failures': output['failures']}))
