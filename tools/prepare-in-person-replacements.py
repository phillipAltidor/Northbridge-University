#!/usr/bin/env python3
"""Stage a conservative in-person replacement rehearsal; never writes MySQL.
Private arguments may contain fictional student histories. Public reports contain
section/course data only. A partial schedule is not registration authorization.
"""
import argparse,collections,copy,json,os,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'database/generation'))
from rebuild_oldwestbury import Builder,canonical
from academic_meetings import meetings
from schedule_oldwestbury import build

def prepare(source,candidate,old_schedule,output):
    b=Builder(source);work=copy.deepcopy(candidate);term='202609'
    # Omit TBA-only records: On Campus alone does not supply schedulable times.
    for key,values in list(b.offer.items()):b.offer[key]=[r for r in values if meetings(r)]
    fresh=[]
    for p in ['/Users/phillipaltidor/Downloads/searchResults.json','/Users/phillipaltidor/Downloads/searchResults (1).json']:
        fresh+=json.loads(Path(p).read_text())['data']
    bycrn={r['courseReferenceNumber']:r for r in fresh}
    risks={r['courseReferenceNumber'] for r in fresh if r.get('isSectionLinked') or r.get('crossList') or (r.get('crossListCapacity') or 0)>0}
    # No schema support for coupled registration/shared seats has been authorized.
    for key,values in list(b.offer.items()):
        if key[0]==term:b.offer[key]=[r for r in values if r['courseReferenceNumber'] not in risks]
    options={};deferred=[]
    for key,ids in candidate['rosters'][term].items():
        if not ids:continue
        credits=candidate['courses'][key]['credits'];valid=[]
        for r in b.sources(term,key):
            lo=r.get('creditHours') if r.get('creditHours') is not None else r.get('creditHourLow');hi=r.get('creditHourHigh')
            if lo!=credits or (hi is not None and hi!=credits):continue
            valid.append(r)
        options[key]=valid
        if not valid:deferred.append({'courseKey':key,'affectedStudents':len(ids),'reason':'No verified timed in-person same-credit offering without unresolved linked/shared-seat requirements.'})
    section_reviews=[]
    for sec in old_schedule['sections']:
        if sec['term']!=term:continue
        if sec.get('officialCRN') and sec['instructionalMethodDescription']=='On Campus':continue
        opts=[]
        for r in options.get(sec['courseKey'],[]):
            code=r['subject']+r['courseNumber'];conversion='@' in sec['courseKey'] or code!=canonical(sec['courseKey'])
            opts.append({'sourceCRN':r['courseReferenceNumber'],'officialCRN':None if conversion else r['courseReferenceNumber'],'section':r['sequenceNumber'],'sourceCourseCode':code,'credits':candidate['courses'][sec['courseKey']]['credits'],'modality':r['instructionalMethodDescription'],'meetings':r['meetings'],'conversionTemplateOnly':conversion})
        section_reviews.append({'courseKey':sec['courseKey'],'deliveryGroup':sec['deliveryGroup'],'oldOfficialCRN':sec.get('officialCRN'),'oldModality':sec['instructionalMethodDescription'],'students':len(sec['studentIds']),'alternatives':opts,'disposition':'candidate-alternatives' if opts else 'needs-course-resequencing'})
    # Conservative rehearsal keeps course meanings/grades intact. Unavailable
    # courses are explicitly deferred, not replaced by unrelated general electives.
    removed=[a for a in work['attempts'] if str(a['termCode'])==term and not options.get(a['courseKey'])]
    work['attempts']=[a for a in work['attempts'] if a not in removed]
    work['rosters']={term:{k:ids for k,ids in candidate['rosters'][term].items() if ids and options.get(k)}}
    for k,rs in options.items():
        # Make the scheduler use exactly the reviewed option set.
        sourcecode=rs[0]['subject']+rs[0]['courseNumber'] if rs else canonical(k)
        if rs:b.offer[term,sourcecode]=rs
    # Scheduler constructs its own Builder; persist exclusions by passing an
    # instance-local constructor below, never by editing official source files.
    import schedule_oldwestbury as scheduler
    original=scheduler.Builder;scheduler.Builder=lambda _:b
    try:scheduled=build(source,work,only_term=term,faculty_seconds=30)
    finally:scheduler.Builder=original
    scheduled['status']='staged-partial-not-ready-for-activation'
    scheduled['deferredAttempts']=removed
    scheduled['activationAllowed']=False
    (output/'candidate.json').write_text(json.dumps(work,indent=2)+'\n')
    (output/'schedule.json').write_text(json.dumps(scheduled,indent=2)+'\n')
    # Check load after deferrals; a seat/conflict-feasible partial timetable must
    # never be mistaken for a complete student academic plan.
    loads=collections.Counter()
    for a in work['attempts']:
        if str(a['termCode'])==term:loads[a['studentId']]+=work['courses'][a['courseKey']]['credits']
    people={p['studentId']:p for p in candidate['students']}
    active={a['studentId'] for a in candidate['attempts'] if str(a['termCode'])==term}
    under=sum(loads[s]<(12 if people[s]['mode']=='Full-Time' else 4)for s in active)
    counts=collections.Counter(r['disposition'] for r in section_reviews)
    report={'status':'staged-alternatives-with-explicit-blockers','term':'Fall 2026','reviewedAffectedSections':len(section_reviews),'dispositions':dict(counts),'sections':section_reviews,'deferredCourses':deferred,'deferredAttempts':len(removed),'studentsBelowMinimumAfterDeferrals':under,'scheduledPartialSections':len(scheduled['sections']),'solverFailures':scheduled['failures'],'unresolvedLinkedOrCrossListedSourceSectionsExcluded':len(risks),'activationAllowed':False,'schemaChanged':False,'databaseChanged':False,'privateCandidateDirectory':str(output),'sequencingReview':{'status':'requires-reviewed-placement-route-before-algebra-acceleration','issue':'Reviewed stored MA1020 rule currently permits MA1010 or MA2000 at C. The universal entry-proficiency display policy grants no course-specific placement equivalence. Moving MA1020 before MA2000 therefore needs a reviewed explicit placement route; do not fabricate a grade or claim the display check satisfies it.','proposal':'Add a documented fictional MA1020 admission-placement route consistent with the source syllabus, then prioritize MA1020 before CS2510. Recheck all programs and Spring prerequisites after resequencing.'},'notes':['Alternatives are not confirmed enrollments.','Conversion templates retain null official CRN; they do not become official offerings of historical courses.','Current/future grades are not invented.','No teaching resource or load failure is bypassed.']}
    (ROOT/'database/staging/academic-alignment/in-person-replacement-plan.json').write_text(json.dumps(report,indent=2)+'\n')
    print(json.dumps({k:v for k,v in report.items() if k not in ['sections','deferredCourses','sequencingReview','notes']}))

if __name__=='__main__':
    p=argparse.ArgumentParser();p.add_argument('--source',required=True);p.add_argument('--candidate',required=True);p.add_argument('--schedule',required=True);p.add_argument('--output',required=True);a=p.parse_args()
    for v in [a.source,a.candidate,a.schedule,a.output]:
        if not str(Path(v).resolve()).startswith('/private/tmp/'):raise SystemExit('Private temporary paths required')
    os.umask(0o077);out=Path(a.output);out.mkdir(mode=0o700,parents=True,exist_ok=False)
    prepare(json.loads(Path(a.source).read_text()),json.loads(Path(a.candidate).read_text()),json.loads(Path(a.schedule).read_text()),out)
