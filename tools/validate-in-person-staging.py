#!/usr/bin/env python3
"""Independent full-date validation of staged resources, rosters and seats."""
import argparse,json,sys,collections,itertools,hashlib,datetime as dt
from pathlib import Path
root=Path(__file__).resolve().parents[1];sys.path.insert(0,str(root/'database/generation'))
from academic_meetings import meetings,overlap,old_meetings
from in_person_sections import RELATIONSHIP,two_day_lecture_pattern
from faculty_planning import cross_appointments
parser=argparse.ArgumentParser();parser.add_argument('--source',required=True);parser.add_argument('--candidate-directory',required=True);args=parser.parse_args()
for value in [args.source,args.candidate_directory]:
 if not str(Path(value).resolve()).startswith('/private/tmp/'):raise SystemExit('Private input paths required')
p=Path(args.candidate_directory);c=json.load(open(p/'candidate.json'));s=json.load(open(p/'schedule.json'));tables=json.load(open(args.source));errors=collections.Counter();expected=collections.Counter((str(a['termCode']),a['studentId'],a['courseKey'])for a in c['attempts']if str(a['termCode'])in s['scheduledTerms']);actual=collections.Counter();fac={r['faculty_ID']:r for r in tables['Faculty']};courses={r['course_ID']:r for r in tables['Course']};room={r['room_ID']:r for r in tables['Room']};apps={(r['faculty_ID'],r['dept_ID'])for r in tables['Faculty_Department']};grouped=collections.defaultdict(list)
people={r['studentId']:r for r in c['students']};loads=collections.Counter();histories=collections.defaultdict(list)
if set(people)!={r['student_ID']for r in tables['Undergraduate']}:errors['undergraduate-population-changed']+=1
for a in c['attempts']:
 term=str(a['termCode']);sid=a['studentId'];loads[term,sid]+=c['courses'][a['courseKey']]['credits'];histories[sid].append(a)
 if term>='202609'and(a['grade']is not None or a['status']!=('in-progress'if term=='202609'else'planned')):errors['current-future-grade-or-status']+=1
 if term<'202609'and(a['grade']is None or a['status']!='completed'):errors['historical-grade-or-status']+=1
for term,ids in c['plannedTermStudents'].items():
 if len(ids)!=len(set(ids)):errors['duplicate-planned-student']+=1
 for sid in ids:
  minimum,maximum=(12,16)if people[sid]['mode']=='Full-Time'else(4,8)
  if not minimum<=loads[term,sid]<=maximum:errors['student-credit-load']+=1
for term in ['202609','202702']:
 original={int(sid)for sid,terms in c['reconstructionTermHorizon'].items()if term in terms}
 if original!=set(c['plannedTermStudents'].get(term,[])):errors['current-future-student-disappeared']+=1
for sid,rows in histories.items():
 for a in rows:
  for partner in c['corequisiteEdges'].get(a['courseKey'],[]):
   # A Spring preregistration may depend conditionally on a Fall in-progress
   # component. The independent evaluator preserves that conditional verdict;
   # this coverage check must not treat it as missing or as earned credit.
   if not any(other['courseKey']==partner and(other['termCode']==a['termCode']or(other['termCode']<a['termCode']and(other['status']in ['in-progress','planned']or(other['status']=='completed'and other['grade']not in [None,'F','I','W','NR','NC']))))for other in rows):errors['missing-corequisite-component']+=1
official={}
for entry in json.load(open(root/'database/staging/academic-alignment/official-history-source-register.json'))['terms']:
 for row in json.load(open(root/entry['path']))['sections']:official[entry['term'],row['courseReferenceNumber']]=row
date_ranges=collections.defaultdict(collections.Counter)
for (term,_),row in official.items():
 for m in row['meetings']:
  if m.get('startDate')and m.get('endDate')and(dt.datetime.strptime(m['endDate'],'%m/%d/%Y')-dt.datetime.strptime(m['startDate'],'%m/%d/%Y')).days>=84:date_ranges[term][m['startDate'],m['endDate']]+=1
