"""Explicit fictional-history policy; never represents authentic school records."""
import copy

def initialize_entry_evidence(candidate,positions,term_codes):
    first={}
    for row in candidate['attempts']:
        sid=row['studentId'];tid=row['termId']
        if sid not in first or positions[tid]<positions[first[sid]]:first[sid]=tid
    for person in candidate['students']:
        sid=person['studentId'];tid=first.get(sid)
        if tid is None:continue
        evidence=person.setdefault('registrarEvidence',{})
        for scope in ['placement:ECI','placement:MA1020-entry']:
            original=copy.deepcopy(evidence.get(scope))
            record={'status':'approved','scope':scope,'studentId':sid,'effectiveTermPosition':positions[tid],'termOrder':int(term_codes[tid]),'approvalId':'NB-HISTORY-ENTRY-'+scope.replace(':','-')+'-'+str(sid),'approvedBy':'Owner-approved Northbridge fictional undergraduate admission simulation','approvedAt':'2026-11-09','fictionalSimulation':True,'basis':'Owner approved basic writing and mathematics entry placement for fictional undergraduates; no exam score, course credit, equivalency, or prerequisite completion is granted.'}
            if scope=='placement:MA1020-entry':record['authority']='northbridge-admissions-simulation'
            evidence[scope]=record
            person.setdefault('historyEvidenceReview',{})[scope]={'original':original,'replacement':copy.deepcopy(record)}

def refresh_writing_eligibility(candidate,histories,positions,target_tid,target_term):
    for person in candidate['students']:
        sid=person['studentId'];evidence=person.setdefault('registrarEvidence',{})
        completed=[a for a in histories[sid]if a['courseKey']=='EL1000'and positions[a['termId']]<positions[target_tid]and a['status']=='completed'and a.get('grade')in ['A','A-','B+','B','B-','C+','C']]
        if not completed:continue
        # This is a documented fictional eligibility decision based on an actual
        # reconstructed passing course, not a fabricated entrance-exam result.
        first=min(completed,key=lambda a:positions[a['termId']]);effective=positions[first['termId']]+1
        prior=copy.deepcopy(evidence.get('placement:ECII'))
        if prior and prior.get('status')=='approved'and prior.get('effectiveTermPosition',10**9)<=effective:continue
        evidence['placement:ECII']={'status':'approved','scope':'placement:ECII','studentId':sid,'effectiveTermPosition':effective,'termOrder':int(target_term),'approvalId':'NB-HISTORY-WRITING-II-'+str(sid),'approvedBy':'Owner-approved Northbridge fictional academic progression review','approvedAt':'2026-11-09','fictionalSimulation':True,'basis':'Simulated eligibility for Composition II after completed EL1000 with C or better; no exam score, Composition II credit, or writing-completion waiver is granted.','qualifyingCourse':'EL1000','qualifyingTerm':first['termCode'],'qualifyingGrade':first['grade']}
        person.setdefault('historyEvidenceReview',{}).setdefault('placement:ECII',{'original':prior})['replacement']=copy.deepcopy(evidence['placement:ECII'])
