#!/usr/bin/env python3
"""Package institutional/GE rules and report readiness plus remaining gates."""
import json,re
from pathlib import Path
R=Path(__file__).resolve().parent;O=R/'rules'
def load(p):return json.loads((R/p).read_text())
def save(p,v):(O/p).write_text(json.dumps(v,indent=2)+'\n')
institution={
 'status':'northbridge-project-rules-for-isolated-test','catalogEdition':'2026-2027',
 'degreeMinimums':{'BS':{'credits':120,'liberalArtsCredits':60},'BA':{'credits':120,'liberalArtsCredits':90},'AccountingBSOverride':{'credits':126,'institutionalResidencyCredits':46}},
 'institutionalGPA':{'minimum':2.0,'repeatAttempt':'latest','exclude':['CR','NC','I','W','NR'],'transferGradesExcluded':True},
 'gradePoints':{'A':4.0,'A-':3.7,'B+':3.3,'B':3.0,'B-':2.7,'C+':2.3,'C':2.0,'C-':1.7,'D+':1.3,'D':1.0,'D-':0.7,'F':0.0},
 'earnedCreditPolicy':{'passingLetterGrades':['A','A-','B+','B','B-','C+','C','C-','D+','D','D-'],'creditGrade':'CR','zeroEarnedCredits':['F','NC','I','W','NR'],'countRepeatedCourseCreditOnceUnlessRepeatable':True,'stricterProgramAndProficiencyGradesOverride':True,'passingFloorAuthority':'Northbridge project interpretation consistent with supplied grade system and GE minimum; no unstated course-specific C threshold.'},
 'residencyCredits':40,'minimumUpperDivisionCredits':45,'minimumUpperDivisionMajorCredits':24,'maximumTransferCredits':80,
 'repeatPolicies':{'institutionalGPA':'latest','PsychologyMajorGPA':'highest-grade-in-each-counted-course','PsychologyBSMathScienceGPA':'highest-grade-in-each-required-course','historicalAttemptsPreserved':True},
 'standingCompletedCredits':{'Freshman':[0,31],'Sophomore':[32,56],'Junior':[57,87],'Senior':[88,None]},
 'proficiencies':{'writing':{'compositionI':'EL1000 or approved alternative','compositionII':'approved Composition II course','minimumGrade':'C','approvedPlacementOrEquivalencyEvidenceAllowed':True,'partialPlacementDoesNotSatisfyBoth':True},
                  'math':{'courses':['MA1020','MA2000','MA2050','MA2080','MA2090','MA2300','MA2310','MA2320','MA3030'],'minimumGrade':'C','approvedPlacementOrEquivalencyEvidenceAllowed':True}},
 'generalEducation':{'minimumDistinctCredits':30,'requireEachCurrentDomain':True,'minimumLetterGrade':'D-','creditGradeAccepted':'CR','courseCreditsCountOnceAcrossDomains':True,'effectiveDateAndCohortConditionsRequired':True,'approvedSUNYOrCUNYAAASWaiver':{'requiresAwardEvidence':True,'waivesLocalProficiencies':False}},
 'advanceRegistration':{'inProgressPrerequisiteMayYieldConditionalEligibility':True,'mustRecheckBeforeTargetTerm':True,'inProgressNotEarnedCredit':True},
 'overrides':{'mustBeLogged':True,'mustIdentifyApproverAndScope':True,'unknownRuleNeverAutomaticallySatisfied':True},
 'preservedProjectRules':{'maximumSectionSeats':10,'fullTimeCreditLimit':16,'partTimeCreditLimit':8,'facultyWorkloadAndAdvisingLimits':'retain existing project constraints'},
 'sources':['../old-westbury-cs/academic-policy-review.json','supplemental-sources/lec-overview.json','rules/project-decisions.json']}
save('institutional-rules.json',institution)
rows=[]
for source in load('general-education-source-tables.json'):
 for i,row in enumerate(source['tableRows']):
  m=re.match(r'\s*([A-Z]{2})\s*(\d{4}[A-Z]?)',row[0])
  if not m or len(row)<3:continue
  course=m[1]+m[2]
  bundle=re.match(r'\s*([A-Z]{2})\s*(\d{4})\s*/\s*(\d{4})',row[0])
  rows.append({'domain':source['domain'],'sourceCourseCode':course,'rawCourseExpression':row[0],
   'lectureLabBundleCandidate':[bundle[1]+bundle[2],bundle[1]+bundle[3]] if bundle else None,
   'legacyApprovalRaw':row[1],'currentApprovalRaw':row[2],'sourceURL':source['url'],'sourceRow':i,'sourceSHA256':source['sha256'],
   'currentCohortEntryFrom':'Fall 2023','status':'source-mapping-requires-effective-date-and-bundle-check'})
