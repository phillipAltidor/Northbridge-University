#!/usr/bin/env python3
import itertools,json,re,sys,unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];sys.path.insert(0,str(ROOT/'database/generation'))
from academic_planning import PlanningCatalog,source_sequences
R=ROOT/'database/staging/academic-alignment/rules'
def read(p):return json.loads(p.read_text())
programs=read(R/'curricula.json')['programs'];courses={r['courseCode']:{'courseCode':r['courseCode'],'catalogVersion':'2026-2027','credits':r['creditMinimum'],'level':int(re.search(r'\d+',r['courseCode'])[0]),'classifications':[]}for r in read(R/'course-metadata.json')['courses']if r['creditMinimum']is not None}
for v in read(R/'approved-historical-course-versions.json'):courses[v['versionID']]={'courseCode':v['courseCode'],'catalogVersion':v['sourceCatalog'],'credits':v['credits'],'classifications':[]}
rules={r['courseVersionOrCode']:r['rule']for r in read(R/'all-course-prerequisites.json')['courses']};rules['MA1020']=read(ROOT/'database/staging/academic-alignment/ma1020-entry-placement-policy.json')['rule']
ge=read(R/'general-education-mappings.json')['rows'];seq=source_sequences(read(ROOT/'database/staging/academic-alignment/program-review-inputs.json'))
class PlanningTests(unittest.TestCase):
 def setUp(self):self.p=PlanningCatalog(programs,courses,rules,ge,seq);self.person={'studentId':1,'major':'Computer and Information Science, B.S.','minor':'Accounting, Minor','concentration':None}
 def evaluate(self,n):
  if n['type']=='scoped-evidence'and n.get('scope')=='placement:MA1020-entry':return 'satisfied'
  return 'unsatisfied'
 def test_placement_does_not_create_statistics_dependency(self):
  choices=self.p.choices(self.person,set(),[],self.evaluate)
  self.assertEqual(choices['MA1020']['role'],'major-preparation')
  self.assertNotEqual(choices.get('MA2000',{}).get('role'),'major-preparation')
 def test_redundant_minor_math_prep_is_not_added_beside_algebra(self):
  choices=self.p.choices(self.person,set(),[],self.evaluate)
  self.assertTrue(self.p.redundant_preparation({'MA2000'},choices,['MA1020'],{'MA1020':choices['MA1020']}))
 def test_ge_does_not_offer_second_math_course(self):
  choices=self.p.choices({**self.person,'minor':None},set(),['MA1020'],self.evaluate)
  self.assertNotEqual(choices.get('MA2000',{}).get('role'),'general-education')
 def test_two_required_math_courses_are_not_blanket_banned(self):
  choices=self.p.choices({**self.person,'major':'Mathematics, B.S.','minor':None},set(),[],self.evaluate)
  self.assertEqual(choices['MA2310']['role'],'major-required')
  self.assertEqual(choices['MA3030']['role'],'major-required')
  self.assertFalse(self.p.redundant_preparation({'MA3030'},choices,['MA2310'],{'MA2310':choices['MA2310']}))
 def test_business_statistics_keeps_its_required_purpose(self):
  choices=self.p.choices({**self.person,'major':'Accounting, B.S.','minor':None},set(),[],self.evaluate)
  self.assertEqual(choices['MA2000']['role'],'major-required')
 def test_completed_choice_group_is_not_overfilled(self):
  goals=[{'id':'choice','units':[frozenset(['MA3160']),frozenset(['MA4100'])],'minimumCount':1,'minimumCredits':4,'fixed':False,'parent':None}]
  self.assertEqual(self.p.reservations(goals,{'MA3160'})[0],[])
 def test_required_course_does_not_also_fill_distinct_elective(self):
  goals=[{'id':'fixed','units':[frozenset(['CS3810'])],'minimumCount':1,'minimumCredits':0,'fixed':True,'parent':None},{'id':'elective','units':[frozenset(['CS3810']),frozenset(['CS4400'])],'minimumCount':1,'minimumCredits':4,'fixed':False,'parent':None}]
  remaining,_=self.p.reservations(goals,{'CS3810'});self.assertEqual([g['id']for g in remaining],['elective'])
 def test_composition_two_is_not_selected_with_one(self):
  choices=self.p.choices(self.person,set(),['EL1000'],self.evaluate);self.assertNotIn('EL2216',choices)
 def test_sample_duplicates_do_not_create_repeat_requirements(self):
  self.assertEqual(seq[self.person['major']]['courseTerms']['MA2310'],2)
  self.assertIn('MA2310',seq[self.person['major']]['repeatedCourseReferences'])
 def test_all_programs_and_concentrations_compile(self):
  checked=0
  for i,p in enumerate(programs):
   for concentration in p.get('concentrations',{})or[None]:
    person={'studentId':1000+checked,'major':p['program']if p['kind']=='major'else self.person['major'],'minor':p['program']if p['kind']=='minor'else None,'concentration':concentration}
    goals=self.p.goals(person,p['kind'],set());self.assertTrue(goals,p['program']);self.assertFalse(self.p.unsupported);checked+=1
  self.assertEqual(len(programs),24);self.assertGreaterEqual(checked,31)
 def test_all_published_path_alternatives_compile(self):
  checked=0
  for program in programs:
   for concentration in program.get('concentrations',{})or[None]:
    paths=[]
    def walk(n,label):
     if n['type']=='choose-one-path':paths.append((label,list(n['paths'])))
     for i,g in enumerate(n.get('groups',[])):walk(g,label+'/'+str(i))
    for i,g in enumerate(program['groups']):walk(g,program['kind']+'/'+str(i))
    for values in itertools.product(*(v for _,v in paths)):
     checked+=1;person={**self.person,'studentId':3000+checked,'major':program['program']if program['kind']=='major'else self.person['major'],'minor':program['program']if program['kind']=='minor'else None,'concentration':concentration,'planningPaths':dict(zip((k for k,_ in paths),values))}
     self.assertTrue(self.p.goals(person,program['kind'],set()));self.assertFalse(self.p.unsupported)
  self.assertEqual(checked,37)
 def test_allocation_parent_does_not_double_reserve_child_credits(self):
  fixture={k:{**courses[k]}for k in ['MA1020','MA2000','MA2090']}
  program={'program':'Fixture','groups':[{'type':'allocation-group','minimumCredits':8,'groups':[{'type':'all-courses','courses':['MA1020','MA2090']}]}],'degreeRequirements':{'minimumTotalCredits':12,'minimumLiberalArtsCredits':0,'minimumUpperDivisionCredits':0}}
  planner=PlanningCatalog([program],fixture,{},[])
  choices=planner.choices({**self.person,'major':'Fixture','minor':None},set(),[],self.evaluate)
  self.assertEqual(choices['MA2000']['role'],'general-elective')
 def test_freshman_is_not_assigned_upper_division_just_to_fill_distribution(self):
  choices=self.p.choices(self.person,set(),[],self.evaluate)
  self.assertFalse(any(v['role']=='degree-distribution'for v in choices.values()))
 def test_minor_outside_major_floor_still_proposes_unshared_choices(self):
  person={**self.person,'major':'Physics, B.S.','minor':'Mathematics, Minor','concentration':'General Physics'}
  held={'MA2310','MA2320','MA3030','MA3160','MA3210','MA3330'}
  choices=self.p.choices(person,held,[],self.evaluate)
  self.assertTrue(any(v['role']=='minor-overlap'for v in choices.values()))
 def test_normal_elective_can_close_one_credit_degree_remainder(self):
  fixture={k:{'courseCode':k,'catalogVersion':'2026-2027','credits':cr,'level':1000,'classifications':[]}for k,cr in [('EL1000',4),('EL2216',4),('XX1000',3),('XX1100',4)]}
  program={'program':'Fixture','groups':[],'degreeRequirements':{'minimumTotalCredits':12,'minimumLiberalArtsCredits':0,'minimumUpperDivisionCredits':0}}
  planner=PlanningCatalog([program],fixture,{},[])
  person={**self.person,'major':'Fixture','minor':None}
  choices=planner.choices(person,{'EL1000','EL2216','XX1000'},[],self.evaluate)
  self.assertEqual(choices['XX1100']['role'],'general-elective')
  self.assertNotIn('XX1100',planner.choices(person,{'EL1000','EL2216','XX1000','XX1100'},[],self.evaluate))
if __name__=='__main__':unittest.main()