freshfall=sum([json.load(open(path))['data']for path in ['/Users/phillipaltidor/Downloads/searchResults.json','/Users/phillipaltidor/Downloads/searchResults (1).json']],[])
metadata={'202609':freshfall,'202702':json.load(open(root/'database/staging/academic-alignment/spring-2027-link-capacity-review.json'))['sections']}
linked={(term,r['courseReferenceNumber'])for term,rows in metadata.items()for r in rows if r.get('isSectionLinked')or r.get('crossList')or(r.get('crossListCapacity')or 0)>0}
policy=json.load(open(root/'database/staging/academic-alignment/northbridge-in-person-section-policy.json'))
lecture_policy=c.get('northbridgeLectureMeetingPolicy')
if lecture_policy and lecture_policy!=json.load(open(root/'database/staging/academic-alignment/northbridge-lecture-meeting-policy.json')):errors['unreviewed-lecture-meeting-policy']+=1
custom_count=0
if c.get('facultyCrossAppointments'):
 if c['facultyCrossAppointments']!=cross_appointments(tables):errors['unreviewed-faculty-cross-appointments']+=1
 for a in c['facultyCrossAppointments']:apps.add((a['faculty_ID'],a['dept_ID']))
for fid,f in fac.items():
 if sum(person==fid for person,dept in apps)>(3 if f['faculty_Type']=='Full-Time'else 1):errors['faculty-department-membership-limit']+=1
roomcapacity={r['lecture_ID']:r['avail_Seats']for r in tables['Lecture']};roomcapacity.update({r['lab_ID']:r['no_Of_Workstations']for r in tables['Lab']})
for sec in s['sections']:
 pattern=meetings(sec);term=sec['term'];key=sec['courseKey'];ids=sec['studentIds']
 if lecture_policy and term in lecture_policy['requiredCourseTerms'].get(key,[]):
  if sec.get('sourceRelationship')!=RELATIONSHIP or sec['officialCRN']is not None or not two_day_lecture_pattern(pattern):errors['required-custom-two-day-lecture-pattern']+=1
 template_term=sec.get('sourceTemplateTerm',term);src=official.get((template_term,sec['sourceReferenceCRN']))
 if not src:errors['missing-source-section']+=1
 else:
  if template_term!=term:
   if sec.get('sourceRelationship')!=RELATIONSHIP or sec['officialCRN']is not None:errors['cross-term-section-misrepresented-as-official']+=1
   if sorted((d,b,e)for d,b,e,_,_ in meetings(src))!=sorted((d,b,e)for d,b,e,_,_ in pattern):errors['cross-term-periods-changed']+=1
   if sec.get('sourceOriginalMeetings')!=src['meetings']:errors['cross-term-original-source-changed']+=1
   if not date_ranges[term]or any((m['startDate'],m['endDate'])!=date_ranges[term].most_common(1)[0][0]for m in sec['meetings']):errors['cross-term-target-dates-not-verified']+=1
  elif sorted(meetings(src))!=sorted(pattern):errors['official-meeting-pattern-changed']+=1
  custom=sec.get('sourceRelationship')==RELATIONSHIP
  if custom:
   custom_count+=1
   if c.get('northbridgeInPersonSectionPolicy')!=policy:errors['unapproved-northbridge-section-policy']+=1
   if sec['officialCRN']is not None or sec['sourceSectionLabel']!='NB-IP':errors['northbridge-section-misrepresented-as-official']+=1
   template=c['courses'].get(src['subject']+src['courseNumber'])
   if not template or template['labBearing']!=sec['labBearing']:errors['northbridge-template-lab-classification']+=1
   if c['courses'][key]['credits']==4 and sum(e-b for _,b,e,_,_ in pattern)<(180 if term<'202609'else 200):errors['northbridge-four-credit-meeting-time']+=1
   if any(hi-lo<84 for _,_,_,lo,hi in pattern):errors['northbridge-short-term-template']+=1
   if src['sequenceNumber']!=sec.get('sourceTemplateSectionLabel'):errors['northbridge-template-label-changed']+=1
  elif src['sequenceNumber']!=sec['sourceSectionLabel']:errors['official-section-label-changed']+=1
  if sec['sourceCourseCode']!=src['subject']+src['courseNumber']:errors['template-course-reference-mismatch']+=1
  credits=src.get('creditHours')if src.get('creditHours')is not None else src.get('creditHourLow')
  if credits!=c['courses'][key]['credits']:errors['source-credit-mismatch']+=1
  if sec['capacity']>src['maximumEnrollment']:errors['source-capacity-exceeded']+=1
 if (template_term,sec['sourceReferenceCRN'])in linked:errors['unresolved-linked-or-cross-listed-section']+=1
 if sec['capacity']>roomcapacity.get(sec['roomId'],0):errors['recorded-room-seats-exceeded']+=1
 if sec['labBearing']and room[sec['roomId']]['room_Type']!='Lab':errors['laboratory-room-required']+=1
 if sec['instructionalMethodDescription']!='On Campus':errors['not-in-person']+=1
 if not pattern:errors['no-timed-meetings']+=1
 if len(ids)>sec['capacity']:errors['section-over-capacity']+=1
 if len(ids)!=len(set(ids)):errors['duplicate-roster-student']+=1
 if (sec['facultyId'],sec['departmentId'])not in apps:errors['faculty-department']+=1
 if sec['roomId']not in room:errors['unknown-room']+=1
 for sid in ids:actual[term,sid,key]+=1;grouped[term,'student',sid].append(pattern)
 grouped[term,'faculty',sec['facultyId']].append(pattern);grouped[term,'room',sec['roomId']].append(pattern)
 if (sec['sourceCourseCode']!=c['courses'][key]['courseCode']or'@'in key)and sec['officialCRN']is not None:errors['conversion-misrepresented-as-official']+=1