save('general-education-mappings.json',{'status':'source-backed-mappings-with-conditions','rows':rows,'policy':'Only approved domain assignments activate; cross-list, lab bundles and date qualifiers are retained. No copied narrative heading overrides the course table.'})
programs=load('rules/curricula.json')['programs'];meta=load('rules/course-metadata.json')['courses'];known={m['courseCode'] for m in meta}
# Add source-listed missing options as unresolved metadata, never fictitious 4-credit courses.
missing={}
for p in programs:
 def walk(v):
  if isinstance(v,str) and re.fullmatch(r'[A-Z]{2}\d{4}[A-Z]?',v) and v not in known:missing.setdefault(v,set()).add(p['program'])
  elif isinstance(v,list):
   for x in v:walk(x)
  elif isinstance(v,dict):
   for k,x in v.items():
    if k not in ['source','notes','sourcePlaceholder']:walk(x)
 walk(p['groups']);walk(p.get('concentrations',{}))
save('unresolved-course-options.json',{'status':'manual-metadata-resolution-required','options':[{'courseCode':c,'programs':sorted(ps),'mayActivateWithoutCreditAndSourceEvidence':False} for c,ps in sorted(missing.items())]})
manual=load('rules/manual-review-register.json');prior=load('rules/phase-one-status.json')
recommendations=load('rules/recommended-interpretations.json');recommendations += [
 {'id':'REC-SY2222-001','status':'adopted-display-choice','decision':'Use Care & Care Work as the display title for SY2222, preserving Sociology of Caring and The Work of Caring as title variants and all source entries.','authority':'Delegated recommendation; source entry explicitly says the title was updated.','changesCourseCreditOrHistory':False},
 {'id':'REC-SOURCE-GAPS-001','status':'accepted-staging-provenance-limitation','decision':'Retain original CS/MA extracted text and hashes as evidence; do not ask for repeated uploads or label them as reverified PDFs. Matching original PDFs are unavailable.','scope':'isolated test/source staging; provenance warning remains in final checklist'},
 {'id':'REC-ED5925-GATE-001','status':'manual-prerequisite-review-required','decision':'Keep the historical ED5925 course version, but require logged academic review for its malformed prerequisite expression instead of inventing ED400 or dropping the condition.'}]
recommendations=list({d['id']:d for d in recommendations}.values())
save('recommended-interpretations.json',recommendations)
summary={'phase':'1-academic-rule-package','status':'ready-for-isolated-test-with-explicit-review-gates','allOfficialSourceQuestionsResolved':False,
 'workingDatabaseChanged':False,'selectedMajorDefinitions':12,'selectedMinorDefinitions':12,'courseMetadataRecords':len(meta),
 'prerequisiteRecords':len(load('rules/all-course-prerequisites.json')['courses']),
 'fullyTranscribedPrerequisiteRecords':len(load('rules/all-course-prerequisites.json')['courses'])-len(manual['prerequisites']),
 'reviewGatedPrerequisiteRecords':len(manual['prerequisites']),'missingCreditValues':manual['missingCreditValues'],
 'unresolvedSourceListedOptions':sorted(missing),'GEApprovalRowsRetained':len(rows),
 'userApprovedDecisions':prior.get('approvedProjectDecisions',prior.get('userApprovedDecisions',[])),
 'recommendationAuthority':'User requested completion using the supplied files and trusted recommendations on November 7, 2026.',
 'sourceLimitations':['Original CS/MA PDF pathnames were reused; retained earlier text/hash evidence has not been reverified against matching PDFs.','All source conflicts are represented in project decisions, source variants, explicit approval checks or manual review gates; none of these makes an unsupported condition automatically true.'],
 'nextStep':'Use the separate test database for versioned curriculum and pilot evaluation; consult test-implementation-receipt.json for installed state.',
 'pendingWorkBeforeWorkingMigration':['Implement and test rule evaluation including manual/conditional outcomes.','Resolve affected review gates before allowing unattended registration/completion decisions.','Import official term-specific schedules.','Review individual student mappings and legacy/graduate protections.','Rehearse backup restore and synchronize install seeds.']}
save('phase-one-status.json',summary)
print('Packaged 24 curricula, institutional policies and',len(rows),'GE source mappings.')
print('Unresolved source-listed options:',sorted(missing));print('Manual prerequisites:',len(manual['prerequisites']),'-- explicit gates, not omitted conditions.')
