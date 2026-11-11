#!/usr/bin/env python3
import copy,sys,unittest
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'database/generation'))
from in_person_sections import template_options,target_term_templates,two_day_lecture_pattern
from academic_meetings import meetings
class TemplateTests(unittest.TestCase):
 def setUp(self):
  self.course={'credits':4,'departmentId':6,'labBearing':False}
  self.courses={'EL1000':self.course}
  self.row={'subject':'EL','courseNumber':'1000','courseReferenceNumber':'90001','sequenceNumber':'001','instructionalMethodDescription':'On Campus','creditHours':4,'maximumEnrollment':30,'meetings':[{'beginTime':'0950','endTime':'1130','startDate':'08/24/2026','endDate':'12/16/2026','monday':True,'wednesday':True}]}
 def options(self,row=None,excluded=None):return template_options(self.course,[row or self.row],self.courses,excluded or set())
 def test_preserves_exact_source_without_mutation(self):
  before=copy.deepcopy(self.row);options=self.options();self.assertTrue(options[0]['northbridgeOnly']);self.assertEqual(options[0]['meetings'],self.row['meetings']);self.assertEqual(self.row,before)
 def test_excludes_hybrid(self):
  row={**self.row,'instructionalMethodDescription':'Hybrid'};self.assertFalse(self.options(row))
 def test_excludes_linked_shared_seat_template(self):self.assertFalse(self.options(excluded={'90001'}))
 def test_excludes_credit_mismatch(self):self.assertFalse(self.options({**self.row,'creditHours':3}))
 def test_requires_lab_match(self):
  self.courses={'EL1000':{**self.course,'labBearing':True}};self.assertFalse(self.options())
 def test_four_credits_cannot_use_one_hundred_minutes(self):
  row=copy.deepcopy(self.row);row['meetings'][0]['wednesday']=False;self.assertFalse(self.options(row))
 def test_excludes_short_term(self):
  row=copy.deepcopy(self.row);row['meetings'][0]['endDate']='09/01/2026';self.assertFalse(self.options(row))
 def test_cross_term_template_preserves_periods_and_separates_dates(self):
  target=copy.deepcopy(self.row);target['meetings'][0].update({'startDate':'01/25/2027','endDate':'05/18/2027'})
  result=target_term_templates([self.row],[target],'202609')[0]
  self.assertEqual(result['sourceOriginalMeetings'],self.row['meetings']);self.assertEqual(result['templateSourceTerm'],'202609')
  self.assertEqual(result['meetings'][0]['beginTime'],'0950');self.assertEqual(result['meetings'][0]['startDate'],'01/25/2027')
  self.assertEqual(self.row['meetings'][0]['startDate'],'08/24/2026')
 def test_single_two_hundred_minute_block_is_not_two_day_lecture(self):
  row=copy.deepcopy(self.row);row['meetings'][0].update({'monday':False,'wednesday':False,'friday':True,'endTime':'1310'})
  self.assertFalse(two_day_lecture_pattern(meetings(row)))
 def test_prefers_verified_two_day_lecture_template_without_editing_source(self):
  friday=copy.deepcopy(self.row);friday.update({'courseReferenceNumber':'80001'});friday['meetings'][0].update({'monday':False,'wednesday':False,'friday':True,'endTime':'1310'})
  before=copy.deepcopy([friday,self.row]);options=template_options(self.course,[friday,self.row],self.courses,set())
  self.assertTrue(two_day_lecture_pattern(meetings(options[0])));self.assertEqual([friday,self.row],before)
 def test_preference_does_not_rewrite_lab_pattern(self):
  lab={**self.course,'labBearing':True};row=copy.deepcopy(self.row);row['meetings'][0].update({'monday':False,'wednesday':False,'friday':True,'endTime':'1310'})
  options=template_options(lab,[row],{'EL1000':lab},set());self.assertEqual(options[0]['meetings'],row['meetings'])
if __name__=='__main__':unittest.main()
