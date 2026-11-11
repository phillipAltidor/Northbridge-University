#!/usr/bin/env python3
"""Validate rule definitions and source-derived edge cases; no DB/network access."""
import importlib.util,json,re
from pathlib import Path
R=Path(__file__).resolve().parent;O=R/'rules'
def load(n):return json.loads((O/n).read_text())
checks=0
def check(condition,message):
 global checks
 if not condition:raise AssertionError(message)
 checks+=1
programs=load('curricula.json')['programs'];by={p['program']:p for p in programs}
meta={c['courseCode']:c for c in load('course-metadata.json')['courses']}
check(len(programs)==24 and len(by)==24,'24 distinct selected programs')
check(sum(p['kind']=='major' for p in programs)==12,'12 majors')
check(sum(p['kind']=='minor' for p in programs)==12,'12 minors')
check(by['Accounting, B.S.']['degreeRequirements']['minimumTotalCredits']==126,'Accounting 126-credit override')
check(by['Accounting, B.S.']['degreeRequirements']['minimumInstitutionalResidencyCredits']==46,'Accounting residency override')
check(by['English, B.A.']['degreeRequirements']['minimumLiberalArtsCredits']==90,'BA 90 liberal arts')
check(by['Psychology, B.S.']['degreeRequirements']['minimumLiberalArtsCredits']==60,'approved Psychology BS 60 liberal arts')
check(len(by['Physics, B.S.']['concentrations'])==4,'all four Physics paths')
check(len(by['English, B.A.']['concentrations'])==3,'all three English paths')
check(len(by['Business Administration, B.S.']['concentrations'])==3,'all three Business paths')
check(list(by['Childhood Education 1-6, B.S.']['concentrations'])==['Mathematics'],'explicit initial Education scope')
check('XX9990' not in meta,'placeholder not a fabricated course')
check(meta['SY2222']['sourceTitle']=='Care & Care Work' and len(meta['SY2222']['titleAliases'])==2,'duplicate title resolution preserves aliases')
check(meta['CS5620']['creditMinimum']==2 and meta['CS5620']['creditMaximum']==4,'variable credits preserved')
check(meta['ED5940']['creditMinimum']==12,'student teaching actual 12 credits')
check(meta['BS2400']['creditMinimum']==3 and meta['BS2401']['creditMinimum']==1,'lecture/lab credits kept separate')
check(meta['BU4190']['creditMinimum'] is None,'unknown credit not invented')

def walk(v):
 if isinstance(v,list):
  for x in v:yield from walk(x)
 elif isinstance(v,dict):
  yield v
  for x in v.values():
   if isinstance(x,(dict,list)):yield from walk(x)
for p in programs:
 for node in walk([p['groups'],p.get('concentrations',{})]):
  if node.get('type')=='all-courses' and 'minimumCredits' in node:
   check(all(c in meta for c in node['courses']),'fixed group known courses: '+p['program'])
   check(sum(meta[c]['creditMinimum'] or 0 for c in node['courses'])>=node['minimumCredits'],'fixed-group credit arithmetic: '+p['program'])
  if node.get('type')=='choose-distinct-courses':check(len(set(node['eligibleCourses']))>=node['minimumCount'],'enough distinct options: '+p['program'])
mis=by['Management Information Systems, B.S.']['groups'][2]
check(len(set(['MA2701','MA2701']) & set(mis['eligibleCourses']))<mis['minimumCount'],'duplicate attempt cannot fill two MIS choices')
check(len(set(['MA2701','PE2420']) & set(mis['eligibleCourses']))>=mis['minimumCount'],'valid two-of-three MIS choice')
minor=by['Computer Information Science, Minor']['groups'][1]
check(set(minor['excludeCourses'])=={'CS4100','CS5620'},'CS minor exclusions')
check(by['Public Health, Minor']['policies']['maximumCoursesSharedWithMajor']==1,'PH overlap limit')
check(by['Accounting, Minor']['policies']['minimumCreditsOutsideMajor']==20,'Accounting minor outside-major credits')

rows=load('all-course-prerequisites.json')['courses'];rules={c['courseVersionOrCode']:c for c in rows}
# A narrow fixture interpreter tests actual AST branches; it is not the production evaluator.
points=load('institutional-rules.json')['gradePoints']
def eligible(node,grades=None,writing=False,permission=False):
 grades=grades or {};kind=node['type']
 if kind=='and':return all(eligible(n,grades,writing,permission) for n in node['rules'])
 if kind=='or':return any(eligible(n,grades,writing,permission) for n in node['rules'])
 if kind=='course':
  grade=grades.get(node['courseCode']);minimum=node.get('minimumGrade')
  return grade in points and grade!='F' and (minimum is None or points[grade]>=points[minimum])
 if kind=='proficiency':return writing if node['key'] in ['EMS','ECI','ECII'] else False
 if kind=='completed-writing-proficiency':return writing
 if kind=='approval':return permission
 if kind=='no-course-prerequisites':return True
 return False # Unsupported/unknown evidence can never pass a fixture by default.
software=rules['CS4501']['rule']
check(eligible(software,{'CS2511':'C','CS3810':'D','CS3611':'B'}),'software engineering alternative route')
check(not eligible(software,{'CS2511':'D','CS3810':'A','CS3611':'A'}),'CS2511 C minimum retained on both paths')
check(not eligible(software,{'CS2511':'A'}),'missing alternative does not pass')
capstone=rules['CS5910']['rule'];all_three={'CS4501':'B','CS4550':'C','CS4720':'A'}
check(not eligible(capstone,all_three),'capstone writing still required')
check(eligible(capstone,all_three,writing=True),'capstone all course and writing evidence')
check(not eligible(capstone,{'CS4501':'A','CS4550':'A'},writing=True),'capstone retains CS4720')
data=rules['CS5810']['rule']
check(eligible(data,{'CS4550':'C','MA2000':'B'},writing=True),'Data Mining Applied Statistics route')
check(eligible(data,{'CS4550':'C','MA3210':'B'},writing=True),'Data Mining Probability route')
check(not eligible(data,{'MA3210':'A'},writing=True),'Data Mining database prerequisite retained')
check(rules['MA2000']['rule']['sourceExplicitlyStatesNone'],'explicit no prerequisites preserved')
check(rules['PH2000']['status']=='review-gated','missing prerequisite statement remains review-gated')
check(any(n.get('type')=='unresolved-course-reference' for n in walk(rules['PY5210']['rule'])),'unmatched old code retained at its condition')
check(len({r['courseVersionOrCode'] for r in rows})==len(rows),'unique course-version rule identities')
check('ED4050@2020-2022' in rules and 'ED5925@2020-2022' in rules,'historical versions remain explicit')
check(rules['ED5925@2020-2022']['status']=='review-gated','malformed ED condition cannot silently pass')
manual=load('manual-review-register.json')
check(len(manual['prerequisites'])==sum(c['status']=='review-gated' for c in rows),'manual inventory accounts for every review-gated record')
status=load('phase-one-status.json')
check(status['allOfficialSourceQuestionsResolved'] is False,'package does not falsely certify all sources')
check(status['workingDatabaseChanged'] is False,'normalization has no working database side effects')
for p in O.glob('*.json'):json.loads(p.read_text())
print(f'PASS: {checks} curriculum, credit, prerequisite-branch, version and review-gate checks. No DB/network access.')
