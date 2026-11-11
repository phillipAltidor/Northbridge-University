#!/usr/bin/env python3
"""Exercise resource feasibility, protected reservations, and atomic proposals."""
import copy,sys,unittest
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'database/generation'))
from faculty_planning import rebalance_faculty

class FacultyRebalancingTests(unittest.TestCase):
 def setUp(self):
  self.source={'Faculty':[{'faculty_ID':1,'faculty_Type':'Part-Time'},{'faculty_ID':2,'faculty_Type':'Part-Time'}],
   'Course':[],'Course_Section':[]}
  self.pattern=[{'monday':True,'beginTime':'0900','endTime':'1000','startDate':'01/20/2026','endDate':'05/20/2026'}]
  self.existing={'termId':1,'departmentId':1,'facultyId':1,'meetings':self.pattern}
  self.proposed={'termId':1,'departmentId':2,'meetings':self.pattern}
 def test_releases_cross_appointed_faculty_without_mutating_inputs(self):
  before=copy.deepcopy(self.existing)
  answer=rebalance_faculty(self.source,[self.existing],self.proposed,{1:{1,2},2:{1}})
  self.assertEqual(answer,[2,1]);self.assertEqual(self.existing,before)
 def test_never_exceeds_workload(self):
  self.assertIsNone(rebalance_faculty(self.source,[self.existing],self.proposed,{1:{1},2:{1}}))
 def test_never_assigns_overlapping_sections_to_full_time_faculty(self):
  self.source['Faculty'][0]['faculty_Type']='Full-Time'
  self.assertIsNone(rebalance_faculty(self.source,[self.existing],self.proposed,{1:{1},2:{1}}))
 def test_preserves_graduate_reservations(self):
  self.source.update({'Course':[{'course_ID':'G','course_Type':'Graduate'}],
   'Course_Section':[{'semester_ID':1,'course_ID':'G','faculty_ID':2,'time_Slot_ID':1}],
   'Time_Slot_Day':[{'time_Slot_ID':1,'day_ID':1}], 'Day':[{'day_ID':1,'day_Of_Week':'Monday'}],
   'Time_Slot_Period':[{'time_Slot_ID':1,'period_ID':1}],
   'Period':[{'period_ID':1,'start_Time':'09:00:00','end_Time':'10:00:00'}]})
  self.assertIsNone(rebalance_faculty(self.source,[self.existing],self.proposed,{1:{1,2},2:{1}}))
 def test_keeps_feasible_existing_assignment(self):
  self.assertEqual(rebalance_faculty(self.source,[self.existing],self.proposed,{1:{1,2},2:{2}}),[1,2])

if __name__=='__main__':unittest.main()
