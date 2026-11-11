#!/usr/bin/env python3
"""Retime explicitly approved custom sections without changing academic records."""
import argparse,collections,copy,hashlib,itertools,json,os,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];sys.path.insert(0,str(ROOT/'database/generation'))
from academic_meetings import meetings,overlap,old_meetings
from in_person_sections import template_options,two_day_lecture_pattern,RELATIONSHIP

def retime(source,candidate,schedule):
 c=copy.deepcopy(candidate);s=copy.deepcopy(schedule)
 policy=json.loads((ROOT/'database/staging/academic-alignment/northbridge-lecture-meeting-policy.json').read_text())
 requested={(term,key)for key,terms in policy['requiredCourseTerms'].items()for term in terms}
 targets=[sec for sec in s['sections']if(sec['term'],sec['courseKey'])in requested]
 if not targets:raise ValueError('No requested custom sections found.')
 for sec in targets:
  course=c['courses'][sec['courseKey']]
  if sec['sourceRelationship']!=RELATIONSHIP or sec['officialCRN']is not None or course['credits']!=4 or course['labBearing']:raise ValueError('Only explicitly fictional four-credit lectures may be retimed.')
 registry={r['term']:r for r in json.loads((ROOT/'database/staging/academic-alignment/official-history-source-register.json').read_text())['terms']}
 oldcourses={row['course_ID']:row for row in source['Course']};events=[]
 for term in sorted({x['term']for x in targets}):
  changing=[x for x in targets if x['term']==term];fixed=[x for x in s['sections']if x['term']==term and x not in changing]
  rows=json.loads((ROOT/registry[term]['path']).read_text())['sections']
  if term=='202609':metadata=sum([json.loads(Path(p).read_text())['data']for p in ['/Users/phillipaltidor/Downloads/searchResults.json','/Users/phillipaltidor/Downloads/searchResults (1).json']],[])
  else:metadata=json.loads((ROOT/'database/staging/academic-alignment/spring-2027-link-capacity-review.json').read_text())['sections']
  excluded={str(r['courseReferenceNumber'])for r in metadata if r.get('isSectionLinked')or r.get('crossList')or(r.get('crossListCapacity')or 0)>0}
  occupied=collections.defaultdict(list)
  for sec in fixed:
   pattern=meetings(sec);occupied['faculty',sec['facultyId']]+=pattern;occupied['room',sec['roomId']]+=pattern
   for sid in sec['studentIds']:occupied['student',sid]+=pattern
  for sec in source['Course_Section']:
   if sec['semester_ID']==changing[0]['termId']and oldcourses[sec['course_ID']]['course_Type']=='Graduate':
    pattern=old_meetings(source,sec);occupied['faculty',sec['faculty_ID']]+=pattern;occupied['room',sec['room_ID']]+=pattern
  choices=[]
  for sec in changing:
   options=[]
   for row in template_options(c['courses'][sec['courseKey']],rows,c['courses'],excluded):
    pattern=meetings(row)
    resources=[('faculty',sec['facultyId']),('room',sec['roomId'])]+[('student',sid)for sid in sec['studentIds']]
    if not two_day_lecture_pattern(pattern)or row['maximumEnrollment']<sec['capacity']or any(overlap(pattern,occupied[key])for key in resources):continue
    options.append(row)
   if not options:raise ValueError('No conflict-free verified two-day template with existing roster, faculty, and room for '+sec['courseKey']+' group '+str(sec['deliveryGroup']))
   choices.append(options)
  picked=None
  for combination in itertools.product(*choices):
   compatible=True
   for i,j in itertools.combinations(range(len(changing)),2):
    a,b=changing[i],changing[j]
    shared=a['facultyId']==b['facultyId']or a['roomId']==b['roomId']or bool(set(a['studentIds'])&set(b['studentIds']))
    if shared and overlap(meetings(combination[i]),meetings(combination[j])):compatible=False;break
   if compatible:picked=combination;break
  if picked is None:raise ValueError('No jointly conflict-free template assignment.')
  for sec,row in zip(changing,picked):
   before=copy.deepcopy(sec)
   sec.update({'sourceReferenceCRN':row['courseReferenceNumber'],'sourceCourseCode':row['subject']+row['courseNumber'],'sourceCourseTitle':row['courseTitle'],'sourceTemplateSectionLabel':row['sequenceNumber'],'sourceTemplateTerm':term,'sourceOriginalMeetings':copy.deepcopy(row['meetings']),'meetings':copy.deepcopy(row['meetings']),'meetingPolicyDecision':policy['decisionId']})
   events.append({'term':term,'courseKey':sec['courseKey'],'deliveryGroup':sec['deliveryGroup'],'previousTemplate':{k:before[k]for k in ['sourceReferenceCRN','sourceCourseCode','sourceTemplateTerm','meetings']},'replacementTemplate':{k:sec[k]for k in ['sourceReferenceCRN','sourceCourseCode','sourceTemplateTerm','meetings']},'studentsFacultyRoomCapacityAndCourseworkUnchanged':True})
 c['northbridgeLectureMeetingPolicy']=policy;s['lectureMeetingRepairs']=events
 s['candidateSHA256']=hashlib.sha256(json.dumps(c,sort_keys=True).encode()).hexdigest()
 return c,s,{'status':'meeting-repair-staged-awaiting-independent-validation','sectionsRetimed':len(events),'termCourses':[{'term':t,'course':k}for t,k in sorted(requested)],'academicRecordsChanged':False,'facultyRoomsRostersCapacitiesChanged':False,'databaseChanged':False,'schemaChanged':False,'activationAllowed':False}

if __name__=='__main__':
 p=argparse.ArgumentParser();p.add_argument('--source',required=True);p.add_argument('--candidate-directory',required=True);p.add_argument('--output',required=True);args=p.parse_args()
 for value in vars(args).values():
  if not str(Path(value).resolve()).startswith('/private/tmp/'):raise SystemExit('Private paths required.')
 os.umask(0o077);out=Path(args.output);out.mkdir(mode=0o700,exist_ok=False);base=Path(args.candidate_directory)
 c,s,summary=retime(json.loads(Path(args.source).read_text()),json.loads((base/'candidate.json').read_text()),json.loads((base/'schedule.json').read_text()))
 for name,value in [('candidate.json',c),('schedule.json',s),('summary.json',summary)]:(out/name).write_text(json.dumps(value,indent=2)+'\n')
 print(json.dumps(summary))
