#!/usr/bin/env python3
import copy,importlib.util,unittest
from pathlib import Path
spec=importlib.util.spec_from_file_location('repair',Path(__file__).resolve().parents[1]/'tools/repair-historical-loads.py')
module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)

class RepairTests(unittest.TestCase):
 def fixture(self):
  r=module.Repair.__new__(module.Repair)
  r.c={'courses':{k:{'credits':4}for k in ['A','B','C','D']},'academicPlanningPurposes':[],
       'reconstructionTermHorizon':{'1':['202509','202602'],'2':['202509','202602']}}
  r.people={sid:{'mode':'Full-Time'}for sid in [1,2]};r.termids={'202509':3,'202602':4,'202702':2}
  r.rows={1:[],2:[]};r.s={'sections':[]}
  for sid,terms in [(1,{'202509':['B','C','D'],'202602':['A']}),(2,{'202509':['A','B','C','D'],'202602':['B','C','D']})]:
   for term,keys in terms.items():
    for key in keys:
     r.rows[sid].append({'studentId':sid,'termCode':term,'termId':r.termids[term],'courseKey':key,'grade':'B+','status':'completed'})
     r.s['sections'].append({'term':term,'courseKey':key,'studentIds':[sid],'capacity':1,'deliveryGroup':sid,'facultyId':1})
     r.c['academicPlanningPurposes'].append({'studentId':sid,'term':term,'courseKey':key,'reason':'Required coursework'})
  r.resource_cache={};r.staffing_cache={};r.patterns={};r.events=[]
  r.valid=lambda sid,rows:True;r.pattern=lambda s:[];r.busy=lambda sid,term:[]
  def allocation(sid,key,term):
   for sec in r.s['sections']:
    if sec['term']==term and sec['courseKey']==key and len(sec['studentIds'])<sec['capacity']:return False,sec
   return None
  r.allocation=allocation
  return r
 def test_two_way_exchange_uses_vacated_seat_preserves_grades_and_other_load(self):
  r=self.fixture();a=next(x for x in r.rows[1]if x['courseKey']=='A')
  self.assertTrue(r.exchange_seat(1,a,'202509'))
  self.assertEqual(r.load(2,'202509'),12);self.assertEqual(r.load(2,'202602'),16)
  self.assertTrue(all(x['grade']=='B+'for rows in r.rows.values()for x in rows))
  self.assertTrue(all(len(s['studentIds'])<=s['capacity']for s in r.s['sections']))
  self.assertEqual(sum(len(s['studentIds'])for s in r.s['sections']),sum(map(len,r.rows.values())))
 def test_failed_second_move_rolls_back_both_rosters_attempts_and_purposes(self):
  r=self.fixture();before=copy.deepcopy((r.c,r.s,r.rows));calls=0
  def valid(sid,rows):
   nonlocal calls
   if sid==1:calls+=1
   return not(sid==1 and calls>1)
  r.valid=valid;a=next(x for x in r.rows[1]if x['courseKey']=='A')
  self.assertFalse(r.exchange_seat(1,a,'202509'))
  self.assertEqual((r.c,r.s,r.rows),before);self.assertEqual(r.events,[])
 def test_historical_course_moved_to_future_has_no_final_grade(self):
  r=self.fixture();r.s['sections'].append({'term':'202702','courseKey':'A','studentIds':[],'capacity':1})
  a=next(x for x in r.rows[1]if x['courseKey']=='A')
  self.assertTrue(r.move(1,a,'202702'));pending=next(x for x in r.rows[1]if x['courseKey']=='A')
  self.assertIsNone(pending['grade']);self.assertEqual(pending['status'],'planned')
 def test_compaction_preserves_course_attempts_grades_and_capacity(self):
  r=self.fixture();before=copy.deepcopy(r.rows)
  r.s['sections']=[{'term':'202602','courseKey':'A','studentIds':[sid],'capacity':2,'deliveryGroup':sid,'facultyId':sid}for sid in [1,2]]
  r.compact_sections('202602')
  self.assertEqual(len(r.s['sections']),1);self.assertEqual(sorted(r.s['sections'][0]['studentIds']),[1,2]);self.assertEqual(r.rows,before)
 def test_compaction_rejects_conflicts_in_both_directions(self):
  r=self.fixture()
  r.s['sections']=[{'term':'202602','courseKey':key,'studentIds':[sid],'capacity':2,'deliveryGroup':sid,'facultyId':sid,'pattern':[(0,start,start+10,1,100)]}for sid,key,start in [(1,'A',10),(2,'A',20),(1,'B',20),(2,'C',10)]]
  r.pattern=lambda s:s['pattern'];before=copy.deepcopy(r.s)
  r.compact_sections('202602');self.assertEqual(r.s,before)
 def test_failed_consolidation_restores_other_students_after_successful_exchange(self):
  r=self.fixture();r.c['courses']['E']={'credits':4};r.c['plannedTermStudents']={'202509':[1,2],'202602':[1,2]}
  r.rows[1].append({'studentId':1,'termCode':'202602','termId':4,'courseKey':'E','grade':'A','status':'completed'})
  r.s['sections'].append({'term':'202602','courseKey':'E','studentIds':[1],'capacity':1,'facultyId':1,'deliveryGroup':1})
  before=copy.deepcopy(r.rows);c,s,summary=r.run()
  self.assertEqual(r.rows,before);self.assertEqual(summary['status'],'candidate-blocked')
  self.assertFalse(any(e['kind']=='historical-seat-freed-by-validated-relocation'for e in r.events))

if __name__=='__main__':unittest.main()
