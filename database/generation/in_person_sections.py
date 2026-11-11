"""Owner-approved Northbridge sections; source CRNs are template references only."""
import copy,json,collections,datetime as dt
from academic_meetings import meetings

RELATIONSHIP='northbridge-only-in-person-pattern'

def two_day_lecture_pattern(pattern):
    """Verified M/W or T/Th periods, exactly 100 minutes on each day."""
    return len(pattern)==2 and {row[0]for row in pattern}in [{0,2},{1,3}]and all(row[2]-row[1]==100 for row in pattern)

def target_term_templates(source_rows,target_rows,source_term):
    """Borrow periods, not official offerings, into a documented target term."""
    ranges=collections.Counter()
    for row in target_rows:
        for m in row['meetings']:
            if not m.get('startDate')or not m.get('endDate'):continue
            lo=dt.datetime.strptime(m['startDate'],'%m/%d/%Y').date();hi=dt.datetime.strptime(m['endDate'],'%m/%d/%Y').date()
            if (hi-lo).days>=84:ranges[m['startDate'],m['endDate']]+=1
    if not ranges:return []
    dates=ranges.most_common(1)[0][0];result=[]
    for row in source_rows:
        if row.get('instructionalMethodDescription')!='On Campus':continue
        row=copy.deepcopy(row);row['templateSourceTerm']=source_term;row['sourceOriginalMeetings']=copy.deepcopy(row['meetings'])
        for m in row['meetings']:m['startDate'],m['endDate']=dates
        result.append(row)
    return result

def template_options(course,rows,courses,excluded,minimum_four_credit_minutes=200):
    patterns={}
    for row in rows:
        code=row.get('subject','')+row.get('courseNumber','')
        source_course=courses.get(code)
        credit=row.get('creditHours')if row.get('creditHours')is not None else row.get('creditHourLow')
        if str(row['courseReferenceNumber'])in excluded or row.get('instructionalMethodDescription')!='On Campus':continue
        if credit!=course['credits']or row.get('creditHourHigh')not in [None,credit]or not source_course:continue
        if source_course['labBearing']!=course['labBearing']or(row.get('maximumEnrollment')or 0)<=0:continue
        pattern=meetings(row)
        if not pattern or any(hi-lo<84 for _,_,_,lo,hi in pattern):continue
        if credit==4 and sum(end-start for _,start,end,_,_ in pattern)<minimum_four_credit_minutes:continue
        signature=tuple(sorted(pattern))
        lecture_preference=credit==4 and not course['labBearing']
        rank=(lecture_preference and not two_day_lecture_pattern(pattern),sum(end-start for _,start,end,_,_ in pattern),source_course['departmentId']!=course['departmentId'],len(pattern)!=2,str(row['courseReferenceNumber']))
        if signature not in patterns or rank<patterns[signature][0]:patterns[signature]=(rank,row)
    result=[]
    for _,row in sorted(patterns.values(),key=lambda r:r[0])[:12]:
        row=copy.deepcopy(row);row['northbridgeOnly']=True
        result.append(row)
    return result
