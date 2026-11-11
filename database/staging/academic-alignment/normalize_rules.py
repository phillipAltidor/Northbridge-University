#!/usr/bin/env python3
"""Build reviewable rule definitions from retained sources; never connects to DB.
Only explicitly approved conversation decisions override selected catalog text.
"""
import collections
import json
from pathlib import Path
import re
ROOT = Path(__file__).resolve().parent
OUT = ROOT / 'rules'
OUT.mkdir(exist_ok=True)

def read(name):
    return json.loads((ROOT/name).read_text())

def write(name, value):
    (OUT/name).write_text(json.dumps(value, indent=2)+'\n')

def course(code, grade=None):
    return {'type':'course', 'courseCode':code, 'minimumGrade':grade,
            'minimumGradeStatus':'explicit' if grade else 'not-specified-in-course-entry'}

def group(kind, *nodes):
    return {'type':kind, 'rules':list(nodes)}

def choose(count, codes, credits=None):
    return {'type':'choose-distinct-courses','count':count,'eligibleCourses':codes,
            'minimumCredits':credits,'allowDuplicateCourseCredit':False}

programs = {p['program']:p for p in read('program-review-inputs.json')}
sources = read('reviewed-course-extraction.json')
by_code = collections.defaultdict(list)
for source in sources:
    for row in source['records']:
        by_code[row['code']].append(dict(row, sourcePDF=source['sourcePDF'], sourceSHA256=source['sourceSHA256']))

# Credit values are corroborated by the same selected catalog's program PDFs.
credit_evidence = {
 'AS4402':('Media & Communications, B.A.',4),
 'AS5412':('Media & Communications, B.A.',4),
 'BU5171':('Business Administration, B.S.',4)}
credit_resolutions = []
for code,(program,credits) in credit_evidence.items():
    text='\n'.join(programs[program]['pages'])
    assert re.search(re.escape(code[:2])+r'\s*'+code[2:]+r'[^\n]*Credits:\s*'+str(credits)+r'\b',text)
    credit_resolutions.append({'courseCode':code,'credits':credits,'status':'confirmed-from-selected-catalog-program',
                              'sourceProgram':program,'sourcePDF':programs[program]['archivedPDF'],
                              'sourceSHA256':programs[program]['sourceSHA256']})

canonical = []
for code, rows in sorted(by_code.items()):
    row=rows[0]
    issues=[]
    if len(rows)>1:issues.append('conflicting-source-entries-require-title-version-resolution')
    credit=re.search(r'Credits:\s*(\d+(?:\.\d+)?)(?:\s*[-‑–]\s*(\d+(?:\.\d+)?))?',row['sourceText'])
    low=float(credit[1]) if credit else None
    high=float(credit[2] or credit[1]) if credit else None
    credit_source='course-entry' if credit else None
    if code in credit_evidence:
        low=high=credit_evidence[code][1];credit_source='corroborated-selected-program-pdf'
    if low is None:issues.append('credits-not-established')
    assert low is None or 0 <= low <= high
    canonical.append({'courseCode':code,'sourceTitle':row['title'],
                      'creditMinimum':low,'creditMaximum':high,'creditEvidence':credit_source,
                      'metadataStatus':'quarantined' if issues else 'source-metadata-normalized-rule-review-pending',
                      'issues':issues,'sourceCount':len(rows),
                      'sourceEvidence':[{'pdf':r['sourcePDF'],'sha256':r['sourceSHA256'],'page':r.get('sourceStartPage')} for r in rows],
                      'prerequisiteStatus':'not-yet-approved',
                      'displayDescriptionStatus':'requires-editorial-note-and-page-furniture-cleanup',
                      'academicLevelStatus':'verify-from-program-or-explicit-level-source'})
