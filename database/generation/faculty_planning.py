"""Reviewable fictional cross-appointments within existing manual/schema limits."""
from collections import defaultdict
from academic_meetings import overlap, old_meetings, meetings


def rebalance_faculty(source, sections, proposed, appointments, seconds=5):
    """Return a staffing proposal without mutating sections or graduate reservations.

    Greedy assignments can consume cross-appointed faculty needed elsewhere.
    Reassign existing undergraduate sections within the same reviewed appointments,
    preserving all meeting patterns and minimizing changed teaching assignments.
    """
    from ortools.sat.python import cp_model
    faculty={r['faculty_ID']:r for r in source['Faculty']}
    courses={r['course_ID']:r for r in source['Course']}
    reserved=defaultdict(list);counts=defaultdict(int)
    for row in source['Course_Section']:
        if row['semester_ID']==proposed['termId'] and courses[row['course_ID']]['course_Type']=='Graduate':
            reserved[row['faculty_ID']]+=old_meetings(source,row)
            counts[row['faculty_ID']]+=1
    rows=sections+[proposed];patterns=[meetings(row)for row in rows]
    # If the reachable appointment network has no spare teaching slot, changing
    # meeting patterns cannot make an extra section staffable.
    reachable=set(appointments[proposed['departmentId']]);previous=set()
    while previous!=reachable:
        previous=set(reachable)
        for row in sections:
            if row['facultyId'] in reachable:reachable.update(appointments[row['departmentId']])
    current=defaultdict(int)
    for row in sections:current[row['facultyId']]+=1
    if not any(current[f]+counts[f]<(2 if faculty[f]['faculty_Type']=='Full-Time'else 1)for f in reachable):return None
    model=cp_model.CpModel();assigned=defaultdict(list);variables={};changes=[]
    for i,row in enumerate(rows):
        options=[]
        for fid in sorted(appointments[row['departmentId']]):
            cap=2 if faculty[fid]['faculty_Type']=='Full-Time' else 1
            if counts[fid]>=cap or overlap(patterns[i],reserved[fid]):continue
            var=model.NewBoolVar(f'section_{i}_faculty_{fid}');variables[i,fid]=var
            assigned[fid].append((i,var));options.append(var)
            model.AddHint(var,int(row.get('facultyId')==fid))
            if i<len(sections) and row['facultyId']!=fid:changes.append(var)
        if not options:return None
        model.AddExactlyOne(options)
    for fid,values in assigned.items():
        cap=2 if faculty[fid]['faculty_Type']=='Full-Time' else 1
        model.Add(sum(v for _,v in values)<=cap-counts[fid])
        for j,(i,a) in enumerate(values):
            for k,b in values[:j]:
                if overlap(patterns[i],patterns[k]):model.Add(a+b<=1)
    model.Minimize(sum(changes));solver=cp_model.CpSolver()
    solver.parameters.max_time_in_seconds=seconds;solver.parameters.num_search_workers=4
    solver.parameters.random_seed=19
    if solver.Solve(model) not in (cp_model.OPTIMAL,cp_model.FEASIBLE):return None
    return [next(fid for fid in appointments[row['departmentId']] if (i,fid)in variables and solver.Value(variables[i,fid]))for i,row in enumerate(rows)]


def cross_appointments(source):
    faculty={r['faculty_ID']:r for r in source['Faculty']};existing={(r['faculty_ID'],r['dept_ID'])for r in source['Faculty_Department']}
    result=[]
    # Interdisciplinary fictional teaching assignments; no claim about a real
    # person's qualifications. Primary appointments and graduate sections stay.
    for donor,targets in [(5,[3,1]),(8,[6,7]),(9,[4,3]),(10,[6]),(7,[2])]:
        candidates=sorted(fid for fid,dept in existing if dept==donor and faculty[fid]['faculty_Type']=='Full-Time')[:8]
        for fid in candidates:
            for dept in targets:
                if (fid,dept)in existing:continue
                if sum(f==fid for f,d in existing)>=3:continue
                existing.add((fid,dept));result.append({'faculty_ID':fid,'dept_ID':dept,'basis':'Owner-authorized fictional teaching-plan reconstruction; manual permits full-time faculty in up to three departments.'})
    # Two additional fictional appointments address the verified terminal-term
    # staffing bottleneck. Primary appointments and individual teaching caps stay.
    for donor,target in [(5,1),(8,7)]:
        candidates=sorted(fid for fid,dept in existing if dept==donor and faculty[fid]['faculty_Type']=='Full-Time' and sum(f==fid for f,d in existing)==1)
        if candidates:
            fid=candidates[0];existing.add((fid,target))
            result.append({'faculty_ID':fid,'dept_ID':target,'basis':'Owner-authorized fictional teaching-plan reconstruction; additional terminal-term staffing route within the three-department limit.'})
    return result
