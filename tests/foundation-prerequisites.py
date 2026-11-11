#!/usr/bin/env python3
import copy,json,sys,unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];sys.path.insert(0,str(ROOT/'database/generation'))
from rebuild_foundation_proofs import foundation_rules
from rebuild_eligibility import Eligibility
raw={r['courseVersionOrCode']:r['rule']for r in json.load(open(ROOT/'database/staging/academic-alignment/rules/all-course-prerequisites.json'))['courses']}
rules=foundation_rules(raw)
codes=['EL1000','BS2400','BS2401','BS2410','BS2411','MA1020','MA2000','BU3071','BU3250','CS2521']
courses={k:{'courseCode':k,'catalogVersion':'2026-2027','credits':4}for k in codes}
def attempt(code,term=1,grade='B'):return {'courseKey':code,'termId':term,'grade':grade,'status':'completed'if grade is not None else'in-progress'}
def state(course,rows):return Eligibility(courses,rows,{1:0,2:1,3:2},2,{'studentId':1},course).evaluate(rules[course]['rule'])['status']
class FoundationTests(unittest.TestCase):
 def test_changed_source_requires_review(self):
  changed=copy.deepcopy(raw);changed['BS2400']={'type':'no-course-prerequisites'}
  with self.assertRaises(ValueError):foundation_rules(changed)
 def test_biology_one_requires_writing_and_paired_lab(self):
  rows=[attempt('EL1000'),attempt('BS2400',2,None)]
  self.assertEqual(state('BS2400',rows),'unsatisfied')
  self.assertEqual(state('BS2400',rows+[attempt('BS2401',2,None)]),'conditional')
 def test_same_term_writing_does_not_satisfy_completed_writing(self):
  self.assertEqual(state('BS2400',[attempt('EL1000',2,None),attempt('BS2401',2,None)]),'unsatisfied')
 def test_biology_two_requires_c_in_both_first_courses(self):
  rows=[attempt('BS2400'),attempt('BS2401',grade='D'),attempt('MA1020'),attempt('BS2411',2,None)]
  self.assertEqual(state('BS2410',rows),'unsatisfied')
  rows[1]=attempt('BS2401',grade='C');self.assertEqual(state('BS2410',rows),'conditional')
 def test_biology_two_does_not_waive_math(self):
  self.assertNotEqual(state('BS2410',[attempt('BS2400'),attempt('BS2401'),attempt('BS2411',2,None)]),'satisfied')
 def test_business_cross_list_has_explicit_sufficient_route(self):self.assertEqual(state('BU4180',[attempt('BU3250')]),'satisfied')
 def test_business_data_mining_retains_all_four_conditions(self):
  rows=[attempt(k)for k in ['MA2000','BU3071','BU3250']];self.assertEqual(state('BU5180',rows),'unsatisfied')
  self.assertEqual(state('BU5180',rows+[attempt('CS2521')]),'satisfied')
if __name__=='__main__':unittest.main()