# Approved older Education versions are separate from current catalog course identity.
education_decision=read('rules/education-core-proposal.json')
assert education_decision['status']=='approved-project-decision'
historical_evidence=read('supplemental-sources/education-historical-evidence.json')
historical_source=read('supplemental-sources/undergraduate-catalog-2020-2022-source.json')
versions=[]
for spec,next_code in zip(education_decision['courses'],['ED4082','ED5926']):
    page=next(p['text'] for p in historical_evidence['pages'] if p['pdfPage']==spec['sourcePDFPage'])
    start=page.index(spec['courseCode']+' ')
    end=page.index(next_code+' ',start)
    raw=page[start:end].strip()
    assert '4 cr' in raw
    versions.append({**spec,'versionID':spec['courseCode']+'@2020-2022',
                     'sourceText':raw,'sourceSHA256':historical_source['sha256'],
                     'status':'approved-historical-course-version-prerequisite-review-pending',
                     'automaticEquivalencyToCurrentVersion':False})
canonical.append({'courseCode':'ED4050','sourceTitle':versions[0]['title'],
                  'creditMinimum':4,'creditMaximum':4,'creditEvidence':'user-approved-historical-course-version',
                  'metadataStatus':'approved-historical-version-rule-review-pending','issues':[],
                  'sourceCount':1,'sourceEvidence':[{'pdf':education_decision['source'],
                    'sha256':historical_source['sha256'],'page':200}],
                  'prerequisiteStatus':'not-yet-approved','academicLevelStatus':'undergraduate-education-program-source'})
canonical.sort(key=lambda c:c['courseCode'])
write('approved-historical-course-versions.json',versions)
write('course-metadata.json',{'status':'staging-only-not-import-ready','courses':canonical})
write('credit-resolutions.json',credit_resolutions)

# Preserve only source-explicit former-code claims; never infer aliases by title.
aliases={}
for domain in read('general-education-source-tables.json'):
    for index,row in enumerate(domain['tableRows']):
        current=re.match(r'\s*([A-Z]{2})\s*(\d{4})\b',row[0])
        if not current:continue
        for old_prefix,old_number in re.findall(r'Previously\s+([A-Z]{2})\s*(\d{4})\b',row[0]):
            old=old_prefix+old_number;new=current[1]+current[2]
            item=aliases.setdefault((old,new),{'formerCode':old,'currentCode':new,
                'relationship':'source-explicit-former-code','effectiveTerm':None,
                'historicalCreditTransferApproved':False,'automaticTranscriptRewriteAllowed':False,'sources':[]})
            item['sources'].append({'url':domain['url'],'row':index,'rawCourseCell':row[0]})
write('alias-evidence.json',{'status':'source-evidence-only-effective-dates-and-use-need-review','relationships':list(aliases.values())})

# Pilot rule trees preserve the selected course-entry wording and minimum grades.
predicates={
 'CS2510':course('MA1020'),
 'CS2511':course('CS2510'),
 'CS3611':course('CS2510'),
 'CS3620':group('and',course('CS2511'),course('MA3030')),
 'CS3810':group('and',course('CS2511'),course('MA3030')),
 'CS3911':group('and',course('CS2511'),course('MA3030')),
 'CS4100':group('and',group('or',{'type':'standing-in-program','standing':'Junior','program':'CIS'},
                             {'type':'standing-in-program','standing':'Junior','program':'MIS'}),
                         {'type':'proficiency','key':'ECII'}),
 'CS4501':group('and',course('CS2511','C'),group('or',course('CS3810','C'),course('CS3611','C'))),
 'CS4550':group('and',course('CS2511'),group('or',course('CS3810'),course('CS3611')),{'type':'proficiency','key':'ECI'}),
 'CS4720':group('and',course('CS2511','C'),group('or',course('CS3810','C'),course('CS3611','C'))),
 'CS4400':group('and',course('CS3810'),course('MA3210')),
 'CS4705':course('CS3810'),
 'CS4710':group('and',course('CS2511','C'),course('MA3030','C')),
 'CS5610':group('and',course('CS3620'),course('CS3810')),
 'CS5710':group('and',group('or',course('CS4501'),course('CS4550')),group('or',course('MA3210'),course('MA2000'))),
 'CS5730':group('and',course('CS4710'),course('CS5710')),
 'CS5910':group('and',*[group('or',course(c,'C'),{'type':'approved-transfer-course','courseCode':c,'minimumBaseGrade':'C','sourceGradeToken':'TC'}) for c in ['CS4501','CS4720','CS4550']],
                         {'type':'completed-writing-proficiency','components':['CompositionI','CompositionII'],'minimumCourseGrade':'C','allowApprovedEquivalentOrPlacementEvidence':True,'partialPlacementSatisfiesBoth':False}),
 'MA1020':group('or',course('MA1010','C'),course('MA2000','C')),
 'MA2000':{'type':'no-course-prerequisites','sourceExplicitlyStatesNone':True},
 'MA2080':course('MA1020','C'),
 'MA2090':course('MA1020','C'),
 'MA2310':course('MA2090','C'),
 'MA3030':group('or',course('MA2090','C'),course('MA2080','C')),
 'MA3160':group('or',course('MA2310','C'),course('MA2300','C')),
 'MA3210':group('or',course('MA2310'),course('MA2300')),
 'MA4100':course('MA3030','C')}