for old in tables['Course_Section']:
 if courses[old['course_ID']]['course_Type']!='Graduate':continue
 term=next((sec['term']for sec in s['sections']if sec['termId']==old['semester_ID']),None)
 if not term:continue
 pat=old_meetings(tables,old);grouped[term,'faculty',old['faculty_ID']].append(pat);grouped[term,'room',old['room_ID']].append(pat)
for (term,kind,_),patterns in grouped.items():
 if kind=='faculty':
  fid=_;cap=2 if fac[fid]['faculty_Type']=='Full-Time'else 1
  if len(patterns)>cap:errors['faculty-load']+=1
 for a,b in itertools.combinations(patterns,2):
  if overlap(a,b):errors[kind+'-meeting-conflict']+=1
if actual!=expected:errors['roster-attempt-coverage']+=1
report={'scheduledTerms':s['scheduledTerms'],'sectionsChecked':len(s['sections']),'enrollmentAssignmentsChecked':sum(actual.values()),'northbridgeOnlySections':custom_count,'candidateFileSHA256':hashlib.sha256((p/'candidate.json').read_bytes()).hexdigest(),'scheduleFileSHA256':hashlib.sha256((p/'schedule.json').read_bytes()).hexdigest(),'errors':dict(errors),'includesPreservedGraduateResourceReservations':True,'officialMeetingsCreditsAndSectionLabelsChecked':True,'linkedAndSharedSeatSectionsExcluded':True,'recordedRoomAndSourceCapacitiesChecked':True,'schemaChanged':False,'databaseChanged':False}
(root/'database/staging/academic-alignment/two-term-timetable-validation.json').write_text(json.dumps(report,indent=2)+'\n');(p/'timetable-validation.json').write_text(json.dumps(report,indent=2)+'\n');print(json.dumps(report));assert not errors
