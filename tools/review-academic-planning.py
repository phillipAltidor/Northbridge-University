#!/usr/bin/env python3
"""Publish structural coverage and aggregate blockers; never publish students."""
import argparse,collections,hashlib,itertools,json,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'database/generation'))
from academic_planning import PlanningCatalog,source_sequences
from rebuild_oldwestbury import Builder

def review(source,baseline,candidate):
 b=Builder(source);b.enable_source_proof_planning();b.enable_project_policies()
 seq=source_sequences(json.loads((ROOT/'database/staging/academic-alignment/program-review-inputs.json').read_text()))
 planner=PlanningCatalog(b.programs,candidate['courses'],b.rules,b.ge,seq)
 programs=[];variants=0
 for program in b.programs:
  variants_for_program=[]
  for concentration in program.get('concentrations',{})or[None]:
   paths=[]
   def walk(node,label):
    if node['type']=='choose-one-path':paths.append((label,list(node['paths'])))
    for i,g in enumerate(node.get('groups',[])):walk(g,label+'/'+str(i))
    if node['type']=='selected-concentration':
     for i,g in enumerate(program['concentrations'][concentration]['groups']):walk(g,label+'/concentration/'+concentration+'/'+str(i))
   for i,g in enumerate(program['groups']):walk(g,program['kind']+'/'+str(i))
   for chosen in itertools.product(*(values for _,values in paths)):
    variants+=1;person={'studentId':-variants,'major':program['program']if program['kind']=='major'else b.programs[0]['program'],'minor':program['program']if program['kind']=='minor'else None,'concentration':concentration,'planningPaths':dict(zip((k for k,_ in paths),chosen))}
    goals=planner.goals(person,program['kind'],set())
    missing=sorted({k for g in goals for unit in g['units']for k in unit if k not in candidate['courses']})
    variants_for_program.append({'concentration':concentration,'paths':person['planningPaths'],'compiledGoals':len(goals),'missingCourseDefinitions':missing,'emptyChoiceGroups':[g['id']for g in goals if not g['units']]})
  programs.append({'program':program['program'],'kind':program['kind'],'sequenceBasis':'published sample, subject to actual prerequisites'if program['program']in seq else'prerequisite-derived planning; no published sample in supplied program pages','variants':variants_for_program})
 people={p['studentId']:p for p in candidate['students']};loads=collections.Counter();prior=collections.Counter()
 for a in candidate['attempts']:
  loads[str(a['termCode']),a['studentId']]+=candidate['courses'][a['courseKey']]['credits']
  if str(a['termCode'])<'202609':prior[a['studentId']]+=1
 active=collections.defaultdict(set)
 for a in baseline['attempts']:
  if str(a['termCode'])in ['202609','202702']:active[str(a['termCode'])].add(a['studentId'])
 blocked={}
 for term,ids in active.items():
  counts=collections.Counter(people[sid]['major']for sid in ids if loads[term,sid]<(12 if people[sid]['mode']=='Full-Time'else 4))
  blocked[term]={'studentsBelowMinimumLoad':sum(counts.values()),'byMajor':dict(sorted(counts.items()))}
 purposes=candidate.get('academicPlanningPurposes',[]);roles=collections.Counter(p['role']for p in purposes)
 freshman_cs={sid for sid,p in people.items()if not prior[sid]and p['major']=='Computer and Information Science, B.S.'and sid in active['202609']}
 algebra_and_stats=sum(all(any(a['studentId']==sid and a['termCode']=='202609'and a['courseKey']==code for a in candidate['attempts'])for code in ['MA1020','MA2000'])for sid in freshman_cs)
 current_future=[a for a in candidate['attempts']if str(a['termCode'])in active]
 assigned={(p['term'],p['studentId'],p['courseKey'])for p in purposes}
 result={'status':'staging-blocked-not-approved-for-migration','schemaChanged':False,'databaseChanged':False,'activationAllowed':False,'programsChecked':len(programs),'majorsChecked':sum(p['kind']=='major'for p in programs),'minorsChecked':sum(p['kind']=='minor'for p in programs),'programConcentrationAndPathVariantsChecked':variants,'publishedSamplePrograms':sorted(seq),'unsupportedPlanningNodes':sorted(planner.unsupported),'programs':programs,'minimumLoadBlockers':blocked,'courseAssignmentPurposes':dict(sorted(roles.items())),'assignmentsWithoutPurpose':sum((str(a['termCode']),a['studentId'],a['courseKey'])not in assigned for a in current_future),'currentOrFutureFinalGrades':sum(a['grade']is not None for a in current_future),'csFreshmenWithAlgebraAndStatisticsTogether':algebra_and_stats,'interpretation':'Structural coverage and valid prerequisites do not establish complete individual graduation plans. Underloaded plans remain blocked; no arbitrary filler or waived prerequisites are authorized.','studentIdentifiersPublished':False}
 if not(planner.unsupported or any(v['studentsBelowMinimumLoad']for v in blocked.values())or result['assignmentsWithoutPurpose']or result['currentOrFutureFinalGrades']or algebra_and_stats or any(v['missingCourseDefinitions']or v['emptyChoiceGroups']for p in programs for v in p['variants'])):result['status']='structural-planning-checks-passed-not-migration-approval'
 return result

if __name__=='__main__':
 p=argparse.ArgumentParser();p.add_argument('--source',required=True);p.add_argument('--baseline',required=True);p.add_argument('--candidate',required=True);a=p.parse_args()
 for path in [a.source,a.baseline,a.candidate]:
  if not str(Path(path).resolve()).startswith('/private/tmp/'):raise SystemExit('Private inputs required')
 result=review(*(json.loads(Path(path).read_text())for path in [a.source,a.baseline,a.candidate]))
 result['candidateFileSHA256']=hashlib.sha256(Path(a.candidate).read_bytes()).hexdigest()
 (ROOT/'database/staging/academic-alignment/academic-planning-review.json').write_text(json.dumps(result,indent=2)+'\n')
 print(json.dumps({k:v for k,v in result.items()if k not in ['programs','interpretation']}))