prerequisites=[]
for code, rule in predicates.items():
    record=by_code[code][0]
    start=record['sourceText'].find('Prerequisites')
    assert start>=0
    source_text=record['sourceText'][start:]
    item={'courseCode':code,'status':'source-rule-transcribed-not-active',
          'sourcePrerequisiteText':source_text,'rule':rule,
          'sourceSHA256':record['sourceSHA256'],
          'sourcePDF':record['sourcePDF']}
    if code=='CS5910':
        item.update({'status':'project-rule-approved-not-active','projectDecision':'CS-WRITING-001',
                     'replacedSourceCondition':'( WRITING O.W. 25) and'})
    if code=='CS4100':item['standingInterpretationStatus']='Junior threshold / senior eligibility must be explicit in eligibility policy before activation.'
    if code=='MA3210':item['recommendations']=['MA2320 recommended; not a required prerequisite in this entry.']
    prerequisites.append(item)
# The user explicitly adopted the advising guide as a project exception for CS5810.
cs5810_rule=group('and',course('CS4550'),group('or',course('MA3210'),course('MA2000')),
                  {'type':'proficiency','key':'ECII'})
prerequisites.append({'courseCode':'CS5810','status':'project-rule-approved-not-active',
                      'rule':cs5810_rule,'projectDecision':'CS5810-PREREQ-001',
                      'selectedCatalogPrerequisiteText':None,
                      'advisingSourceText':'CS4550, MA3210 or MA2000, ECII',
                      'source':'supplemental-sources/math-cis-advising.json',
                      'note':'Explicit user-approved source exception. No unstated prerequisite grade threshold is added; program graduation minimum grades remain separate.'})
write('pilot-prerequisites.json',{'status':'staging-only-not-registration-code','rules':prerequisites,
      'interpretationNotes':['Minimum grade null means not stated; never copy major minimum grades into prerequisite rules automatically.',
                            'Course entry conditions and institution/program restrictions are separate layers.',
                            'Recommendations are not prerequisites.']})

cs_core=['CS2510','CS2511','CS3620','CS3810','CS3911','CS4100','CS4501','CS4550','CS4720','CS5910']
cs={'program':'Computer and Information Science, B.S.','catalogEdition':'2026-2027','status':'groups-finalized-prerequisite-and-policy-issues-still-pending',
 'core':{'type':'all-courses','courses':cs_core,'credits':40},
 'electives':choose(3,['CS4400','CS4705','CS4710','CS5610','CS5710','CS5730','CS5810'],12),
 'math':{'all':['MA2310','MA3030','MA3210'],'chooseOne':['MA3160','MA4100'],'credits':16},
 'majorCredits':68,'degreeCredits':120,'minimumGradeForRequiredCSAndMath':'C',
 'upperDivisionCredits':45,'liberalArtsCredits':60,'residencyCredits':40,'maximumTransferCredits':80,
 'transferMajorResidency':{'credits':28,'minimumCourses':7,'minimumLevel':3000},
 'projectDecisions':['CS-CORE-001','CS-WRITING-001'],'ruleDependencies':['GE-approval-and-credit-counting','institutional-grade-repeat-transfer-rules','pilot-prerequisites']}
