#!/usr/bin/env python3
"""Construct course choices and resource assignments jointly, in private staging."""
import argparse,collections,copy,hashlib,json,os,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];sys.path.insert(0,str(ROOT/'database/generation'))
from rebuild_oldwestbury import Builder,canonical,stable
from rebuild_eligibility import Eligibility
from academic_meetings import meetings,overlap,old_meetings

def run(source,base,fall,out):
 c=copy.deepcopy(base);b=Builder(source);b.enable_source_proof_planning();b.enable_project_policies();b.rules['MA1020']=c['entryPlacementPolicy']['rule'];term='202702';tid=next(a['termId']for a in c['attempts']if str(a['termCode'])==term)
 b.courses=c['courses'];b.prior={k:set(v)for k,v in c['prerequisiteEdges'].items()};b.prior['MA1020']=set();coreq={k:set(v)for k,v in c['corequisiteEdges'].items()}
 active={a['studentId']for a in c['attempts']if str(a['termCode'])==term};people={p['studentId']:p for p in c['students']};hist=collections.defaultdict(list)
 for a in c['attempts']:
  if str(a['termCode'])!=term:hist[a['studentId']].append(a)
 metadata=json.loads((ROOT/'database/staging/academic-alignment/spring-2027-link-capacity-review.json').read_text());flags={r['courseReferenceNumber']for r in metadata['sections']if r['isSectionLinked']or r['crossList']or(r['crossListCapacity']or 0)>0};fresh={r['courseReferenceNumber']:r for r in metadata['sections']}
 options={}
 for key,course in c['courses'].items():
  good=[]
  for s in b.sources(term,key):
   credit=s.get('creditHours')if s.get('creditHours')is not None else s.get('creditHourLow');cap=s.get('maximumEnrollment')or 0
   if s['courseReferenceNumber']in flags or s['courseReferenceNumber']not in fresh or credit!=course['credits']or s.get('creditHourHigh')not in [None,credit]or cap<=0:continue
   if meetings(s):good.append(s)
  options[key]=good
 fdict={f['faculty_ID']:f for f in source['Faculty']};appointments=collections.defaultdict(set)
 for a in source['Faculty_Department']:appointments[a['dept_ID']].add(a['faculty_ID'])
 rooms={r['room_ID']:r for r in source['Room']if r['room_Type']in ['Lecture','Lab']};roomcap={r['lecture_ID']:r['avail_Seats']for r in source['Lecture']};roomcap.update({r['lab_ID']:r['no_Of_Workstations']for r in source['Lab']})
 fbusy=collections.defaultdict(list);rbusy=collections.defaultdict(list);fload=collections.Counter();rload=collections.Counter()
 courses={r['course_ID']:r for r in source['Course']}
 for s in source['Course_Section']:
  if s['semester_ID']==tid and courses[s['course_ID']]['course_Type']=='Graduate':
   pat=old_meetings(source,s);fbusy[s['faculty_ID']]+=pat;rbusy[s['room_ID']]+=pat;fload[s['faculty_ID']]+=1;rload[s['room_ID']]+=1
 sections=[];bycourse=collections.defaultdict(list);numbers=collections.Counter();busy=collections.defaultdict(list);chosen=collections.defaultdict(list);ge=collections.defaultdict(set)
 for r in b.ge:
  if not r['lectureLabBundleCandidate']:ge[r['sourceCourseCode']].add(r['domain'])
 programs={p['program']:p for p in b.programs}
 def mandatory(n):
  if n.get('type')=='all-courses':return set(n['courses'])
  return set().union(*(mandatory(g)for g in n.get('groups',[])))if n.get('groups')else set()
 def eligible(sid,key,selected):
  rows=hist[sid]+[{'studentId':sid,'termId':tid,'termCode':term,'courseKey':k,'grade':None,'status':'planned'}for k in selected]
  return Eligibility(c['courses'],rows,b.termpos,tid,people[sid],key,b.source_laboratories).evaluate(b.rules.get(key,{'type':'review-required'}))['status']in ['satisfied','conditional']
 def allocate(sid,key,temporary,pending_loads):
  # Return an existing section or a fully resourced new one. No mutation yet.
  course=c['courses'][key];occupied=busy[sid]+temporary
  existing=[s for s in bycourse[key]if len(s['studentIds'])<s['capacity']and not overlap(s['_meetings'],occupied)]
  if existing:return ('existing',min(existing,key=lambda s:(-len(s['studentIds']),s['deliveryGroup'])))
  routes=[]
  for src in options[key]:
   pat=meetings(src)
   if overlap(pat,occupied):continue
   fs=[fid for fid in appointments[course['departmentId']]if fload[fid]+pending_loads[fid]<(2 if fdict[fid]['faculty_Type']=='Full-Time'else 1)and not overlap(pat,fbusy[fid])]
   rs=[rid for rid,r in rooms.items()if (not course['labBearing']or r['room_Type']=='Lab')and roomcap.get(rid,0)>0 and not overlap(pat,rbusy[rid])]
   if not fs or not rs:continue
   fid=min(fs,key=lambda f:(fload[f],f));rid=min(rs,key=lambda r:(rooms[r]['room_Type']=='Lab'and not course['labBearing'],rload[r],r));cap=min(10,int(src['maximumEnrollment']),int(roomcap[rid]))
   if cap<=0:continue
   routes.append(((fload[fid],rload[rid],len(pat),src['courseReferenceNumber']),src,pat,fid,rid,cap))
  if not routes:return None
  _,src,pat,fid,rid,cap=min(routes,key=lambda r:r[0]);same=src['subject']+src['courseNumber']==canonical(key)and'@'not in key
  return ('new',{'term':term,'termId':tid,'courseKey':key,'departmentId':course['departmentId'],'labBearing':course['labBearing'],'studentIds':[],'capacity':cap,'officialCRN':src['courseReferenceNumber']if same else None,'sourceReferenceCRN':src['courseReferenceNumber'],'sourceCourseCode':src['subject']+src['courseNumber'],'sourceCourseTitle':src['courseTitle'],'sourceSectionLabel':src['sequenceNumber'],'meetings':src['meetings'],'instructionalMethodDescription':src['instructionalMethodDescription'],'sourceRelationship':'same-course-offering'if same else'historical-or-alias-meeting-template','facultyId':fid,'roomId':rid,'_meetings':pat})
 def commit(sid,key,assignment):
  kind,s=assignment
  if kind=='new':
   numbers[key]+=1;s['deliveryGroup']=numbers[key];sections.append(s);bycourse[key].append(s);fbusy[s['facultyId']]+=s['_meetings'];rbusy[s['roomId']]+=s['_meetings'];fload[s['facultyId']]+=1;rload[s['roomId']]+=1
  s['studentIds'].append(sid);busy[sid]+=s['_meetings'];chosen[sid].append(key)
 # Learners with the least prerequisite flexibility choose first; fulfill all
 # minimum loads before distributing fourth courses. Keep requirements ahead of GE.
 order=sorted(active,key=lambda sid:(len(hist[sid]),stable((people[sid]['major'],sid))))
 for phase in [0,1]:
  for sid in order:
   person=people[sid];done={a['courseKey']for a in hist[sid]};target=set(person['targetCourses']);pool=(target|set(ge))&set(c['courses']);core=set().union(*(mandatory(n)for n in programs[person['major']]['groups']));unlock=collections.Counter(k for x in target for k in b.prior.get(x,set()))
   if person['major']in ['Computer and Information Science, B.S.','Management Information Systems, B.S.']and'CS2510'not in done:unlock['MA1020']+=100;unlock['CS2510']+=50
   maximum=16 if person['mode']=='Full-Time'else 8;goal=(12 if person['mode']=='Full-Time'else 4)if phase==0 else maximum
   while sum(c['courses'][k]['credits']for k in chosen[sid])<goal:
    covered=set().union(*(ge.get(k,set())for k in done|set(chosen[sid])));ranked=[]
    for key in pool-done-set(chosen[sid]):
     bundle=({key}|coreq.get(key,set()))-done-set(chosen[sid])
     for k in list(bundle):bundle|=coreq.get(k,set())-done-set(chosen[sid])
     if not bundle<=pool or any(not options[k]for k in bundle)or sum(c['courses'][k]['credits']for k in chosen[sid]+list(bundle))>maximum:continue
     if not all(eligible(sid,k,chosen[sid]+list(bundle))for k in bundle):continue
     rank=(-sum(unlock[k]for k in bundle),-len(bundle&core),-len(bundle&target),-len(set().union(*(ge.get(k,set())for k in bundle))-covered),-sum(bool(bycourse[k])for k in bundle),stable((person['major'],key)),key)
     ranked.append((rank,bundle))
    picked=None
    for _,bundle in sorted(ranked,key=lambda r:r[0]):
     assignments=[];temporary=[];pending_loads=collections.Counter()
     for key in sorted(bundle):
      assignment=allocate(sid,key,temporary,pending_loads)
      if not assignment:break
      assignments.append((key,assignment));temporary+=assignment[1]['_meetings']
      if assignment[0]=='new':pending_loads[assignment[1]['facultyId']]+=1
     if len(assignments)!=len(bundle):continue
     # New sections in the same corequisite bundle must not share an occupied
     # faculty/room interval. The student nonoverlap already separates their times.
     picked=assignments;break
    if picked is None:break
    for key,assignment in picked:commit(sid,key,assignment)
  print('Spring phase',phase+1,'rosters',sum(len(s['studentIds'])for s in sections),'sections',len(sections),flush=True)
 for sid in active:hist[sid]+=[{'studentId':sid,'termId':tid,'termCode':term,'courseKey':key,'grade':None,'status':'planned'}for key in chosen[sid]]
 c['attempts']=[a for rows in hist.values()for a in rows];c['rosters'][term]={key:[sid for s in ss for sid in s['studentIds']]for key,ss in bycourse.items()if ss}
 for s in sections:s.pop('_meetings')
 schedule=copy.deepcopy(fall);schedule['sections']=[s for s in fall['sections']if s['term']=='202609']+sections;schedule['scheduledTerms']=['202609','202702'];schedule['expectedTerms']=['202609','202702'];schedule['failures']=[];schedule['status']='candidate-scheduled';schedule['activationAllowed']=False;schedule['candidateSHA256']=hashlib.sha256(json.dumps(c,sort_keys=True).encode()).hexdigest()
 loads={sid:sum(c['courses'][k]['credits']for k in chosen[sid])for sid in active};under=[sid for sid in active if loads[sid]<(12 if people[sid]['mode']=='Full-Time'else 4)]
 if under:schedule['status']='candidate-blocked';schedule['failures']=[{'term':term,'stage':'minimum-credit-load','students':len(under)}]
 summary={'status':schedule['status'],'activeStudents':len(active),'springSections':len(sections),'springAttempts':sum(len(s['studentIds'])for s in sections),'underloadedStudents':len(under),'excludedLinkedOrCrossListedSpringSections':len(flags),'schemaChanged':False,'databaseChanged':False,'activationAllowed':False}
 for name,data in [('candidate.json',c),('schedule.json',schedule),('summary.json',summary)]: (out/name).write_text(json.dumps(data,indent=2)+'\n')
 print(json.dumps(summary),flush=True)
if __name__=='__main__':
 p=argparse.ArgumentParser();p.add_argument('--source',required=True);p.add_argument('--candidate',required=True);p.add_argument('--fall-schedule',required=True);p.add_argument('--output',required=True);a=p.parse_args()
 for v in [a.source,a.candidate,a.fall_schedule,a.output]:
  if not str(Path(v).resolve()).startswith('/private/tmp/'):raise SystemExit('Private paths required')
 os.umask(0o077);out=Path(a.output);out.mkdir(mode=0o700,parents=True,exist_ok=False)
 run(json.loads(Path(a.source).read_text()),json.loads(Path(a.candidate).read_text()),json.loads(Path(a.fall_schedule).read_text()),out)
