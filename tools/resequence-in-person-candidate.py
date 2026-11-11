#!/usr/bin/env python3
"""Private two-term scheduling rehearsal. No SQL, schema or active writes."""
import argparse,collections,copy,hashlib,itertools,json,math,os,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'database/generation'))
from rebuild_oldwestbury import Builder,canonical,stable
from rebuild_eligibility import Eligibility
from academic_meetings import meetings,overlap,old_meetings
from schedule_oldwestbury import build

def run(source,base,out,keep_fall=False):
 b=Builder(source);b.enable_source_proof_planning();b.enable_project_policies()
 c=copy.deepcopy(base);b.courses=c['courses'];b.prior={k:set(v)for k,v in c['prerequisiteEdges'].items()};b.coreq={k:set(v)for k,v in c['corequisiteEdges'].items()}
 policy=json.loads((ROOT/'database/staging/academic-alignment/ma1020-entry-placement-policy.json').read_text());b.rules['MA1020']=policy['rule'];b.prior['MA1020']=set()
 # The policy is intentionally a separate staged overlay, not an alteration of
 # the approved source transcription or an invented earned course equivalent.
 c['entryPlacementPolicy']=policy;c['stagingOnly']=True;c['activationAllowed']=False
 termids={str(a['termCode']):a['termId']for a in base['attempts']};terms=['202702'] if keep_fall else ['202609','202702']
 rows=collections.defaultdict(list)
 for a in base['attempts']:
  if str(a['termCode'])not in terms:rows[a['studentId']].append(copy.deepcopy(a))
 active={a['studentId']for a in base['attempts']if str(a['termCode'])in terms}
 people={p['studentId']:p for p in c['students']}
 for sid in active:
  people[sid].setdefault('registrarEvidence',{})['placement:MA1020-entry']={'status':'approved','scope':'placement:MA1020-entry','authority':'northbridge-admissions-simulation','studentId':sid,'effectiveTermPosition':b.termpos[termids['202609']],'termOrder':202609,'approvalId':'NB-SIM-MA1020-'+str(sid),'approvedBy':'Owner-authorized Northbridge admission simulation','approvedAt':'2026-11-08','fictionalSimulation':True,'basis':'Approved entry placement; no earned credit or exam score fabricated'}
 fresh=sum([json.loads(Path(p).read_text())['data']for p in ['/Users/phillipaltidor/Downloads/searchResults.json','/Users/phillipaltidor/Downloads/searchResults (1).json']],[])
 risk={r['courseReferenceNumber']for r in fresh if r.get('isSectionLinked')or r.get('crossList')or(r.get('crossListCapacity')or 0)>0}
 for key,values in list(b.offer.items()):
  if key[0]in terms:b.offer[key]=[r for r in values if meetings(r)and not(key[0]=='202609'and r['courseReferenceNumber']in risk)]
 # Exact credits and reviewed aliases only. TBA-only meetings never become slots.
 source_options={}
 for term in terms:
  for key,course in c['courses'].items():
   valid=[]
   for r in b.sources(term,key):
    lo=r.get('creditHours')if r.get('creditHours')is not None else r.get('creditHourLow');hi=r.get('creditHourHigh')
    if lo==course['credits']and hi in [None,lo]and meetings(r):valid.append(r)
   source_options[term,key]=valid
 original_sources=b.sources;b.sources=lambda term,key:source_options.get((term,key),original_sources(term,key))
 cohorts=collections.defaultdict(list)
 for sid in sorted(active):
  p=people[sid];hist=[{k:v for k,v in a.items()if k!='studentId'}for a in rows[sid]]
  key=(p['major'],p['minor'],p['concentration'],p['mode'],tuple(p['targetCourses']),json.dumps(hist,sort_keys=True))
  cohorts[key].append(sid)
 groups=[]
 for ids in cohorts.values():
  for i in range(0,len(ids),10):groups.append(ids[i:i+10])
 print('Planning',len(active),'students in',len(groups),'cohorts',flush=True)
 ge=collections.defaultdict(set)
 for r in b.ge:
  if not r['lectureLabBundleCandidate']:ge[r['sourceCourseCode']].add(r['domain'])
 programmes={p['program']:p for p in b.programs}
 def required_keys(node):
  if node.get('type')=='all-courses':return set(node['courses'])
  return set().union(*(required_keys(n)for n in node.get('groups',[])))if node.get('groups')else set()
 for term in terms:
  tid=termids[term];rosters=collections.defaultdict(list);deptload=collections.Counter();chosen={i:[]for i in range(len(groups))}
  rankgroups=sorted(range(len(groups)),key=lambda i:(len(rows[groups[i][0]]),stable((term,min(groups[i])))))
  for phase in [0,1]:
   for i in rankgroups:
    ids=groups[i];p=people[ids[0]];selected=chosen[i];target=set(p['targetCourses']);done={a['courseKey']for a in rows[ids[0]]};pool=(target|set(ge))&set(c['courses'])
    major=programmes[p['major']];core=set().union(*(required_keys(g)for g in major['groups']))
    maximum=16 if p['mode']=='Full-Time'else 8;goal=(12 if p['mode']=='Full-Time'else 4)if phase==0 else maximum
    unlock=collections.Counter(k for x in target for k in b.prior.get(x,set()))
    if p['major']in ['Computer and Information Science, B.S.','Management Information Systems, B.S.']and 'CS2510'not in done:unlock['MA1020']+=100;unlock['CS2510']+=50
    while sum(c['courses'][k]['credits']for k in selected)<goal:
     covered=set().union(*(ge.get(k,set())for k in done|set(selected)))
     ranked=[]
     for k in pool-done-set(selected):
      bundle=({k}|b.coreq.get(k,set()))-done-set(selected)
      for x in list(bundle):bundle|=b.coreq.get(x,set())-done-set(selected)
      if not bundle<=pool or any(not source_options.get((term,x))for x in bundle):continue
      if sum(c['courses'][x]['credits']for x in selected+list(bundle))>maximum:continue
      # Never admit a current course on a merely conditional prerequisite.
      allowed=['satisfied']if term=='202609'else['satisfied','conditional']
      valid=True
      for sid in ids:
       temporary=rows[sid]+[{'studentId':sid,'termId':tid,'termCode':term,'courseKey':x,'grade':None,'status':'in-progress'if term=='202609'else'planned'}for x in selected+list(bundle)]
       for x in bundle:
        verdict=Eligibility(c['courses'],temporary,b.termpos,tid,people[sid],x,b.source_laboratories).evaluate(b.rules.get(x,{'type':'review-required'}))
        if verdict['status']not in allowed:valid=False;break
       if not valid:break
      if not valid or not b.meetings_fit(term,set(selected)|bundle):continue
      delta=collections.Counter()
      for x in bundle:delta[c['courses'][x]['departmentId']]+=math.ceil((len(rosters[x])+len(ids))/10)-math.ceil(len(rosters[x])/10)
      if any(deptload[d]+n+b.grad_load[tid,d]>b.dept_caps[d]for d,n in delta.items()):continue
      newdomains=len(set().union(*(ge.get(x,set())for x in bundle))-covered)
      score=(-sum(unlock[x]for x in bundle),-len(bundle&core),-len(bundle&target),-newdomains,sum(deptload[c['courses'][x]['departmentId']]for x in bundle),stable((term,p['major'],k)),k)
      ranked.append((score,bundle,delta))
     picked=None
     for _,bundle,delta in sorted(ranked,key=lambda v:v[0]):
      if b.resources_fit(term,tid,rosters,bundle,ids):picked=(bundle,delta);break
     if picked is None:break
     bundle,delta=picked
     for x in sorted(bundle):selected.append(x);rosters[x].extend(ids)
     deptload.update(delta)
  for i,ids in enumerate(groups):
   for sid in ids:
    rows[sid]+=[{'studentId':sid,'termId':tid,'termCode':term,'courseKey':k,'grade':None,'status':'in-progress'if term=='202609'else'planned'}for k in chosen[i]]
  c['rosters'][term]=dict(rosters)
  under=sum(len(ids)for i,ids in enumerate(groups)if sum(c['courses'][k]['credits']for k in chosen[i])<(12 if people[ids[0]]['mode']=='Full-Time'else 4))
  print(term,'planned',sum(len(v)for v in rosters.values()),'attempts; underloads',under,flush=True)
 c['attempts']=sorted([a for rs in rows.values()for a in rs],key=lambda a:(str(a['termCode']),a['studentId'],a['courseKey']))
 # Keep inactive learners' completed histories too.
 active_rows={sid for sid in rows}
 c['attempts']+=[copy.deepcopy(a)for a in base['attempts']if a['studentId']not in active_rows]
 (out/'candidate.json').write_text(json.dumps(c,indent=2)+'\n')
 import schedule_oldwestbury as scheduler
 original=scheduler.Builder;scheduler.Builder=lambda _:b
 subset=copy.deepcopy(c);subset['rosters']={t:c['rosters'][t]for t in terms}
 try:schedule=build(source,subset,faculty_seconds=30)
 finally:scheduler.Builder=original
 schedule['activationAllowed']=False
 (out/'schedule.json').write_text(json.dumps(schedule,indent=2)+'\n')
 # Independent prerequisite scan on all retained/current/future work.
 phase_counts=collections.defaultdict(collections.Counter);failures=[]
 for a in c['attempts']:
  sid=a['studentId'];verdict=Eligibility(c['courses'],rows[sid],b.termpos,a['termId'],people[sid],a['courseKey'],b.source_laboratories).evaluate(b.rules.get(a['courseKey'],{'type':'review-required'}))
  phase_counts[a['status']][verdict['status']]+=1
  if verdict['status']in ['unsatisfied','review-required']:failures.append({'studentId':sid,'courseKey':a['courseKey'],'term':a['termCode'],'result':verdict})
 (out/'prerequisite-failures.json').write_text(json.dumps(failures,indent=2)+'\n')
 loads={t:collections.Counter()for t in terms}
 for a in c['attempts']:
  t=str(a['termCode'])
  if t in terms:loads[t][a['studentId']]+=c['courses'][a['courseKey']]['credits']
 hist_before=[a for a in base['attempts']if str(a['termCode'])not in terms];hist_after=[a for a in c['attempts']if str(a['termCode'])not in terms]
 history_unchanged=sorted(hist_before,key=lambda a:(a['studentId'],str(a['termCode']),a['courseKey']))==sorted(hist_after,key=lambda a:(a['studentId'],str(a['termCode']),a['courseKey']))
 summary={'status':'two-term-resequencing-rehearsal','activeStudents':len(active),'historicalAttemptsPreserved':history_unchanged,'historicalAttemptCount':len(hist_before),'underloadedStudents':{t:sum(loads[t][sid]<(12 if people[sid]['mode']=='Full-Time'else 4)for sid in active)for t in terms},'prerequisiteStatusCounts':{k:dict(v)for k,v in phase_counts.items()},'prerequisiteFailures':len(failures),'scheduleStatus':schedule['status'],'scheduledSections':len(schedule['sections']),'solverFailures':schedule['failures'],'entryPlacementDoesNotGrantCredits':True,'allNewAttemptsUngraded':all(a['grade']is None for a in c['attempts']if str(a['termCode'])in terms),'schemaChanged':False,'databaseChanged':False,'activationAllowed':False,'privateCandidateDirectory':str(out)}
 (ROOT/'database/staging/academic-alignment/two-term-in-person-rehearsal.json').write_text(json.dumps(summary,indent=2)+'\n')
 print(json.dumps(summary),flush=True)

if __name__=='__main__':
 p=argparse.ArgumentParser();p.add_argument('--source',required=True);p.add_argument('--candidate',required=True);p.add_argument('--output',required=True);p.add_argument('--keep-fall',action='store_true');a=p.parse_args()
 for x in [a.source,a.candidate,a.output]:
  if not str(Path(x).resolve()).startswith('/private/tmp/'):raise SystemExit('Private paths required')
 os.umask(0o077);out=Path(a.output);out.mkdir(mode=0o700,parents=True,exist_ok=False)
 run(json.loads(Path(a.source).read_text()),json.loads(Path(a.candidate).read_text()),out,a.keep_fall)