mis={'program':'Management Information Systems, B.S.','catalogEdition':'2026-2027','status':'groups-finalized-prerequisite-and-policy-issues-still-pending',
 'computerScience':{'courses':['CS2510','CS2511','CS3611','CS4100','CS4501','CS4550','CS4720','CS5710','CS5910'],'credits':36},
 'business':{'courses':['BU3502','BU3511','BU3800','BU4110'],'credits':14},
 'economicsAnalytics':choose(2,['MA2701','PE2420','PE2430'],8),
 'math':{'groups':[choose(1,['MA2300','MA2310','MA3030'],4),choose(1,['MA2000','MA3210'],4)],'credits':8},
 'majorCredits':66,'degreeCredits':120,'minimumGradeForRequiredCSBusinessEconomicsMath':'C',
 'projectDecisions':['MIS-ECON-001','CS-WRITING-001']}
marketing={'program':'Marketing, Minor','catalogEdition':'2026-2027','status':'groups-finalized-other-policy-review-pending',
 'core':['BU3800','BU4851','BU4820','BU5880'],'elective':choose(1,['BU5841','BU5891'],4),
 'credits':20,'courseCount':5,'projectDecisions':['MKT-MINOR-001'],
 'note':'No grade, residency or overlap policy is inferred from the four-plus-one group correction.'}
cs_minor={'program':'Computer Information Science, Minor','catalogEdition':'2026-2027','status':'groups-finalized-prerequisite-and-policy-review-pending',
 'core':['CS2510','CS2511','CS3810','CS4501'],'coreCredits':16,
 'elective':{'type':'choose-distinct-courses-by-selector','count':1,'credits':4,'subject':'CS','levels':[4000,5000],'exclude':['CS4100','CS5620']},
 'credits':20,'minimumGrade':'C','minimumCreditsOutsideMajor':16,'maximumTransferCourses':2,
 'prerequisiteSubstitutions':[{'required':'MA2090','allowed':'MA2080'},{'required':'MA2310','allowed':'MA2300'}]}
education={'program':'Childhood Education 1-6, B.S.','catalogEdition':'2026-2027-with-explicit-historical-core-exception',
 'status':'core-group-finalized-concentration-and-policy-review-pending',
 'core':{'courses':[{'courseCode':c,'catalogVersion':'2026-2027'} for c in
   ['ED3700','ED3820','ED3900','ED4200','ED4121','ED4122','ED4220']]
   +[{'courseCode':c['courseCode'],'catalogVersion':'2020-2022'} for c in versions],
   'credits':36,'distinctCourseCount':9},
 'fieldExperience':{'course':'ED5940','credits':12,'minimumGrade':'B'},
 'concentration':{'required':True,'statedCredits':32,'status':'official-options-collected-reconciliation-pending'},
 'projectDecisions':['ED-CORE-001']}
psychology=read('rules/psychology-major-review.json')
write('pilot-program-groups.json',{'status':'staging-only-not-degree-audit-code','programs':[cs,mis,cs_minor,marketing,education,psychology]})

# Only exceptions explicitly approved in the conversation are recorded here.
decisions=[
 {'id':'CS-CORE-001','status':'approved-project-decision','approvedBy':'user','approvedDate':'2026-11-07',
  'rule':'Require CS3911 (4 credits) as the tenth CIS core course. No CS3910 alternative.',
  'catalogDiscrepancyRetained':True,'scope':'Northbridge undergraduate CIS core only'},
 {'id':'MIS-ECON-001','status':'approved-project-decision','approvedBy':'user','approvedDate':'2026-11-07',
  'rule':choose(2,['MA2701','PE2420','PE2430'],8),
  'evidence':'Selected catalog has three courses under an 8-credit/two-course heading. Official advising worksheet explicitly says take two but uses MA3250.',
  'evidenceURL':'https://www.oldwestbury.edu/school-arts-and-sciences/mathematics-computer-information-science/advising-resources-current-0',
  'scope':'Northbridge MIS economics/analytics group; does not establish MA3250-to-MA2701 equivalency'},
 {'id':'MKT-MINOR-001','status':'approved-project-decision','approvedBy':'user','approvedDate':'2026-11-07',
  'rule':{'core':marketing['core'],'elective':marketing['elective'],'credits':20,'courses':5},
  'evidenceURL':'https://www.oldwestbury.edu/programs-courses/marketing-minor',
  'scope':'Northbridge Marketing minor course groups; remaining applicable academic policies still apply'}]
