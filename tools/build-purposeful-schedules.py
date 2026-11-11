#!/usr/bin/env python3
"""Construct course choices and resource assignments jointly, in private staging."""
import argparse,collections,copy,hashlib,heapq,json,os,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];sys.path.insert(0,str(ROOT/'database/generation'))
from rebuild_oldwestbury import Builder,canonical,stable
from rebuild_eligibility import Eligibility
from academic_meetings import meetings,overlap,old_meetings
from academic_planning import PlanningCatalog,source_sequences
from rebuild_degree_properties import reviewed_properties
from in_person_sections import template_options,target_term_templates,RELATIONSHIP
from faculty_planning import cross_appointments

def run(source,base,fall,out,term,catalogue):
 c=copy.deepcopy(base)
 for key,value in catalogue.items():c['courses'].setdefault(key,value)
 c['reviewedDegreeProperties']=reviewed_properties(c['courses'])
 for key,course in c['courses'].items():
  facts=c['reviewedDegreeProperties']['courseProperties'].get(key)
  if facts:course.update({k:facts[k]for k in ['upperDivision','level','liberalArts','classifications','labBearing']})
  else:course['liberalArts']=False;course['classifications']=[]
 b=Builder(source);b.enable_source_proof_planning();b.enable_project_policies();b.rules['MA1020']=c['entryPlacementPolicy']['rule'];tid=next(a['termId']for a in c['attempts']if str(a['termCode'])==term)
 b.courses=c['courses'];b.prior={k:set(v)for k,v in c['prerequisiteEdges'].items()};b.prior['MA1020']=set();coreq={k:set(v)for k,v in c['corequisiteEdges'].items()}
 c['foundationPrerequisiteProofs']=b.foundation_proofs
 for lecture,lab in [('BS2400','BS2401'),('BS2410','BS2411')]:coreq[lecture]={lab};coreq[lab]={lecture}
 c['corequisiteEdges']={k:sorted(v)for k,v in coreq.items()}
 active={a['studentId']for a in c['attempts']if str(a['termCode'])==term};people={p['studentId']:p for p in c['students']};hist=collections.defaultdict(list)
 for a in c['attempts']:
  if str(a['termCode'])!=term:hist[a['studentId']].append(a)
 historical=term<'202609'
 old_attempts=collections.defaultdict(list)
 for a in c['attempts']:
  if str(a['termCode'])==term:old_attempts[a['studentId']].append(a)
 if c.get('historicalRebuildPolicy'):
  from historical_planning import refresh_writing_eligibility
  refresh_writing_eligibility(c,hist,b.termpos,tid,term)
 filename='official-sections-fall-2026.json'if term=='202609'else'official-sections-'+term+'.json'
 template_rows=json.loads((ROOT/'database/staging/academic-alignment'/filename).read_text())['sections']
 borrowed_templates=[]
 if not any(r.get('instructionalMethodDescription')=='On Campus'for r in template_rows):
  for entry in sorted(b.source_register,key=lambda r:(r['term']>term,abs(int(r['term'])-int(term)))):
   if entry['term']==term:continue
   rows=json.loads((ROOT/entry['path']).read_text())['sections']
   borrowed_templates=target_term_templates(rows,template_rows,entry['term'])
   if borrowed_templates:break
 if term=='202609':
  metadata_rows=sum([json.loads(Path(p).read_text())['data']for p in ['/Users/phillipaltidor/Downloads/searchResults.json','/Users/phillipaltidor/Downloads/searchResults (1).json']],[])
 elif term=='202702':metadata_rows=json.loads((ROOT/'database/staging/academic-alignment/spring-2027-link-capacity-review.json').read_text())['sections']
 else:metadata_rows=template_rows
 flags={r['courseReferenceNumber']for r in metadata_rows if r.get('isSectionLinked')or r.get('crossList')or(r.get('crossListCapacity')or 0)>0};fresh={r['courseReferenceNumber']:r for r in metadata_rows}
 policy=json.loads((ROOT/'database/staging/academic-alignment/northbridge-in-person-section-policy.json').read_text())
 c['northbridgeInPersonSectionPolicy']=policy
 options={}
 for key,course in c['courses'].items():
  good=[]
  for s in b.sources(term,key):
   credit=s.get('creditHours')if s.get('creditHours')is not None else s.get('creditHourLow');cap=s.get('maximumEnrollment')or 0
   if s['courseReferenceNumber']in flags or s['courseReferenceNumber']not in fresh or credit!=course['credits']or s.get('creditHourHigh')not in [None,credit]or cap<=0:continue
   if meetings(s):good.append(s)
  options[key]=good or template_options(course,template_rows or borrowed_templates,c['courses'],{str(x)for x in flags},180 if historical else 200)
  if not options[key]and borrowed_templates:options[key]=template_options(course,borrowed_templates,c['courses'],set(),180 if historical else 200)
 fdict={f['faculty_ID']:f for f in source['Faculty']};appointments=collections.defaultdict(set)
 for a in source['Faculty_Department']:appointments[a['dept_ID']].add(a['faculty_ID'])
 if c.get('historicalRebuildPolicy'):
  c['facultyCrossAppointments']=cross_appointments(source)
  for a in c['facultyCrossAppointments']:appointments[a['dept_ID']].add(a['faculty_ID'])
 rooms={r['room_ID']:r for r in source['Room']if r['room_Type']in ['Lecture','Lab']};roomcap={r['lecture_ID']:r['avail_Seats']for r in source['Lecture']};roomcap.update({r['lab_ID']:r['no_Of_Workstations']for r in source['Lab']})
 fbusy=collections.defaultdict(list);rbusy=collections.defaultdict(list);fload=collections.Counter();rload=collections.Counter()
 courses={r['course_ID']:r for r in source['Course']}
 for s in source['Course_Section']:
  if s['semester_ID']==tid and courses[s['course_ID']]['course_Type']=='Graduate':
   pat=old_meetings(source,s);fbusy[s['faculty_ID']]+=pat;rbusy[s['room_ID']]+=pat;fload[s['faculty_ID']]+=1;rload[s['room_ID']]+=1
 planner=PlanningCatalog(b.programs,c['courses'],b.rules,b.ge,source_sequences(json.loads((ROOT/'database/staging/academic-alignment/program-review-inputs.json').read_text())),{k for k,rows in options.items()if rows})
 purposes=[];chosen_roles=collections.defaultdict(dict)
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
   fid=min(fs,key=lambda f:(fload[f],fdict[f]['faculty_Type']!='Part-Time',f));rid=min(rs,key=lambda r:(rooms[r]['room_Type']=='Lab'and not course['labBearing'],rload[r],r));cap=min(10,int(src['maximumEnrollment']),int(roomcap[rid]))
   if cap<=0:continue
   routes.append(((fload[fid],rload[rid],len(pat),src['courseReferenceNumber']),src,pat,fid,rid,cap))
  if not routes:return None
  _,src,pat,fid,rid,cap=min(routes,key=lambda r:r[0]);custom=src.get('northbridgeOnly',False);same=not custom and src['subject']+src['courseNumber']==canonical(key)and'@'not in key
  return ('new',{'term':term,'termId':tid,'courseKey':key,'departmentId':course['departmentId'],'labBearing':course['labBearing'],'studentIds':[],'capacity':cap,'officialCRN':src['courseReferenceNumber']if same else None,'sourceReferenceCRN':src['courseReferenceNumber'],'sourceCourseCode':src['subject']+src['courseNumber'],'sourceCourseTitle':src['courseTitle'],'sourceSectionLabel':'NB-IP'if custom else src['sequenceNumber'],'sourceTemplateSectionLabel':src['sequenceNumber'],'sourceTemplateTerm':src.get('templateSourceTerm',term),'sourceOriginalMeetings':src.get('sourceOriginalMeetings',src['meetings']),'meetings':src['meetings'],'instructionalMethodDescription':src['instructionalMethodDescription'],'sourceRelationship':RELATIONSHIP if custom else'same-course-offering'if same else'historical-or-alias-meeting-template','facultyId':fid,'roomId':rid,'_meetings':pat})
 def commit(sid,key,assignment):
  kind,s=assignment
  if kind=='new':
   numbers[key]+=1;s['deliveryGroup']=numbers[key];sections.append(s);bycourse[key].append(s);fbusy[s['facultyId']]+=s['_meetings'];rbusy[s['roomId']]+=s['_meetings'];fload[s['facultyId']]+=1;rload[s['roomId']]+=1
  s['studentIds'].append(sid);busy[sid]+=s['_meetings'];chosen[sid].append(key)
 # Count useful, eligible courses with reviewed offerings. Students near the
 # end of a program often have fewer useful choices than incoming students.
 # Giving novices all scarce seats first can strand those constrained plans.
 def planning_priority(sid):
  person=people[sid];reserved={a['courseKey']for a in hist[sid]if str(a['termCode'])<term and(a['status']!='completed'or a['grade']not in ['F','NC','I','W','NR'])}
  context=Eligibility(c['courses'],hist[sid],b.termpos,tid,person,'planning',b.source_laboratories)
  choices=planner.choices(person,reserved,chosen[sid],lambda n:context.evaluate(n)['status']);useful=0;departments=set()
  for key,role in choices.items():
   bundle=(set(role['bundle'])|{key}|coreq.get(key,set()))-reserved-set(chosen[sid])
   if all(options.get(k)for k in bundle)and all(eligible(sid,k,chosen[sid]+list(bundle))for k in bundle):useful+=1;departments|={c['courses'][k]['departmentId']for k in bundle}
  return ((len(departments),useful),-sum(c['courses'][a['courseKey']]['credits']for a in hist[sid]if str(a['termCode'])<term),stable((people[sid]['major'],sid)))
 for phase in [0,1]:
  queue=[(*planning_priority(sid),sid)for sid in active];heapq.heapify(queue)
  while queue:
   *_,sid=heapq.heappop(queue)
   person=people[sid];reserved={a['courseKey']for a in hist[sid]if str(a['termCode'])<term and (a['status']!='completed'or a['grade']not in ['F','NC','I','W','NR'])}
   maximum=16 if person['mode']=='Full-Time'else 8
   prior_credits=sum(c['courses'][a['courseKey']]['credits']for a in hist[sid]if str(a['termCode'])<term)
   # Preserve room for a useful final Spring load rather than front-loading
   # every senior to 16 credits in Fall. This is a planning target, not a waiver
   # of any course, graduation condition or enrollment-type minimum.
   target=12 if term=='202609'and person['mode']=='Full-Time'and prior_credits>=88 else maximum
   if c.get('reconstructionTermHorizon'):
    future=sum(t>term for t in c['reconstructionTermHorizon'][str(sid)])
    budget=planner.budgets[sid];required_total=max(budget['minimumTotalCredits'],budget['heldCredits']+budget['outstandingRequiredBound'])
    target=max(12 if person['mode']=='Full-Time'else 4,min(maximum,required_total-prior_credits-future*(12 if person['mode']=='Full-Time'else 4)))
   goal=(12 if person['mode']=='Full-Time'else 4)if phase==0 else target
   if sum(c['courses'][k]['credits']for k in chosen[sid])<goal:
    context=Eligibility(c['courses'],hist[sid],b.termpos,tid,person,'planning',b.source_laboratories)
    choices=planner.choices(person,reserved,chosen[sid],lambda n:context.evaluate(n)['status']);ranked=[]
    for key,role in list(choices.items()):
     bundle=(set(role['bundle'])|{key}|coreq.get(key,set()))-reserved-set(chosen[sid])
     for k in list(bundle):bundle|=coreq.get(k,set())-reserved-set(chosen[sid])
     ceiling=maximum if phase==0 else min(maximum,goal)
     if not bundle<=c['courses'].keys() or any(not options[k]for k in bundle)or sum(c['courses'][k]['credits']for k in chosen[sid]+list(bundle))>ceiling:continue
     for k in bundle:
      if k not in choices:choices[k]={'priority':role['priority'],'role':'required-corequisite','reason':'Required corequisite/component of '+key,'goal':role['goal'],'bundle':[k],'root':key}
     if planner.redundant_preparation(bundle,choices,chosen[sid],chosen_roles[sid]):continue
     if not all(eligible(sid,k,chosen[sid]+list(bundle))for k in bundle):continue
     priority=min(choices[k]['priority']for k in bundle)
     # Broad GE/distribution electives should use available departments before
     # consuming the final teaching slots needed by fixed major requirements.
     if all(choices[k]['role']in ['general-education','degree-distribution','general-elective']for k in bundle):
      for k in bundle:
       if any(len(s['studentIds'])<s['capacity']and not overlap(s['_meetings'],busy[sid])for s in bycourse[k]):continue
       free=sum(max(0,(2 if fdict[f]['faculty_Type']=='Full-Time'else 1)-fload[f])for f in appointments[c['courses'][k]['departmentId']])
       priority+=max(0,12-free)*5
     rank=(priority,-len(set().union(*(ge.get(canonical(k),set())for k in bundle))-set().union(*(ge.get(canonical(k),set())for k in reserved|set(chosen[sid])))),-sum(any(len(s['studentIds'])<s['capacity']for s in bycourse[k])for k in bundle),stable((person['major'],key)),key)
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
    if picked is None:continue
    for key,assignment in picked:
     commit(sid,key,assignment);chosen_roles[sid][key]=choices[key];purposes.append({'studentId':sid,'term':term,'courseKey':key,**choices[key]})
    if sum(c['courses'][k]['credits']for k in chosen[sid])<goal:heapq.heappush(queue,(*planning_priority(sid),sid))
  print(term,'phase',phase+1,'rosters',sum(len(s['studentIds'])for s in sections),'sections',len(sections),flush=True)
 # A fourth course must not keep another full-time learner below twelve.
 # Exchange an occupied seat only when the donor retains the minimum load
 # and every remaining concurrent prerequisite stays valid.
 seat_exchanges=0
 for sid in sorted(active,key=planning_priority):
  minimum=12 if people[sid]['mode']=='Full-Time'else 4
  while sum(c['courses'][k]['credits']for k in chosen[sid])<minimum:
   person=people[sid];reserved={a['courseKey']for a in hist[sid]if str(a['termCode'])<term and(a['status']!='completed'or a['grade']not in ['F','NC','I','W','NR'])}
   context=Eligibility(c['courses'],hist[sid],b.termpos,tid,person,'planning',b.source_laboratories)
   choices=planner.choices(person,reserved,chosen[sid],lambda n:context.evaluate(n)['status']);exchange=None
   for key,role in sorted(choices.items(),key=lambda r:(r[1]['priority'],r[0])):
    if set(role['bundle'])!={key}or(coreq.get(key,set())-reserved-set(chosen[sid])):continue
    if planner.redundant_preparation({key},choices,chosen[sid],chosen_roles[sid])or not eligible(sid,key,chosen[sid]+[key]):continue
    for sec in bycourse[key]:
     if overlap(sec['_meetings'],busy[sid]):continue
     for donor in list(sec['studentIds']):
      remaining=[k for k in chosen[donor]if k!=key];floor=12 if people[donor]['mode']=='Full-Time'else 4
      if sum(c['courses'][k]['credits']for k in remaining)<floor:continue
      if not all(eligible(donor,k,remaining)for k in remaining):continue
      exchange=(key,role,sec,donor,remaining);break
     if exchange:break
    if exchange:break
   if not exchange:break
   key,role,sec,donor,remaining=exchange
   sec['studentIds'].remove(donor);chosen[donor]=remaining;chosen_roles[donor].pop(key,None)
   busy[donor]=[m for s in sections if donor in s['studentIds']for m in s['_meetings']]
   purposes[:]=[r for r in purposes if not(r['studentId']==donor and r['courseKey']==key)]
   commit(sid,key,('existing',sec));chosen_roles[sid][key]=role;purposes.append({'studentId':sid,'term':term,'courseKey':key,**role});seat_exchanges+=1
 for sid in active:
  same={a['courseKey']:a for a in old_attempts[sid]};pool=[a['grade']for a in old_attempts[sid]if a.get('grade')in ['A','A-','B+','B']]
  if historical and not pool:raise ValueError('Historical grade pool missing; do not invent a grade.')
  for number,key in enumerate(chosen[sid]):
   grade=(same[key]['grade']if key in same else pool[number%len(pool)])if historical else None
   hist[sid].append({'studentId':sid,'termId':tid,'termCode':term,'courseKey':key,'grade':grade,'status':'completed'if historical else'in-progress'if term=='202609'else'planned',**({'gradeProvenance':'same-original-course-term'if key in same else'owner-authorized-fictional-reconstruction-from-original-term-grade-pool'}if historical else{})})
 c['academicPlanningPurposes']=[r for r in c.get('academicPlanningPurposes',[])if r['term']!=term]+purposes
 c.setdefault('academicPlanningPaths',{}).update({str(sid)+':'+label:path for (sid,label),path in planner.decisions.items()})
 c['academicPlanningUnsupported']=sorted(planner.unsupported)
 c['academicPlanningApproved']=False
 c.setdefault('plannedTermStudents',{})[term]=sorted(active)
 c['attempts']=[a for rows in hist.values()for a in rows];c['rosters'][term]={key:[sid for s in ss for sid in s['studentIds']]for key,ss in bycourse.items()if ss}
 for s in sections:s.pop('_meetings')
 schedule=copy.deepcopy(fall);schedule['sections']=[s for s in fall['sections']if s['term']!=term]+sections;schedule['scheduledTerms']=sorted({s['term']for s in schedule['sections']});schedule['expectedTerms']=schedule['scheduledTerms'];schedule['failures']=[];schedule['status']='candidate-scheduled';schedule['activationAllowed']=False;schedule['candidateSHA256']=hashlib.sha256(json.dumps(c,sort_keys=True).encode()).hexdigest()
 loads={sid:sum(c['courses'][k]['credits']for k in chosen[sid])for sid in active};under=[sid for sid in active if loads[sid]<(12 if people[sid]['mode']=='Full-Time'else 4)]
 all_loads=collections.Counter()
 for a in c['attempts']:
  if str(a['termCode'])in schedule['scheduledTerms']:all_loads[str(a['termCode']),a['studentId']]+=c['courses'][a['courseKey']]['credits']
 for check_term,ids in c['plannedTermStudents'].items():
  count=sum(all_loads[check_term,sid]<(12 if people[sid]['mode']=='Full-Time'else 4)for sid in ids)
  if count:schedule['failures'].append({'term':check_term,'stage':'minimum-credit-load','students':count})
 if schedule['failures']:schedule['status']='candidate-blocked'
 summary={'status':schedule['status'],'activeStudents':len(active),'term':term,'sections':len(sections),'attempts':sum(len(s['studentIds'])for s in sections),'underloadedStudents':len(under),'seatExchangesFromAboveMinimumLoads':seat_exchanges,'excludedLinkedOrCrossListedSections':len(flags),'schemaChanged':False,'databaseChanged':False,'activationAllowed':False}
 for name,data in [('candidate.json',c),('schedule.json',schedule),('summary.json',summary)]: (out/name).write_text(json.dumps(data,indent=2)+'\n')
 print(json.dumps(summary),flush=True)
 return c,schedule,summary
if __name__=='__main__':
 p=argparse.ArgumentParser();p.add_argument('--source',required=True);p.add_argument('--candidate',required=True);p.add_argument('--fall-schedule',required=True);p.add_argument('--output',required=True);p.add_argument('--term',required=True);p.add_argument('--catalogue',required=True);a=p.parse_args()
 if not a.term.isdigit()or len(a.term)!=6:raise SystemExit('Explicit source term required')
 for v in [a.source,a.candidate,a.fall_schedule,a.output,a.catalogue]:
  if not str(Path(v).resolve()).startswith('/private/tmp/'):raise SystemExit('Private paths required')
 os.umask(0o077);out=Path(a.output);out.mkdir(mode=0o700,parents=True,exist_ok=False)
 run(json.loads(Path(a.source).read_text()),json.loads(Path(a.candidate).read_text()),json.loads(Path(a.fall_schedule).read_text()),out,a.term,json.loads(Path(a.catalogue).read_text()))
