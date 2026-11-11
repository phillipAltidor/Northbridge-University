#!/usr/bin/env python3
"""Repair sparse reconstructed terms with validated coursework moves, not waivers."""
import argparse,collections,copy,hashlib,json,os,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];sys.path.insert(0,str(ROOT/'database/generation'))
from rebuild_oldwestbury import Builder,canonical
from rebuild_eligibility import Eligibility
from academic_meetings import meetings,overlap,old_meetings
from academic_planning import PlanningCatalog,source_sequences
from in_person_sections import template_options,target_term_templates,RELATIONSHIP
from faculty_planning import cross_appointments,rebalance_faculty

class Repair:
 def __init__(self,source,candidate,schedule):
  self.source=source;self.c=candidate;self.s=schedule;self.b=Builder(source);self.b.enable_source_proof_planning();self.b.enable_project_policies();self.b.rules['MA1020']=candidate['entryPlacementPolicy']['rule']
  self.people={p['studentId']:p for p in candidate['students']};self.fac={p['faculty_ID']:p for p in source['Faculty']};self.rooms={p['room_ID']:p for p in source['Room']if p['room_Type']in ['Lecture','Lab']};self.cap={r['lecture_ID']:r['avail_Seats']for r in source['Lecture']};self.cap.update({r['lab_ID']:r['no_Of_Workstations']for r in source['Lab']})
  self.apps=collections.defaultdict(set)
  candidate['facultyCrossAppointments']=cross_appointments(source)
  for a in source['Faculty_Department']+candidate.get('facultyCrossAppointments',[]):self.apps[a['dept_ID']].add(a['faculty_ID'])
  self.termids={r['termCode']:r['termId']for r in candidate['attempts']};self.rows=collections.defaultdict(list)
  for r in candidate['attempts']:self.rows[r['studentId']].append(r)
  self.raw={};self.flags={};self.options_cache={};self.patterns={};self.resource_cache={};self.staffing_cache={};self.events=copy.deepcopy(candidate.get('historicalLoadRepairs',[]))
  for entry in self.b.source_register:self.raw[entry['term']]=json.loads((ROOT/entry['path']).read_text())['sections']
  metadata={'202609':sum([json.load(open(p))['data']for p in ['/Users/phillipaltidor/Downloads/searchResults.json','/Users/phillipaltidor/Downloads/searchResults (1).json']],[]),'202702':json.load(open(ROOT/'database/staging/academic-alignment/spring-2027-link-capacity-review.json'))['sections']}
  for term,rr in metadata.items():self.flags[term]={str(r['courseReferenceNumber'])for r in rr if r.get('isSectionLinked')or r.get('crossList')or(r.get('crossListCapacity')or 0)>0}
  self.sequences=source_sequences(json.load(open(ROOT/'database/staging/academic-alignment/program-review-inputs.json')))
 def load(self,sid,term):return sum(self.c['courses'][a['courseKey']]['credits']for a in self.rows[sid]if a['termCode']==term)
 def minimum(self,sid):return 12 if self.people[sid]['mode']=='Full-Time'else 4
 def maximum(self,sid):return 16 if self.people[sid]['mode']=='Full-Time'else 8
 def pattern(self,sec):
  key=id(sec)
  if key not in self.patterns:self.patterns[key]=meetings(sec)
  return self.patterns[key]
 def busy(self,sid,term):return [m for s in self.s['sections']if s['term']==term and sid in s['studentIds']for m in self.pattern(s)]
 def resources(self,term):
  if term in self.resource_cache:return self.resource_cache[term]
  fb=collections.defaultdict(list);rb=collections.defaultdict(list);fl=collections.Counter();rl=collections.Counter()
  for s in self.s['sections']:
   if s['term']!=term:continue
   fb[s['facultyId']]+=self.pattern(s);rb[s['roomId']]+=self.pattern(s);fl[s['facultyId']]+=1;rl[s['roomId']]+=1
  oldcourses={r['course_ID']:r for r in self.source['Course']}
  for s in self.source['Course_Section']:
   if s['semester_ID']==self.termids[term]and oldcourses[s['course_ID']]['course_Type']=='Graduate':
    pat=old_meetings(self.source,s);fb[s['faculty_ID']]+=pat;rb[s['room_ID']]+=pat;fl[s['faculty_ID']]+=1;rl[s['room_ID']]+=1
  value=(fb,rb,fl,rl);self.resource_cache[term]=value;return value
 def options(self,term,key):
  if(term,key)in self.options_cache:return self.options_cache[term,key]
  course=self.c['courses'][key];excluded=self.flags.get(term,set());rr=[]
  for row in self.b.sources(term,key):
   credit=row.get('creditHours')if row.get('creditHours')is not None else row.get('creditHourLow')
   if str(row['courseReferenceNumber'])not in excluded and credit==course['credits']and(row.get('maximumEnrollment')or 0)>0 and meetings(row):rr.append(row)
  # An official offering can exist yet be unusable because every seat, meeting
  # or teaching assignment is unavailable. Keep it first, then try the already
  # approved explicitly fictional Northbridge alternatives.
  rr+=template_options(course,self.raw[term],self.c['courses'],excluded,180 if term<'202609'else 200)
  if not rr and not any(r.get('instructionalMethodDescription')=='On Campus'for r in self.raw[term]):
   for other in sorted(self.raw,key=lambda t:(t>term,abs(int(t)-int(term)))):
    if other==term:continue
    rr=template_options(course,target_term_templates(self.raw[other],self.raw[term],other),self.c['courses'],self.flags.get(other,set()),180 if term<'202609'else 200)
    if rr:break
  self.options_cache[term,key]=rr;return rr
 def allocation(self,sid,key,term):
  occupied=self.busy(sid,term);course=self.c['courses'][key]
  existing=[s for s in self.s['sections']if s['term']==term and s['courseKey']==key and len(s['studentIds'])<s['capacity']and not overlap(self.pattern(s),occupied)]
  if existing:return False,max(existing,key=lambda s:len(s['studentIds']))
  fb,rb,fl,rl=self.resources(term)
  for row in self.options(term,key):
   pat=meetings(row)
   if overlap(pat,occupied):continue
   fs=[f for f in self.apps[course['departmentId']]if fl[f]<(2 if self.fac[f]['faculty_Type']=='Full-Time'else 1)and not overlap(pat,fb[f])]
   rs=[r for r in self.rooms if(not course['labBearing']or self.rooms[r]['room_Type']=='Lab')and self.cap.get(r,0)>0 and not overlap(pat,rb[r])]
   if not rs:continue
   reassigned=None;term_sections=[s for s in self.s['sections']if s['term']==term]
   if not fs:
    cache_key=(term,course['departmentId'],tuple(pat))
    if cache_key not in self.staffing_cache:self.staffing_cache[cache_key]=rebalance_faculty(self.source,term_sections,{'termId':self.termids[term],'departmentId':course['departmentId'],'meetings':row['meetings']},self.apps,seconds=1)
    reassigned=self.staffing_cache[cache_key]
    if reassigned is None:continue
   fid=reassigned[-1]if reassigned else min(fs,key=lambda f:(fl[f],self.fac[f]['faculty_Type']!='Part-Time',f));rid=min(rs,key=lambda r:(self.rooms[r]['room_Type']=='Lab'and not course['labBearing'],rl[r],r));custom=row.get('northbridgeOnly',False);same=not custom and row['subject']+row['courseNumber']==canonical(key)and'@'not in key
   sec={'term':term,'termId':self.termids[term],'courseKey':key,'departmentId':course['departmentId'],'labBearing':course['labBearing'],'studentIds':[],'capacity':min(10,row['maximumEnrollment'],self.cap[rid]),'officialCRN':row['courseReferenceNumber']if same else None,'sourceReferenceCRN':row['courseReferenceNumber'],'sourceCourseCode':row['subject']+row['courseNumber'],'sourceCourseTitle':row['courseTitle'],'sourceSectionLabel':'NB-IP'if custom else row['sequenceNumber'],'sourceTemplateSectionLabel':row['sequenceNumber'],'sourceTemplateTerm':row.get('templateSourceTerm',term),'sourceOriginalMeetings':row.get('sourceOriginalMeetings',row['meetings']),'meetings':row['meetings'],'instructionalMethodDescription':'On Campus','sourceRelationship':RELATIONSHIP if custom else'same-course-offering'if same else'historical-or-alias-meeting-template','facultyId':fid,'roomId':rid,'deliveryGroup':max([s['deliveryGroup']for s in self.s['sections']if s['term']==term and s['courseKey']==key]+[0])+1}
   if reassigned:sec['_facultyReassignments']=[(s,f)for s,f in zip(term_sections,reassigned)if s['facultyId']!=f]
   return True,sec
  return None
 def valid(self,sid,rows):
  for a in rows:
   state=Eligibility(self.c['courses'],rows,self.b.termpos,a['termId'],self.people[sid],a['courseKey'],self.b.source_laboratories).evaluate(self.b.rules.get(a['courseKey'],{'type':'review-required'}))['status']
   if state not in(['satisfied']if a['status']=='completed'else['satisfied','conditional']):return False
  return True
 def compact_sections(self,term):
  """Free teaching slots by packing the same course into compatible open seats."""
  changed=True
  while changed:
   changed=False
   for donor in sorted([s for s in self.s['sections']if s['term']==term],key=lambda s:len(s['studentIds'])):
    targets=[s for s in self.s['sections']if s is not donor and s['term']==term and s['courseKey']==donor['courseKey']and len(s['studentIds'])<s['capacity']]
    if sum(s['capacity']-len(s['studentIds'])for s in targets)<len(donor['studentIds']):continue
    options={}
    for sid in donor['studentIds']:
     occupied=[m for s in self.s['sections']if s is not donor and s['term']==term and sid in s['studentIds']for m in self.pattern(s)]
     options[sid]=[i for i,s in enumerate(targets)if sid not in s['studentIds']and not overlap(self.pattern(s),occupied)]
    if any(not choices for choices in options.values()):continue
    order=sorted(options,key=lambda sid:len(options[sid]));space=[s['capacity']-len(s['studentIds'])for s in targets];assignment={}
    def pack(index):
     if index==len(order):return True
     sid=order[index]
     for i in options[sid]:
      if not space[i]:continue
      space[i]-=1;assignment[sid]=i
      if pack(index+1):return True
      space[i]+=1;assignment.pop(sid,None)
     return False
    if not pack(0):continue
    for sid,i in assignment.items():targets[i]['studentIds'].append(sid)
    self.s['sections'].remove(donor);self.resource_cache.pop(term,None);self.staffing_cache.clear();self.patterns.clear()
    self.events.append({'term':term,'courseKey':donor['courseKey'],'kind':'same-course-sections-consolidated','removedDeliveryGroup':donor['deliveryGroup'],'studentIds':list(donor['studentIds']),'targetDeliveryGroups':{str(sid):targets[i]['deliveryGroup']for sid,i in assignment.items()},'attemptsAndGradesUnchanged':True})
    changed=True;break
 def add_pending(self,sid,term):
  """Fill a current/future gap only with a justified, eligible degree choice."""
  rows=self.rows[sid];person=self.people[sid]
  reserved={a['courseKey']for a in rows if a['termCode']!=term and(a['status']!='completed'or a['grade']not in ['F','NC','I','W','NR'])}
  selected=[a['courseKey']for a in rows if a['termCode']==term]
  planner=PlanningCatalog(self.b.programs,self.c['courses'],self.b.rules,self.b.ge,self.sequences)
  context=Eligibility(self.c['courses'],rows,self.b.termpos,self.termids[term],person,'planning',self.b.source_laboratories)
  choices=planner.choices(person,reserved,selected,lambda n:context.evaluate(n)['status'])
  roles={p['courseKey']:p for p in self.c['academicPlanningPurposes']if p['studentId']==sid and p['term']==term}
  for key,role in sorted(choices.items(),key=lambda item:(item[1]['priority'],item[0])):
   bundle=set(role['bundle'])|set(self.c['corequisiteEdges'].get(key,[]))|{key}
   if bundle-reserved-set(selected)!={key}:continue
   if planner.redundant_preparation({key},choices,selected,roles):continue
   if self.load(sid,term)+self.c['courses'][key]['credits']>self.maximum(sid):continue
   attempt={'studentId':sid,'termCode':term,'termId':self.termids[term],'courseKey':key,'grade':None,'status':'in-progress'if term=='202609'else'planned'}
   if not self.valid(sid,rows+[attempt]):continue
   target=self.allocation(sid,key,term)
   if not target:continue
   isnew,sec=target
   for existing,fid in sec.pop('_facultyReassignments',[]):
    self.events.append({'term':term,'courseKey':existing['courseKey'],'deliveryGroup':existing['deliveryGroup'],'kind':'faculty-rebalanced-within-reviewed-appointments','previousFacultyId':existing['facultyId'],'facultyId':fid});existing['facultyId']=fid
   if isnew:self.s['sections'].append(sec)
   sec['studentIds'].append(sid);self.rows[sid]=rows+[attempt]
   self.resource_cache.pop(term,None);self.patterns.clear();self.staffing_cache.clear()
   self.c['academicPlanningPurposes'].append({'studentId':sid,'term':term,'courseKey':key,**role})
   self.events.append({'studentId':sid,'term':term,'courseKey':key,'kind':'pending-degree-requirement-added','role':role['role'],'gradeAssigned':False})
   return True
  return False
 def exchange_seat(self,sid,a,term):
  """Relocate an above-minimum historical occupant to free an existing seat."""
  key=a['courseKey'];credit=self.c['courses'][key]['credits']
  changed={**a,'termCode':term,'termId':self.termids[term]}
  if term>='202609':changed.update(grade=None,status='in-progress'if term=='202609'else'planned')
  if key in ['EL1000','EL2216']or self.load(sid,term)+credit>self.maximum(sid):return False
  if not self.valid(sid,[changed if row is a else row for row in self.rows[sid]]):return False
  for sec in list(self.s['sections']):
   if sec['term']!=term or sec['courseKey']!=key or overlap(self.pattern(sec),self.busy(sid,term)):continue
   for other in list(sec['studentIds']):
    if other==sid or self.load(other,term)-credit<self.minimum(other):continue
    old=next(row for row in self.rows[other]if row['termCode']==term and row['courseKey']==key)
    if old['status']!='completed':continue
    for destination in sorted(self.c['reconstructionTermHorizon'][str(other)],reverse=True):
     if destination==term or destination>='202609':continue
     if self.load(other,destination)+credit>self.maximum(other):continue
     saved_c=copy.deepcopy(self.c);saved_s=copy.deepcopy(self.s);saved_rows=copy.deepcopy(self.rows[other]);saved_events=len(self.events)
     # Reserve the outgoing student's old seat for an actual two-way exchange.
     # Attempts stay intact until both chronology checks and allocations pass.
     original_section=None
     for original in self.s['sections']:
      if original['term']==a['termCode']and original['courseKey']==key and sid in original['studentIds']:original['studentIds'].remove(sid);original_section=original;break
     self.resource_cache.clear();self.staffing_cache.clear()
     self.retain_empty_sections=True
     try:moved_other=self.move(other,old,destination)
     finally:self.retain_empty_sections=False
     if not moved_other:
      if original_section is not None:original_section['studentIds'].append(sid)
      self.resource_cache.clear();self.staffing_cache.clear();continue
     if self.move(sid,a,term):
      self.events.append({'term':term,'courseKey':key,'kind':'historical-seat-freed-by-validated-relocation','displacedStudentMinimumPreserved':True});return True
     self.c=saved_c;self.s=saved_s;self.rows[other]=saved_rows;del self.events[saved_events:];self.resource_cache.clear();self.patterns.clear();self.staffing_cache.clear()
     # Rebind references after restoring copied section and attempt objects.
     return False
  return False
 def move(self,sid,a,destination):
  old=a['termCode'];key=a['courseKey']
  if key in ['EL1000','EL2216']or any(r['courseKey']==key and r['termCode']==destination for r in self.rows[sid]):return False
  if self.load(sid,destination)+self.c['courses'][key]['credits']>self.maximum(sid):return False
  changed={**a,'termCode':destination,'termId':self.termids[destination]}
  if destination>='202609':changed.update({'grade':None,'status':'in-progress'if destination=='202609'else'planned'})
  elif a['grade']is None:return False
  changed['gradeProvenance']='reconstructed-course-relocated-with-original-grade-retained'if destination<'202609'else'pending-course-relocated-from-reconstructed-history-no-final-grade'
  newrows=[changed if r is a else r for r in self.rows[sid]]
  if not self.valid(sid,newrows):return False
  target=self.allocation(sid,key,destination)
  if not target:return False
  for sec in self.s['sections']:
   if sec['term']==old and sec['courseKey']==key and sid in sec['studentIds']:sec['studentIds'].remove(sid);break
  isnew,sec=target
  for existing,fid in sec.pop('_facultyReassignments',[]):
   self.events.append({'term':destination,'courseKey':existing['courseKey'],'deliveryGroup':existing['deliveryGroup'],'kind':'faculty-rebalanced-within-reviewed-appointments','previousFacultyId':existing['facultyId'],'facultyId':fid});existing['facultyId']=fid
  if isnew:self.s['sections'].append(sec)
  sec['studentIds'].append(sid);self.rows[sid]=newrows
  if not getattr(self,'retain_empty_sections',False):self.s['sections']=[s for s in self.s['sections']if s['studentIds']]
  self.patterns.clear()
  self.resource_cache.pop(old,None);self.resource_cache.pop(destination,None);self.staffing_cache.clear()
  for p in self.c['academicPlanningPurposes']:
   if p['studentId']==sid and p['term']==old and p['courseKey']==key:p['term']=destination;p['reason']+='; validated chronological relocation from '+old;break
  self.events.append({'studentId':sid,'courseKey':key,'from':old,'to':destination,'kind':'validated-course-relocation','gradePersisted':destination<'202609'});return True
 def run(self):
  for term in sorted(self.c['plannedTermStudents']):
   if any(self.load(sid,term)<self.minimum(sid)for sid in self.c['plannedTermStudents'][term]):self.compact_sections(term)
   for sid in list(self.c['plannedTermStudents'][term]):
    if self.load(sid,term)<self.minimum(sid):print(json.dumps({'stage':'repairing-load-gap','term':term,'credits':self.load(sid,term),'minimum':self.minimum(sid)}),flush=True)
    while self.load(sid,term)<self.minimum(sid):
     moved=False
     donors=sorted([a for a in self.rows[sid]if a['termCode']<term and a['status']=='completed'],key=lambda a:(-int(a['termCode']),self.c['courses'][a['courseKey']]['credits']))
     for a in donors:
      credit=self.c['courses'][a['courseKey']]['credits']
      if self.load(sid,a['termCode'])-credit<self.minimum(sid):continue
      if self.move(sid,a,term):moved=True;break
     if not moved:
      for a in donors:
       if self.load(sid,a['termCode'])-self.c['courses'][a['courseKey']]['credits']<self.minimum(sid):continue
       if self.exchange_seat(sid,a,term):moved=True;break
     if not moved and term>='202609':moved=self.add_pending(sid,term)
     if not moved:break
    if term>='202609' or self.load(sid,term)>=self.minimum(sid):continue
    # Entire sparse historical terms can be consolidated into other historical
    # semesters inside the original horizon, only if all prerequisites and caps
    # remain valid. No current/future course is given a historical grade.
    if self.load(sid,term)>0:
     saved_c=copy.deepcopy(self.c);saved_s=copy.deepcopy(self.s);saved_rows=copy.deepcopy(self.rows);saved_events=len(self.events)
     complete=True
     for key in [a['courseKey']for a in self.rows[sid]if a['termCode']==term]:
      a=next(a for a in self.rows[sid]if a['courseKey']==key and a['termCode']==term)
      destinations=sorted([t for t in self.c['reconstructionTermHorizon'][str(sid)]if t!=term and t<'202609'],key=lambda t:(t>term,abs(int(t)-int(term))))
      if not any(self.move(sid,a,t)or self.exchange_seat(sid,a,t)for t in destinations):complete=False;break
     if not complete:
      self.c=saved_c;self.s=saved_s;self.rows=saved_rows;del self.events[saved_events:];self.resource_cache.clear();self.patterns.clear();self.staffing_cache.clear()
    if self.load(sid,term)==0:
     self.c['plannedTermStudents'][term].remove(sid);self.events.append({'studentId':sid,'term':term,'kind':'empty-historical-enrollment-removed','basis':'No reconstructed academic enrollment remains; this is not a graduation award or prerequisite waiver.'})
  self.c['attempts']=[a for rr in self.rows.values()for a in rr]
  expected=collections.Counter((a['termCode'],a['studentId'],a['courseKey'])for a in self.c['attempts'])
  actual=collections.Counter((s['term'],sid,s['courseKey'])for s in self.s['sections']for sid in s['studentIds'])
  if expected!=actual:raise ValueError('Repair roster/attempt coverage mismatch; candidate cannot be emitted.')
  self.c['historicalLoadRepairs']=self.events;self.c['academicPlanningApproved']=False
  self.c['rosters']={term:{}for term in self.c['plannedTermStudents']}
  for sec in self.s['sections']:self.c['rosters'][sec['term']].setdefault(sec['courseKey'],[]).extend(sec['studentIds'])
  failures=[]
  for term,ids in self.c['plannedTermStudents'].items():
   count=sum(self.load(sid,term)<self.minimum(sid)for sid in ids)
   if count:failures.append({'term':term,'stage':'minimum-credit-load','students':count})
  self.s['failures']=failures;self.s['status']='candidate-blocked'if failures else'candidate-scheduled';self.s['candidateSHA256']=hashlib.sha256(json.dumps(self.c,sort_keys=True).encode()).hexdigest()
  return self.c,self.s,{'status':self.s['status'],'remainingLoadFailures':failures,'repairEvents':len(self.events),'schemaChanged':False,'databaseChanged':False,'activationAllowed':False}

if __name__=='__main__':
 p=argparse.ArgumentParser();p.add_argument('--source',required=True);p.add_argument('--candidate-directory',required=True);p.add_argument('--output',required=True);a=p.parse_args()
 for v in vars(a).values():
  if not str(Path(v).resolve()).startswith('/private/tmp/'):raise SystemExit('Private paths required')
 os.umask(0o077);out=Path(a.output);out.mkdir(mode=0o700,exist_ok=False);d=Path(a.candidate_directory)
 c,s,summary=Repair(json.load(open(a.source)),json.load(open(d/'candidate.json')),json.load(open(d/'schedule.json'))).run()
 for name,value in [('candidate.json',c),('schedule.json',s),('summary.json',summary)]:(out/name).write_text(json.dumps(value,indent=2)+'\n')
 print(json.dumps(summary))