decisions.append(education_decision)
decisions.append({'id':'CS-WRITING-001','status':'approved-project-decision','approvedBy':'user',
 'approvedDate':'2026-11-07','course':'CS5910','scope':'Northbridge CS5910 writing prerequisite only',
 'rule':predicates['CS5910']['rules'][-1],
 'retainedCoursePrerequisites':['CS4501','CS4550','CS4720'],
 'retainedGradeRule':'Each listed CS prerequisite needs C or qualifying approved transferred C (TC) or better.',
 'evidence':'User explicitly approved completed English Composition I and II with C or better, or approved equivalents/placement, to replace truncated catalog writing condition.',
 'originalCondition':'( WRITING O.W. 25) and',
 'sourceURL':'https://www.oldwestbury.edu/school-arts-and-sciences/mathematics-computer-information-science/advising-resources-current-0'})
decisions.append({'id':'CS5810-PREREQ-001','status':'approved-project-decision','approvedBy':'user',
 'approvedDate':'2026-11-07','course':'CS5810','rule':cs5810_rule,
 'scope':'Northbridge CS5810 prerequisites; no inferred prerequisite grade thresholds',
 'evidence':'User approved the official advising-guide prerequisites because the selected catalog course entry omits them.',
 'sourceURL':'https://www.oldwestbury.edu/school-arts-and-sciences/mathematics-computer-information-science/advising-resources-current-0'})
decisions.append({'id':'PSYCH-BS-LA-001','status':'approved-project-decision','approvedBy':'user',
 'approvedDate':'2026-11-07','program':'Psychology, B.S.',
 'rule':{'minimumLiberalArtsCredits':60},
 'scope':'Northbridge Psychology BS liberal-arts minimum only; BA major groups and BS math/science additions still apply.',
 'evidence':'User chose the standard BS 60-credit minimum to resolve ambiguous inheritance of the BA 90-credit requirement.',
 'baseSource':'source-pdfs/supporting-psychology-b-a.pdf'})
decisions.append({'id':'PSYCH-PROGRESSION-001','status':'approved-project-source-interpretation',
 'approvedBy':'user','approvedDate':'2026-11-07','program':'Psychology, B.S.',
 'rule':'Treat Progressing Through the Major as the supplied course prerequisites; use the documents already collected.',
 'additionalDepartmentPolicyDocumentRequired':False,
 'scope':'Psychology progression reference only; retain all documented major, degree and individual-course requirements. Do not import unprovided historical department policies.'})
write('project-decisions.json',decisions)

