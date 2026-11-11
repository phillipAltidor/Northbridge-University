#!/usr/bin/env python3
"""Build synthetic registrar scenarios from the staged choices, not real student data.
Approvals/classifications here are deliberately synthetic, not official academic mappings.
"""
import json,re,itertools
from pathlib import Path
root=Path(__file__).resolve().parents[3]
r=root/'database/staging/academic-alignment/rules'
programs=json.loads((r/'curricula.json').read_text())['programs']
metadata={x['courseCode']:x for x in json.loads((r/'course-metadata.json').read_text())['courses']}
def approval(scope,**extra):return dict(status='approved',scope=scope,approvalId='synthetic-'+scope,approvedBy='synthetic-reviewer',approvedAt='2026-11-07',**extra)
def matches(code,s):
 m=re.match(r'([A-Z]+)(\d+)',code);subject,num=m[1],int(m[2]);subjects=s.get('subject',[]);subjects=[subjects] if isinstance(subjects,str) else subjects
 return (not subjects or subject in subjects) and code not in s.get('excludeCourses',[]) and ('levels' not in s or num//1000*1000 in s['levels']) and num>=s.get('minimumLevel',0)
cases=[]
for program in programs:
 concentrations=list(program.get('concentrations',{})) or [None]
 paths=[]
 def findpaths(n,key=''):
  if isinstance(n,dict):
   if n.get('type')=='choose-one-path':paths.append((key,list(n['paths'])))
   for k,v in n.items():findpaths(v,key+'/'+str(k))
  elif isinstance(n,list):
   for i,x in enumerate(n):findpaths(x,key+'/'+str(i))
 findpaths(program['groups'])
 for concentration in concentrations:
  for choices in itertools.product(*(p[1] for p in paths)) if paths else [()]:
   choices=dict(zip([p[0] for p in paths],choices));used={};approvals={};roles={}
   def add(code,version='2026-2027',role=''):
    if code not in metadata or metadata[code]['creditMinimum'] is None or code in used:return False
    m=metadata[code];num=int(re.search(r'\d+',code)[0]);classes=[]
    if code.startswith(('MC','AS','VA')):classes+=['Media Studies','Media Production']
    if code.startswith('VA'):classes+=['art-history']
    if code.startswith('PH'):classes+=['approved-public-health-elective']
    if code in ['MC4832','MC4835']:classes+=['internship']
    if code=='EL3600':classes+=['British-literature','literature-before-1800-or-US-before-1900']
    if code=='EL3560':classes+=['US-literature','literature-before-1800-or-US-before-1900']
    if code=='EL3610':classes+=['World-literature']
    used[code]=dict(courseCode=code,status='completed',grade='B',credits=(4 if code in ['MC4832','MC4835'] else m['creditMinimum']),origin='local',termOrder=1,attemptOrder=1,upperDivision=num>=3000,level=num,liberalArts=True,classifications=classes,labBearing=True,catalogVersion=version,attributeApproval=approval('attributes:'+code))
    roles[code]=role;return True
   def units(node):
    t=node['type'];out=node.get('courses',node.get('eligibleCourses',node.get('options',[])))
    options=[]
    for x in out:
     if isinstance(x,str):options.append([x])
     elif x.get('type')=='course-bundle':options.append(x['courses'])
     elif 'courseCode'in x:options.append([x['courseCode']])
    for s in ([node] if t=='choose-by-selector' else node.get('selectors',[])):
     options += [[c] for c in metadata if matches(c,s)]
    if program['program']=='Media & Communications, B.A.':options.sort(key=lambda u: int(re.search(r'\d+',u[0])[0])<3000)
    return [u for u in options if all(c not in used and c in metadata and metadata[c]['creditMinimum'] is not None for c in u)]
   def walk(node,key='',role=''):
    t=node['type'];before=set(used)
    if t=='reviewed-evidence':approvals[node['key']]=approval(node['key']);return []
    if t=='selected-concentration':
     for i,g in enumerate(program['concentrations'][concentration]['groups']):walk(g,key+'/concentration/'+str(i),'concentration')
    elif t=='choose-one-path':walk(node['paths'][choices[key]],key+'/path',role)
    elif t=='choose-distinct-domain-courses':
     for i,codes in enumerate(node['domains'].values()):
      if i>=node['minimumDomains']:break
      walk(dict(type='choose-distinct-courses',minimumCount=1,eligibleCourses=codes),key+'/'+str(i),role)
    elif t=='allocation-group':
     for i,g in enumerate(node['groups']):walk(g,key+'/groups/'+str(i),role)
     count=lambda:len(set(used)-before)
     credits=lambda:sum(used[c]['credits'] for c in set(used)-before)
     if node.get('allowAdditionalEligibleCoursesToMeetCredits') or node.get('allowAdditionalEligibleUnitsToMeetCredits'):
      pool=[u for g in node['groups'] for u in units(g)]
      for unit in pool:
       if credits()>=node.get('minimumCredits',0) and count()>=node.get('minimumCount',node.get('minimumUnitCount',0)):break
       if all(c not in used for c in unit):
        for c in unit:add(c,role=role)
    elif t=='all-course-versions':
     for c in node['courses']:add(c['courseCode'],c['catalogVersion'],role)
    elif t=='all-courses':
     for c in node['courses']:add(c,role=role)
    else:
     needed=node.get('minimumCount',0 if t=='choose-credits' else 1);n=0;credits=0
     for unit in units(node):
      if n>=needed and credits>=node.get('minimumCredits',0):break
      if any(c in used for c in unit):continue
      for c in unit:add(c,role=role);credits+=used[c]['credits']
      n+=1
    return list(set(used)-before)
   for i,g in enumerate(program['groups']):walk(g,'/'+str(i),'group-'+str(i+1))
   if program['program']=='Psychology, B.S.':
    add('MA2090',role='science-plan');used['MA2090']['classifications']+=['precalculus-or-higher']
    semesters=[]
    for term,code in enumerate(['BS2400','BS2410','CP2120','PX2200'],2):
     add(code,role='science-plan');used[code]['termOrder']=term;used[code]['classifications']+=['qualifying-psychology-bs-science'];semesters.append(dict(termOrder=term,courseCodes=[code]))
    approvals['psychology-bs-math-science-plan']=approval('psychology-bs-math-science-plan',mathCourse='MA2090',scienceSemesters=semesters,advisorDeadlineReviewed=True)
   if program['program']=='Public Health, B.S.':approvals['applied-practice-90-hours']['hours']=90
   if program['program']=='Childhood Education 1-6, B.S.':
    approvals['school-of-education-admission'].update(completedCreditsAtAdmission=32,gpaAtAdmission=3.0,ED3900Grade='C',formalAdmissionReference='synthetic-admission',requiredProficienciesReviewed=True,introCoursesReviewed=True)
    approvals['education-field-experience']=approval('education-field-experience')
   if program['kind']=='major':
    minimum=program['degreeRequirements']['minimumTotalCredits']
    for code,m in metadata.items():
     if sum(a['credits'] for a in used.values())>=minimum:break
     remaining=minimum-sum(a['credits'] for a in used.values())
     if code not in used and code.startswith('HI') and int(re.search(r'\d+',code)[0])>=3000 and m['creditMinimum']==m['creditMaximum']==4 and remaining>=4:add(code,role='general-elective')
    for code,m in metadata.items():
     if sum(a['credits'] for a in used.values())>=minimum:break
     if code not in used and m['creditMinimum']==m['creditMaximum']==2:add(code,role='general-elective')
   student=dict(fixtureId='synthetic-'+program['program'],program='CIS' if program['program'].startswith('Computer and') else 'MIS',attempts=list(used.values()),proficiencies={c:approval('proficiency:'+c,basis='approved-equivalent',termOrder=0) for c in ['CompositionI','CompositionII']},isTransferStudent=False,completedStandingCredits=120,approvals=approvals,majorCourseCodes=[],majorAllocationApproval=approval('major-allocation'),homeDepartment='synthetic-home')
   if concentration:student['concentration']=concentration
   student['mathProficiencyApproval']=approval('proficiency:math')
   student['geWaiver']=approval('general-education-waiver',awardType='AS',institutionSystem='SUNY',awardEvidenceReference='synthetic-award')
   cases.append(dict(name=program['program'],kind=program['kind'],concentration=concentration,paths=choices,student=student,roles=roles))
path=Path(__file__).with_name('all-programs.json');path.write_text(json.dumps({'warning':'Synthetic approvals/attributes only; not official classifications or student records.','cases':cases},indent=2)+'\n')
print('Built',len(cases),'synthetic scenarios covering all',len(programs),'programs and staged tracks/paths.')
