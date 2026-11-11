#!/usr/bin/env python3
"""Generate a replacement fictional UG academic dataset; never mutate MySQL.
Original people/accounts/graduate academics are protected. No claimed transfer equivalencies.
"""
import argparse,json,re,hashlib,math,collections
from pathlib import Path
from rebuild_rule_interpretations import interpretations,recover_reviewed_entries
from academic_meetings import meetings,overlap
from rebuild_historical_offerings import former_name_templates,credit_bounds
from rebuild_remaining_proofs import proof_rules
from rebuild_source_properties import lab_science_evidence
from rebuild_degree_properties import reviewed_properties
from rebuild_project_policies import project_rules
import datetime as dt
ROOT=Path(__file__).resolve().parents[2]
R=ROOT/'database/staging/academic-alignment'
def read(p):return json.loads(p.read_text())
def stable(x):return int(hashlib.sha256(str(x).encode()).hexdigest()[:12],16)
def termkey(name):
 season,year=name.split();return int(year)*2+(season=='Fall')
def subject(code):return re.match('[A-Z]+',code)[0]
def canonical(code):return code.split('@')[0]
MAJORS=['Computer and Information Science, B.S.','Management Information Systems, B.S.','Business Administration, B.S.','Accounting, B.S.','Mathematics, B.S.','Biological Sciences, B.S.','Physics, B.S.','English, B.A.','Psychology, B.S.','Childhood Education 1-6, B.S.','Public Health, B.S.','Media & Communications, B.A.']
MINORS=['Computer Information Science, Minor','Accounting, Minor','General Business, Minor','Marketing, Minor','Mathematics, Minor','Environmental Studies, Minor','Physics, Minor','Multicultural U.S./ British Literature, Minor','Psychology, Minor','Visual Arts, Minor','Public Health, Minor','Media and Communications, Minor']
POINTS={'A':4,'A-':3.7,'B+':3.3,'B':3,'B-':2.7,'C+':2.3,'C':2,'C-':1.7,'D+':1.3,'D':1,'D-':.7,'F':0}
class Builder:
 def __init__(self,tables):
  self.tables=tables;self.meta={c['courseCode']:dict(c)for c in read(R/'rules/course-metadata.json')['courses']};self.programs=read(R/'rules/curricula.json')['programs'];self.rules={r['courseVersionOrCode']:r['rule']for r in read(R/'rules/all-course-prerequisites.json')['courses']}
  self.raw_rules=dict(self.rules);self.source_proofs={};self.source_laboratories={};self.proof_planning_enabled=False
  self.interpretations=interpretations(self.rules)
  self.interpretations.update(recover_reviewed_entries(self.rules))
  self.rules.update({code:entry['rule']for code,entry in self.interpretations.items()})
  self.histVersions={v['versionID']:v for v in read(R/'rules/approved-historical-course-versions.json')}
  self.cases=read(ROOT/'tests/fixtures/academic/all-programs.json')['cases'];self.ge=read(R/'rules/general-education-mappings.json')['rows'];self.terms=sorted(tables['Semester'],key=lambda t:termkey(t['semester_Name']));self.termpos={t['semester_ID']:i for i,t in enumerate(self.terms)};self.current=self.termpos[1]
  self.offer=collections.defaultdict(list);self.source_register=read(R/'official-history-source-register.json')['terms'];self.freq=collections.Counter()
  for term in self.source_register:
   doc=read(ROOT/term['path']);codes=set()
   for s in doc['sections']:
    # Owner policy: undergraduate rebuild uses verified in-person offerings only.
    # Unknown modalities are excluded; official CRNs and meeting times stay intact.
    if s.get('instructionalMethodDescription') != 'On Campus':continue
    code=s['subject']+s['courseNumber'];self.offer[term['term'],code].append(s);codes.add(code)
   self.freq.update(codes)
  self.aliases={x['currentCode']:x['formerCode']for x in read(R/'rules/alias-evidence.json')['relationships']}
  self.former_templates,self.rejected_former_templates=former_name_templates(self.meta,read(R/'rules/all-course-prerequisites.json')['courses'],self.offer)
  self.aliases.update({code:row['formerCode']for code,row in self.former_templates.items()})
  for code in self.former_templates:self.freq[code]=sum(bool(self.sources(t['term'],code))for t in self.source_register)
  self.courses={};self.prior={};self.coreq={};self.requirements={};self.plans={};self.manual_rules=set()
  self.meeting_cache={};self.feasibility_cache={};self.resource_cache={};self.resource_grad_cache={}
  self.degree_properties={}
  self.graduate_ids={s['student_ID']for s in tables['Student']if s['student_Type']=='Graduate'};self.ug_ids={s['student_ID']for s in tables['Student']if s['student_Type']=='Undergraduate'}
  self.sections={x['CRN']:x for x in tables['Course_Section']};self.oldcourses={x['course_ID']:x for x in tables['Course']};self.students={x['student_ID']:x for x in tables['Student']}
  self.modes={x['student_ID']:('Full-Time'if name.startswith('Full')else'Part-Time')for name in ['Full_Time_Undergraduate','Part_Time_Undergraduate']for x in tables[name]}
  self.minors=collections.defaultdict(list)
  for x in tables['Student_Minor']:self.minors[x['student_ID']].append(x['minor_ID'])
  self.old_by_student=collections.defaultdict(list)
  for x in tables['Enrollment']:self.old_by_student[x['student_ID']].append(x)
  self.active={x['student_ID']for x in tables['Enrollment']if x['semester_ID']in [1,2]}
  self.dept_caps=collections.Counter()
  faculty={x['faculty_ID']:x for x in tables['Faculty']}
  for x in tables['Faculty_Department']:self.dept_caps[x['dept_ID']]+=2 if faculty[x['faculty_ID']]['faculty_Type']=='Full-Time'else 1
  self.planning_caps=self.dept_caps.copy()
  self.grad_load=collections.Counter()
  for x in tables['Course_Section']:
   if self.oldcourses[x['course_ID']]['course_Type']=='Graduate':self.grad_load[x['semester_ID'],self.oldcourses[x['course_ID']]['dept_ID']]+=1
 def dept(self,code):
  s=subject(canonical(code));base={'CS':1,'BU':2,'MA':3,'BS':4,'CP':4,'PX':5,'EL':6,'ML':6,'HI':7,'SY':7,'PY':7,'PE':7,'CR':7,'ED':8,'PH':9,'MC':10,'VA':10,'MD':10,'FY':6}
  if s=='AS':return 10 if re.search('media|video|radio|podcast|television|script|news|communicat|storytell',self.meta[canonical(code)]['sourceTitle'],re.I)else 7
  return base.get(s,7)
 def enable_source_proof_planning(self):
  self.source_proofs=proof_rules(self.raw_rules);self.source_laboratories=lab_science_evidence(self.meta)
  self.rules.update({code:p['rule']for code,p in self.source_proofs.items()});self.proof_planning_enabled=True
  from rebuild_foundation_proofs import foundation_rules
  self.foundation_proofs=foundation_rules(self.raw_rules)
  self.rules.update({code:p['rule']for code,p in self.foundation_proofs.items()})
 def enable_project_policies(self):
  self.project_policies=project_rules(self.source_proofs,self.raw_rules)
  self.rules.update({code:p['rule']for code,p in self.project_policies.items()})
 def needs_manual_evidence(self,node):
  if node['type']in ['approval','review-required','unresolved-course-reference','scoped-evidence']:return True
  return any(self.needs_manual_evidence(c)for c in node.get('rules',[]))or('rule'in node and self.needs_manual_evidence(node['rule']))
 def add(self,key):
  if key in self.courses:return
  code=canonical(key);m=self.meta.get(code)
  if not m or m['creditMinimum']is None:raise ValueError('Unknown course credits: '+key)
  credits=float(m['creditMinimum']);version='2026-2027';title=m['sourceTitle']
  if key in self.histVersions:
   v=self.histVersions[key];credits=float(v['credits']);version=v['sourceCatalog'];title=v['title']
  elif m['creditMaximum']!=m['creditMinimum']:credits=min(4,float(m['creditMaximum']))
  sub=subject(code);number=int(re.search(r'\d+',code)[0]);desc=m.get('displayDescription','');classes=[]
  media=next(p for p in self.programs if p['program']=='Media & Communications, B.A.')
  if code in media['groups'][1]['eligibleCourses']:classes.append('Media Studies')
  if code in media['groups'][3]['eligibleCourses']:classes.append('Media Production')
  if code in ['MC4832','MC4835']:classes.append('internship')
  if code in ['VA3340','VA3350','VA3360','VA2500','VA2510','VA2520']:classes.append('art-history')
  if sub=='PH':classes.append('approved-public-health-elective')
  if code in ['EL3600','EL3610','EL4000','EL4010','EL4015','EL4020','EL4040','EL4070','EL4090','EL4091']:classes.append('US-literature')
  if code in ['EL3800','EL3810','EL4550','EL4560','EL4325']:classes.append('British-literature')
  if code in ['EL3560','EL3561','EL3700','EL3865','EL3870','EL3880','EL4200','EL4311','EL4312','EL4405','EL4600','EL4630','EL4640','EL4650']:classes.append('World-literature')
  if code in ['EL3600','EL3800','EL3560','EL4200','EL4311','EL4312','EL4325']:classes.append('literature-before-1800-or-US-before-1900')
  if sub=='MA'and number>=2090:classes.append('precalculus-or-higher')
  if sub in ['BS','CP','PX']and code not in ['BS2100','BS2490','CP2210','CP2211','CP2800','PX2900','PX2901']:classes.append('qualifying-psychology-bs-science')
  if re.search('independent study',title,re.I):classes.append('independent-study')
  self.courses[key]={'key':key,'courseCode':code,'catalogVersion':version,'title':title,'credits':credits,'departmentId':self.dept(key),'upperDivision':3000<=number<6000,'level':number,'liberalArts':sub not in ['BU','ED','MH']or code in ['BU3071','BU3250','BU3905'],'classifications':classes,'labBearing':bool(re.search('laborator|\blab\b',title+' '+desc,re.I)),'description':desc}
  if key in self.degree_properties:
   self.courses[key].update({k:v for k,v in self.degree_properties[key].items()if k in ['upperDivision','level','liberalArts','classifications','labBearing']})
  self.prior[key]=set();self.coreq[key]=set();self.requirements[key]=[]
 def translate(self,code):return 'ED4050@2020-2022'if code=='ED4050'else code
 def expand(self,key,preferred,visiting=None):
  self.add(key);visiting=set()if visiting is None else visiting
  if key in visiting:return
  visiting=visiting|{key};rule=self.rules.get(key,{'type':'review-required','reason':'missing rule'})
  def needed(n,concurrent=False):
   t=n['type'];out=set()
   if t in ['course','approved-transfer-course']:
    c=self.translate(n['courseCode'])
    if canonical(c)in self.meta:out.add(c)
    else:self.manual_rules.add(key)
   elif t=='and':
    for x in n['rules']:out.update(needed(x,concurrent))
   elif t=='or':
    choices=[]
    for x in n['rules']:
     nodes=needed(x,concurrent);choices.append((int(self.needs_manual_evidence(x))if self.proof_planning_enabled else 0,sum(self.freq[canonical(c)]==0 for c in nodes),len(nodes-set(preferred)),len(nodes),-sum(self.freq[canonical(c)]for c in nodes),sum(self.meta[canonical(c)]['creditMinimum']or 100 for c in nodes),sorted(nodes)))
    out.update(min(choices)[-1])
   elif t in ['corequisite-condition','completed-or-concurrent-condition']:
    nodes=needed(n['rule'],True);self.coreq[key].update(nodes);return set()
   elif t in ['proficiency','completed-writing-proficiency','completed-or-concurrent-proficiency']:
    token=n.get('key','writing');out.update(['EL1000']if token=='ECI'else['MA2000']if token=='MATH_PROFICIENCY'else['EL1000','EL2216'])
   elif t in ['standing-in-program','earned-subject-credits','minimum-earned-credits','minimum-institutional-gpa','subject-course-count']:self.requirements[key].append(n)
   elif t=='completed-laboratory-science':
    if not(set(self.source_laboratories)&set(preferred)):out.add('BS2100')
   elif t=='scoped-evidence':
    self.manual_rules.add(key)
    if n['scope']=='placement:ECII':out.add('EL1000')
   elif t in ['review-required','approval','unresolved-course-reference']:
    self.manual_rules.add(key)
    # Visible specific Education admission/coursework conditions are modeled explicitly.
    if canonical(key)=='ED3820':out.update(['EL1000','EL2216'])
    if canonical(key)=='ED3900':out.update(['EL1000','EL2216','ED3700','MA2000']);self.requirements[key].append({'type':'minimum-earned','minimum':32,'gpa':3.0})
    if 'Admission to SOE'in str(n)or'Admission to the School'in str(n):out.update(['ED3700','ED3820','ED3900'])
   return out
  self.prior[key].update(needed(rule));self.prior[key].discard(key);self.coreq[key].discard(key)
  for parent in list(self.prior[key]|self.coreq[key]):
   if parent in visiting:
    self.requirements[key].append({'type':'project-cycle-review','sourceReference':parent});self.prior[key].discard(parent);self.coreq[key].discard(parent);self.manual_rules.add(key)
   else:self.expand(parent,preferred,visiting)
 def pool(self,domain):
  codes=[]
  for row in self.ge:
   c=row['sourceCourseCode']
   if row['domain']==domain and c in self.meta and self.meta[c]['creditMinimum']==self.meta[c]['creditMaximum']==4 and self.freq[c]>=8 and not row['lectureLabBundleCandidate']:codes.append(c)
  return list(dict.fromkeys(codes))
 def make_plan(self,student):
  sid=student['student_ID'];name=MAJORS[student['major_ID']-1];options=[c for c in self.cases if c['name']==name];case=options[(sid//10)%len(options)];keys=[]
  for a in case['student']['attempts']:
   if case['roles'][a['courseCode']]=='general-elective':continue
   key=a['courseCode']+('@'+a['catalogVersion']if a['catalogVersion']!='2026-2027'else'');keys.append(key)
  # Replace synthetic attribute-test choices with actual subject/category course choices.
  if name=='English, B.A.':keys=['EL3800'if k=='EL3610'else k for k in keys]
  if name=='Media & Communications, B.A.':
   keys=[k for k in keys if k not in ['AS3000','AS4000']]
   for k in ['MC3632','MC4160']:
    if k not in keys:keys.append(k)
  if name=='Childhood Education 1-6, B.S.':keys=['MA3520'if k=='MA3010'else k for k in keys]
  if name=='Psychology, B.S.':
   keys=[k for k in keys if case['roles'].get(k)!='science-plan'];keys+=['MA2090','BS2400','BS2401','BS2410','BS2411','CP2120','CP2121','CP2130','CP2131']
  minor=None
  if self.minors[sid]:
   preferred=MINORS[self.minors[sid][0]-1]
   choices=[preferred]+[m for m in MINORS if m!=preferred]
   if name==MAJORS[0]and preferred==MINORS[0]:choices=[MINORS[4]]+[m for m in choices if m!=MINORS[4]]
   for m in choices:
    mc=next(c for c in self.cases if c['name']==m);mk=[a['courseCode']for a in mc['student']['attempts']];mp=next(p for p in self.programs if p['program']==m)['policies'];shared=set(mk)&set(keys);outside=sum(self.meta[c]['creditMinimum']for c in mk if c not in shared)
    same=(('Computer'in m and'Computer'in name)or('Mathematics'in m and'Mathematics'in name)or('Psychology'in m and'Psychology'in name)or('Public Health'in m and'Public Health'in name))
    if same or outside<mp.get('minimumCreditsOutsideMajor',0)or len(shared)>mp.get('maximumCoursesSharedWithMajor',999):continue
    if m=='Visual Arts, Minor':mk=['VA2020','VA2030','VA2045','VA3340','VA3360']
    keys+=mk;minor=m;break
  keys=list(dict.fromkeys(keys+['EL1000','EL2216']))
  # Current domains apply retroactively as the owner-authorized Northbridge rebuild policy.
  covered=set();gecodes=set()
  for row in self.ge:
   if row['sourceCourseCode']in keys and not row['lectureLabBundleCandidate']:covered.add(row['domain']);gecodes.add(row['sourceCourseCode'])
  pools={d:self.pool(d)for d in dict.fromkeys(x['domain']for x in self.ge)}
  recommended={'Mathematics':'MA2000','World Languages':'ML1000','The Arts':'MD1000','Natural Sciences':'BS2100','US History and Civic Engagement':'AS2252','World History and Global Awareness':'HI2511','Humanities':'AS1512','Social Sciences':'SY1500','Diversity: Equity, Inclusion, and Social Justice':'AS1512'}
  for d,pool in pools.items():
   if d in covered:continue
   pick=recommended.get(d,pool[0]);pick=pick if pick in pool else pool[0];keys.append(pick);gecodes.add(pick)
   if d=='The Arts':
    # A single published Music section cannot support every fictional student
    # at once. Spread cohorts across equally valid, regularly offered choices.
    choices=[c for c in ['MD1000','MD1110','MD2350','MD3010']if c in pool]
    alternative=choices[stable((sid//10,d))%len(choices)]if choices else pick
    keys[-1]=alternative;gecodes.discard(pick);gecodes.add(alternative);pick=alternative
   for row in self.ge:
    if row['sourceCourseCode']==pick:covered.add(row['domain'])
  # At least 30 distinct general-education credits, even where domains overlap.
  for c in ['HI3002','HI3600','AS1512','AS2252','MD1000','SY1500','ML1000','BS2100']:
   if len(gecodes)*4>=30:break
   if c not in keys:keys.append(c)
   gecodes.add(c)
  for k in list(keys):self.expand(k,set(keys))
  allkeys=set(keys)
  def include(k):
   for p in self.prior[k]|self.coreq[k]:
    if p not in allkeys:allkeys.add(p);include(p)
  for k in list(allkeys):include(k)
  degree=next(p for p in self.programs if p['program']==name)['degreeRequirements'];fillers=['HI3002','HI3006','HI3600','AS3462','AS3010','EL4312','EL4030','MD3010','SY3280','HI4210']
  for c in fillers:
   credits=sum(self.courses[k]['credits']for k in allkeys);la=sum(self.courses[k]['credits']for k in allkeys if self.courses[k]['liberalArts']);upper=sum(self.courses[k]['credits']for k in allkeys if self.courses[k]['upperDivision'])
   if credits>=degree['minimumTotalCredits']and la>=degree['minimumLiberalArtsCredits']and upper>=degree['minimumUpperDivisionCredits']:break
   if c not in allkeys:self.expand(c,allkeys);allkeys.add(c);include(c)
  return {'major':name,'minor':minor,'concentration':case['concentration'],'courses':sorted(allkeys),'priority':list(dict.fromkeys(['EL1000','EL2216']+keys)),'geCourses':sorted(gecodes)}
 def sources(self,term,key):
  code=canonical(key);rows=self.offer[term,code]
  if not rows and code in self.aliases:
   rows=self.offer[term,self.aliases[code]]
   if code in self.former_templates:
    m=self.meta[code];rows=[s for s in rows if credit_bounds(s)==(m['creditMinimum'],m['creditMaximum'])]
  if not rows and key in self.histVersions:
   rows=self.offer[term,'ED4850'if code=='ED4050'else'ED5925']
  return rows
 def ready(self,key,done,chosen,plan):
  """Candidate ordering only. Unresolved rules separately block activation.

  This method must never authorize registration or certify prerequisites.
  """
  total=sum(self.courses[k]['credits']for k in done)
  def ok(n,concurrent=False):
   t=n['type']
   if t in ['course','approved-transfer-course']:return self.translate(n['courseCode'])in set(done)|(set(chosen)if concurrent else set())
   if t=='and':return all(ok(x,concurrent)for x in n['rules'])
   if t=='or':
    choices=n['rules']
    if self.proof_planning_enabled:
     verified=[x for x in choices if not self.needs_manual_evidence(x)]
     if verified:choices=verified
    return any(ok(x,concurrent)for x in choices)
   if t in ['corequisite-condition','completed-or-concurrent-condition']:return ok(n['rule'],True)
   if t in ['proficiency','completed-writing-proficiency','completed-or-concurrent-proficiency']:
    required={'EL1000'}if n.get('key')=='ECI'else{'MA2000'}if n.get('key')=='MATH_PROFICIENCY'else{'EL1000','EL2216'}
    return required<=set(done)|(set(chosen)if concurrent or t=='completed-or-concurrent-proficiency'else set())
   if t=='standing-in-program':
    required={'Freshman':0,'Sophomore':32,'Junior':57,'Senior':88}.get(n['standing'],float('inf'))
    tag={'Computer and Information Science, B.S.':'CIS','Management Information Systems, B.S.':'MIS'}.get(plan['major'],plan['major'])
    return total>=required and tag==n['program']
   if t=='earned-subject-credits':return sum(self.courses[k]['credits']for k in done if subject(canonical(k))==n['subject'])>=n['minimum']
   if t=='minimum-earned-credits':return total>=n['minimum']
   if t=='minimum-institutional-gpa':
    # Every generated completed grade is B or higher. The independent audit
    # recomputes each student's actual GPA; this is only a planning lower bound.
    return bool(done)and n['minimum']<=3.0
   if t=='subject-course-count':
    return sum(subject(canonical(k))==n['subject']and n.get('minimumLevel',0)<=self.courses[k]['level']<=n.get('maximumLevel',99999)for k in done)>=n['minimumCourses']
   if t=='completed-laboratory-science':return any(canonical(k)in self.source_laboratories for k in done)
   if t=='scoped-evidence':return n['scope']!='placement:ECII'or'EL1000'in done
   if t=='program-field':return any(any(field in value for field in n['eligibleFields'])for value in [plan['major'],plan.get('minor')or''])
   if t in ['approval','review-required','unresolved-course-reference','no-course-prerequisites']:return True
   return False
  if not ok(self.rules.get(key,{'type':'review-required'})):return False
  code=canonical(key)
  if code=='ED3820'and not{'EL1000','EL2216'}<=set(done):return False
  if code=='ED3900'and(total<32 or not{'EL1000','EL2216','ED3700','MA2000'}<=set(done)):return False
  if code=='ED5940'and not(set(plan['courses'])-{'ED5940','MA1010','CS3611'})<=set(done):return False
  if plan['major']=='Psychology, B.S.':
   science_pairs=[{'BS2400','BS2401'},{'BS2410','BS2411'},{'CP2120','CP2121'},{'CP2130','CP2131'}]
   pair=next((p for p in science_pairs if code in p),None)
   if pair and any(c in set(chosen)-pair for p in science_pairs if p!=pair for c in p):return False
  return True
 def resources_fit(self,term,tid,rosters,bundle,ids):
  """Reject unavoidable simultaneous overloads before selecting electives.

  Only single-pattern offerings are forced here. The full scheduler still
  checks every meeting choice and individual faculty assignment independently.
  """
  counts={k:math.ceil((len(v)+(len(ids)if k in bundle else 0))/10)for k,v in rosters.items()if v or k in bundle}
  for k in bundle:counts.setdefault(k,math.ceil(len(ids)/10))
  forced=[]
  for k,n in counts.items():
   if (term,k)not in self.meeting_cache:self.meeting_cache[term,k]=list({tuple(meetings(s))for s in self.sources(term,k)})
   if len(self.meeting_cache[term,k])==1 and self.meeting_cache[term,k][0]:forced.append((k,n))
  identity=(term,tuple(sorted(forced)))
  if identity in self.resource_cache:return self.resource_cache[identity]
  from academic_meetings import old_meetings
  bounds=[(m[3],m[4])for k,n in forced for m in self.meeting_cache[term,k][0]]
  if not bounds:return True
  lower=min(x[0]for x in bounds);upper=max(x[1]for x in bounds)
  events=collections.defaultdict(list)
  def add(d,pattern,n):
   for day,start,end,lo,hi in pattern:
    lo=max(lo,lower);hi=min(hi,upper)
    if lo>hi:continue
    first=lo+(day-dt.date.fromordinal(lo).weekday())%7
    for date in range(first,hi+1,7):events[d,date].extend([(start,n),(end,-n)])
  for k,n in forced:add(self.courses[k]['departmentId'],self.meeting_cache[term,k][0],n)
  if tid not in self.resource_grad_cache:
   self.resource_grad_cache[tid]=[(self.oldcourses[s['course_ID']]['dept_ID'],old_meetings(self.tables,s))for s in self.tables['Course_Section']if s['semester_ID']==tid and self.oldcourses[s['course_ID']]['course_Type']=='Graduate']
  for d,pattern in self.resource_grad_cache[tid]:add(d,pattern,1)
  faculty=collections.Counter(x['dept_ID']for x in self.tables['Faculty_Department'])
  for (d,date),values in events.items():
   load=0
   for time,delta in sorted(values):
    load+=delta
    if load>faculty[d]:self.resource_cache[identity]=False;return False
  self.resource_cache[identity]=True;return True
 def meetings_fit(self,term,keys):
  identity=(term,tuple(sorted(keys)))
  if identity in self.feasibility_cache:return self.feasibility_cache[identity]
  options=[]
  for key in keys:
   if(term,key)not in self.meeting_cache:self.meeting_cache[term,key]=list({tuple(meetings(s))for s in self.sources(term,key)})
   options.append(self.meeting_cache[term,key])
  options.sort(key=len)
  def choose(i,occupied):
   if i==len(options):return True
   return any(not overlap(p,occupied)and choose(i+1,occupied+list(p))for p in options[i])
  result=choose(0,[]);self.feasibility_cache[identity]=result;return result
 def repair_minimum(self,g,term,tid,rosters,deptload):
  """Backtrack a short timetable before spare capacity is allocated elsewhere."""
  original=list(g['_chosen']);ids=set(g['ids']);saved_load=deptload.copy();saved={k:list(rosters[k])for k in original}
  for key in original:
   before=math.ceil(len(rosters[key])/10);rosters[key]=[sid for sid in rosters[key]if sid not in ids]
   deptload[self.courses[key]['departmentId']]+=math.ceil(len(rosters[key])/10)-before
  pool=set(g['plan']['courses'])|set(self.elective_pool());eligible=[]
  for key in sorted(pool):
   if key in g['done']or canonical(key)not in self.meta or self.meta[canonical(key)]['creditMinimum']!=4 or self.meta[canonical(key)]['creditMaximum']!=4 or not self.sources(term,key):continue
   if key not in self.courses:self.expand(key,set(g['plan']['courses']))
   if self.coreq[key]-set(g['done'])or not self.ready(key,g['done'],[],g['plan']):continue
   eligible.append(key)
  eligible.sort(key=lambda k:(k not in g['plan']['courses'],deptload[self.courses[k]['departmentId']],k))
  needed=3 if g['mode']=='Full-Time'else 1;steps=0
  def choose(selected,deltas,start):
   nonlocal steps
   steps+=1
   if steps>6000:return None
   if len(selected)==needed:return selected
   for i in range(start,len(eligible)):
    key=eligible[i];d=self.courses[key]['departmentId'];delta=math.ceil((len(rosters[key])+len(ids))/10)-math.ceil(len(rosters[key])/10)
    if deptload[d]+deltas[d]+delta+self.grad_load[tid,d]>self.planning_caps[d]or not self.meetings_fit(term,set(selected)|{key})or not self.resources_fit(term,tid,rosters,set(selected)|{key},g['ids']):continue
    nextd=deltas.copy();nextd[d]+=delta;result=choose(selected+[key],nextd,i+1)
    if result:return result
   return None
  replacement=choose([],collections.Counter(),0)
  if replacement:
   g['_chosen']=replacement
   for key in replacement:
    d=self.courses[key]['departmentId'];before=math.ceil(len(rosters[key])/10);rosters[key].extend(g['ids']);deptload[d]+=math.ceil(len(rosters[key])/10)-before
   return True
  for key,roster in saved.items():rosters[key]=roster
  deptload.clear();deptload.update(saved_load);return False
 def elective_pool(self):
  # General electives also include verified introductory courses outside GE.
  # Keep unresolved courses out of this additional fallback pool.
  def verified(node):
   if node['type']in ['review-required','approval','unresolved-course-reference']:return False
   if node['type']in ['and','or']:return all(verified(n)for n in node['rules'])
   if 'rule'in node:return verified(node['rule'])
   return True
  extra=[c for c,m in self.meta.items()if int(re.search(r'\d+',c)[0])<3000 and m['creditMinimum']==m['creditMaximum']==4 and c in self.rules and verified(self.rules[c])]
  return list(dict.fromkeys([r['sourceCourseCode']for r in self.ge]+sorted(extra)))
 def build(self):
  for sid in sorted(self.ug_ids):self.plans[sid]=self.make_plan(self.students[sid])
  # Group academically identical learners so roster packing remains practical.
  cohorts=collections.defaultdict(list);terms_by_sid={}
  for sid in sorted(self.ug_ids):
   s=self.students[sid];mode=self.modes[sid]
   if sid in self.active:
    completed=(2 if mode=='Full-Time'else 4)*(s['student_Year']-1);start=max(0,self.current-completed);termids=[t['semester_ID']for t in self.terms[start:]]
   else:termids=sorted({x['semester_ID']for x in self.old_by_student[sid]},key=lambda t:self.termpos[t])
   terms_by_sid[sid]=termids;p=self.plans[sid];cohorts[(p['major'],p['minor'],p['concentration'],mode,tuple(termids),tuple(p['courses']))].append(sid)
  groups=[]
  for (major,minor,concentration,mode,terms,courses),ids in cohorts.items():
   for i in range(0,len(ids),10):groups.append({'ids':ids[i:i+10],'mode':mode,'terms':terms,'plan':self.plans[ids[i]],'done':{},'entries':[]})
  planned=[];unfilled=[];sectioncounts={};newrosters={}
  for t in self.terms:
   tid=t['semester_ID'];season,year=t['semester_Name'].split();term=year+('09'if season=='Fall'else'02');rosters=collections.defaultdict(list);deptload=collections.Counter()
   active=[g for g in groups if tid in g['terms']]
   # Learners with the fewest completed prerequisites have fewer usable alternatives.
   active.sort(key=lambda g:(len(g['done']),stable((term,min(g['ids']))),g['plan']['major']))
   for g in active:g['_chosen']=[]
   # Guarantee minimum loads before distributing the remaining teaching capacity.
   for phase in [0,1]:
    for g in active:
     done=g['done'];chosen=g['_chosen'];load=sum(self.courses[c]['credits']for c in chosen);limit=16 if g['mode']=='Full-Time'else 8;minimum=12 if g['mode']=='Full-Time'else 4
     remaining=set(g['plan']['courses'])-set(done)-set(chosen);rank={c:i for i,c in enumerate(g['plan']['priority'])}
     attempts=0
     while load<(minimum if phase==0 else limit):
      candidates=[]
      for k in remaining:
       if not self.ready(k,done,chosen,g['plan']):continue
       bundle={k}|(self.coreq[k]-set(done));bundle|=set().union(*(self.coreq.get(c,set())-set(done)for c in list(bundle)))
       bundle-=set(chosen)
       if not all(self.ready(c,done,set(chosen)|bundle,g['plan'])for c in bundle):continue
       credits=sum(self.courses[c]['credits']for c in bundle)
       if load+credits>limit or not all(self.sources(term,c)for c in bundle):continue
       if not self.meetings_fit(term,set(chosen)|bundle):continue
       if not self.resources_fit(term,tid,rosters,bundle,g['ids']):continue
       deltas=collections.Counter()
       for c in bundle:
        n=len(rosters[c]);deltas[self.courses[c]['departmentId']]+=math.ceil((n+len(g['ids']))/10)-math.ceil(n/10)
       if any(deptload[d]+delta+self.grad_load[tid,d]>self.planning_caps[d]for d,delta in deltas.items()):continue
       descendants=sum(k in self.prior[x]for x in remaining);candidates.append((-descendants,rank.get(k,999),credits,k,bundle,deltas))
      if not candidates:
       # A free elective can maintain a valid load without waiving a known prerequisite.
       extras=[]
       for c in self.elective_pool():
        if c in done or c in chosen or c not in self.meta or self.meta[c]['creditMinimum']!=self.meta[c]['creditMaximum']or self.meta[c]['creditMinimum']!=4 or not self.sources(term,c):continue
        if c not in self.courses:self.expand(c,set(g['plan']['courses']))
        if not self.ready(c,done,chosen,g['plan'])or self.coreq[c]-set(done):continue
        if not self.meetings_fit(term,set(chosen)|{c}):continue
        if not self.resources_fit(term,tid,rosters,{c},g['ids']):continue
        d=self.courses[c]['departmentId'];delta=math.ceil((len(rosters[c])+len(g['ids']))/10)-math.ceil(len(rosters[c])/10)
        if deptload[d]+delta+self.grad_load[tid,d]>self.planning_caps[d]or load+4>limit:continue
        extras.append((deptload[d],-len(rosters[c]),c,d,delta))
       if not extras:break
       _,_,c,d,delta=min(extras);chosen.append(c);remaining.discard(c);load+=4;rosters[c].extend(g['ids']);deptload[d]+=delta;continue
      *_,k,bundle,deltas=min(candidates,key=lambda x:x[:4])
      for c in sorted(bundle):
       chosen.append(c);remaining.discard(c);load+=self.courses[c]['credits'];rosters[c].extend(g['ids'])
      deptload.update(deltas)
    if phase==0:
     for g in active:
      minimum=12 if g['mode']=='Full-Time'else 4
      if sum(self.courses[c]['credits']for c in g['_chosen'])<minimum:self.repair_minimum(g,term,tid,rosters,deptload)
   for g in active:
    done=g['done'];chosen=g['_chosen'];load=sum(self.courses[c]['credits']for c in chosen);minimum=12 if g['mode']=='Full-Time'else 4
    remaining=set(g['plan']['courses'])-set(done)-set(chosen)
    if load<minimum:unfilled.append({'term':term,'studentCount':len(g['ids']),'mode':g['mode'],'major':g['plan']['major'],'credits':load,'minimum':minimum,'remainingCourses':sorted(remaining)})
    for c in chosen:
     for sid in g['ids']:
      grade=None if tid in [1,2]else['A','A-','B+','B'][stable((sid,c,tid))%4]
      planned.append({'studentId':sid,'termId':tid,'termCode':term,'courseKey':c,'grade':grade,'status':'completed'if grade else'in-progress'if tid==1 else'planned'})
     done[c]=tid
   sectioncounts[term]={str(d):n for d,n in sorted(deptload.items())};newrosters[term]={c:ids for c,ids in rosters.items()}
   print(t['semester_Name'],'courses',len(rosters),'student attempts',sum(map(len,rosters.values())),'sections',sum(deptload.values()),'underload groups',sum(u['term']==term for u in unfilled),flush=True)
  return {'status':'generated-not-applied','asOf':'2026-11-07','policy':'Owner-authorized fictional undergraduate academic rebuild, not verified transfer equivalence.','students':[{'studentId':sid,'mode':self.modes[sid],'major':p['major'],'minor':p['minor'],'concentration':p['concentration'],'targetCourses':p['courses'],'geCourses':p['geCourses'],'activeOriginalCohort':sid in self.active}for sid,p in self.plans.items()],'courses':self.courses,'prerequisiteEdges':{k:sorted(v)for k,v in self.prior.items()},'corequisiteEdges':{k:sorted(v)for k,v in self.coreq.items()},'sourceReviewCoursesNeedingScopedProjectApprovals':sorted(self.manual_rules),'attempts':planned,'rosters':newrosters,'sectionCountsByDepartment':sectioncounts,'underloadGroups':unfilled,'workingDatabaseChanged':False,'graduateStudentsProtected':len(self.graduate_ids)}
if __name__=='__main__':
 parser=argparse.ArgumentParser();parser.add_argument('--source',required=True);parser.add_argument('--output',required=True);parser.add_argument('--reserve-sections-per-department',type=int,choices=range(16),default=5);a=parser.parse_args()
 if not str(Path(a.source).resolve()).startswith('/private/tmp/')or not str(Path(a.output).resolve()).startswith('/private/tmp/'):raise SystemExit('Private paths required')
 b=Builder(read(Path(a.source)));b.enable_source_proof_planning();b.enable_project_policies()
 # Reuse collected public academic facts; no network or personal data here.
 all_versions={code:{'courseCode':code,'catalogVersion':'2026-2027','title':m['sourceTitle']}for code,m in b.meta.items()}
 for key,v in b.histVersions.items():all_versions[key]={'courseCode':key.split('@')[0],'catalogVersion':v['sourceCatalog'],'title':v['title']}
 degree_registry=reviewed_properties(all_versions);b.degree_properties=degree_registry['courseProperties']
 # Leave teaching capacity for the extra rosters that exact meeting choices
 # can require. Actual workload limits in the scheduler/auditor stay unchanged.
 b.planning_caps=collections.Counter({d:max(0,n-a.reserve_sections_per_department)for d,n in b.dept_caps.items()});out=b.build()
 out['planningReserveSectionsPerDepartment']=a.reserve_sections_per_department
 out['prerequisiteSourceProofs']=b.source_proofs;out['sourceLaboratoryScienceEvidence']=b.source_laboratories
 out['writingSequencePlanningPolicy']='Start fictional entrants in Composition I; plan Composition II after I, with placement evidence still required.'
 out['historicalOfferingTemplates']=b.former_templates;out['historicalOfferingTemplateReviews']=b.rejected_former_templates
 out['reviewedDegreeProperties']=degree_registry
 out['northbridgeProjectPolicies']=b.project_policies
 Path(a.output).write_text(json.dumps(out,indent=2)+'\n');Path(a.output).chmod(0o600)
 print(json.dumps({'students':len(out['students']),'attempts':len(out['attempts']),'newCourseVersions':len(out['courses']),'underloadGroups':len(out['underloadGroups']),'privatePlan':a.output}))