clarifications=[
 {'id':'CREDIT-001','status':'confirmed-from-selected-catalog','rule':credit_resolutions},
 {'id':'GENBUS-001','status':'clarified-without-changing-source-rule',
  'rule':'Minimum 18 credits is a floor. Four listed core courses total 15; a listed 4-credit elective produces 19. Keep five-course requirement and actual credits; do not force total to 18 or invent fourth elective.',
  'sourceProgram':'General Business, Minor'},
 {'id':'MATH-ELECTIVE-001','status':'corroborated-interpretation-of-catalog-continuation',
  'rule':'Math major elective pool includes eligible 4000/5000 mathematics courses OR CS3810; three distinct additional elective courses totaling 12 credits.',
  'evidenceURL':'https://www.oldwestbury.edu/school-arts-and-sciences/mathematics-computer-information-science/advising-resources-current-0',
  'note':'Worksheet has unrelated arithmetic errors; only explicit elective-pool wording corroborates this catalog interpretation.'},
 {'id':'GRADE-TC-001','status':'meaning-confirmed-from-official-guide',
  'rule':'T prefixes transfer grade tokens; TC denotes a transferred C-grade result. Accept only approved course-equivalent transfer evidence; do not count transferred grades in institutional GPA or treat TCR as numeric C.',
  'source':'supplemental-sources/degree-audit-user-guide.pdf','page':6,
  'relatedProjectDecision':'CS-WRITING-001; user approved completed writing proficiency to replace the incomplete source condition.'},
 {'id':'EMS-001','status':'meaning-corroborated',
  'rule':'EMS means English Mastery Satisfied. Resolve through reviewed writing-proficiency evidence; completion normally requires qualifying Composition I and II with C or better, with approved exemptions/placement handled separately.',
  'sources':['supplemental-sources/math-cis-advising.json','supplemental-sources/lec-overview.json'],
  'note':'ECI and ECII are proficiency predicates, not literal course IDs. Workshop completion alone does not satisfy them.'},
 {'id':'RECOMMENDED-001','status':'confirmed-from-selected-course-entry',
  'rule':'MA2320 is recommended for MA3210, not required by that course entry.'}]
write('source-clarifications.json',clarifications)
remaining_credits=[c['courseCode'] for c in canonical if 'credits-not-established' in c['issues']]
summary={'phase':'1-clean-and-finalize-academic-rules','status':'in-progress','workingDatabaseChanged':False,
 'approvedProjectDecisions':[d['id'] for d in decisions],
 'creditValuesResolved':len(credit_resolutions),'missingCreditValuesRemaining':remaining_credits,
 'formerCodeEvidenceRelationships':len(aliases),'pilotPrerequisiteEntries':len(prerequisites),
 'finalizedProgramGroupDefinitions':6,
 'acceptedSourceInterpretations':[{'id':'PSYCH-PROGRESSION-001','scope':'Psychology progression reference','status':'use-supplied-prerequisites-no-additional-document-required'}],
 'blockingIssues':[
  {'id':'ED5925-PREREQ-001','scope':'approved historical ED5925 registration','issue':'Historical prerequisite text contains ED400 and ambiguous separators; preserve source and resolve before activating eligibility.','status':'needs-prerequisite-reconciliation'},
  {'id':'SY2222-TITLE-001','scope':'canonical SY2222 record','issue':'Two source entries with different titles; keep both as evidence until canonical title/version is settled.','status':'quarantined'},
  {'id':'PDF-ORIGINALS-001','scope':'CS/MA source verification','issue':'Original matching PDF filenames were overwritten; extracted text survives.','status':'requires-matching-source-archive'},
  {'id':'PREREQ-CLOSURE-001','scope':'all program courses','issue':'Unresolved old/missing codes and ambiguous AND/OR expressions require per-course review. Former-code evidence does not authorize transcript rewrites.','status':'in-progress'},
  {'id':'ED-CONCENTRATION-001','scope':'Education concentration','issue':'Official concentration list and admission policy collected. Their course-version and credit differences still need reconciliation with selected catalog.','status':'supplemental-source-collected-needs-reconciliation'}]}
write('phase-one-status.json',summary)
assert len(cs_core)==10 and cs['core']['credits']+cs['electives']['minimumCredits']+cs['math']['credits']==68
assert mis['computerScience']['credits']+mis['business']['credits']+mis['economicsAnalytics']['minimumCredits']+mis['math']['credits']==66
assert len(marketing['core'])+marketing['elective']['count']==5
assert 'BU4762' not in remaining_credits
assert len({c['courseCode'] for c in canonical})==len(canonical)
print(f"Normalized {len(canonical)} candidate course records; {len(aliases)} explicit former-code relationships.")
print(f"Recorded {len(decisions)} approved project decisions, 6 program-group definitions and {len(prerequisites)} pilot prerequisite records.")
print(f"Resolved 3 credit values; {len(remaining_credits)} remain unknown. Phase 1 remains in progress. No database access.")
