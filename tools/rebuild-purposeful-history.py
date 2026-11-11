#!/usr/bin/env python3
"""Chronological, private reconstruction of owner-authorized fictional academics."""
import argparse,copy,importlib.util,json,os,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];sys.path.insert(0,str(ROOT/'database/generation'))
from rebuild_oldwestbury import Builder
from historical_planning import initialize_entry_evidence

if __name__=='__main__':
 p=argparse.ArgumentParser()
 for name in ['source','candidate','schedule','catalogue','output']:p.add_argument('--'+name,required=True)
 p.add_argument('--resume-after')
 a=p.parse_args()
 for key,value in vars(a).items():
  if key=='resume_after':continue
  if not str(Path(value).resolve()).startswith('/private/tmp/'):raise SystemExit('Private paths required')
 os.umask(0o077);out=Path(a.output);out.mkdir(mode=0o700,exist_ok=False)
 read=lambda name:json.loads(Path(getattr(a,name)).read_text())
 source=read('source');candidate=read('candidate');schedule=read('schedule');catalogue=read('catalogue');b=Builder(source)
 horizon={}
 for row in candidate['attempts']:horizon.setdefault(str(row['studentId']),set()).add(row['termCode'])
 candidate['reconstructionTermHorizon']={k:sorted(v)for k,v in horizon.items()}
 terms=sorted({(r['termCode'],r['termId'])for r in candidate['attempts']});term_codes={tid:term for term,tid in terms}
 candidate['historicalRebuildPolicy']={'status':'owner-authorized-fictional-reconstruction-test-only','approvedAt':'2026-11-09','basis':'Owner approved full historical reconstruction in the separate test database before presentation. Original records remain backed up.','gradePolicy':'Preserve the original grade for the same course/term where possible; otherwise reuse values from that fictional student/term grade pool and record reconstruction provenance. No current or future grade is assigned.','entryPolicy':'Owner-approved simulated basic entry placement is bound to each fictional admission term. Composition II eligibility follows completed Composition I at C or better and remains separate from Composition II completion.','grantsRealTranscriptEquivalences':False,'workingDatabaseChanged':False,'schemaChanged':False}
 if not a.resume_after:
  initialize_entry_evidence(candidate,b.termpos,term_codes)
  candidate.pop('academicPlanningPurposes',None);candidate.pop('plannedTermStudents',None)
 elif a.resume_after not in candidate.get('plannedTermStudents',{}):raise SystemExit('A completed chronological checkpoint is required')
 spec=importlib.util.spec_from_file_location('purposeful',ROOT/'tools/build-purposeful-schedules.py');module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
 summaries=[]
 for term,tid in terms:
  if a.resume_after and term<=a.resume_after:
   summaries.append({'term':term,'status':'retained-chronological-checkpoint','sourceDirectory':str(Path(a.candidate).parent)});continue
  stage=out/term;stage.mkdir(mode=0o700)
  candidate,schedule,summary=module.run(source,candidate,schedule,stage,term,catalogue);summaries.append(summary)
  print('HISTORY_PROGRESS',json.dumps({'term':term,'termsFinished':len(summaries),'totalTerms':len(terms),'underloadedStudents':summary['underloadedStudents']}),flush=True)
 for name,value in [('candidate.json',candidate),('schedule.json',schedule),('summary.json',{'status':schedule['status'],'terms':summaries,'schemaChanged':False,'databaseChanged':False,'activationAllowed':False})]:(out/name).write_text(json.dumps(value,indent=2)+'\n')
