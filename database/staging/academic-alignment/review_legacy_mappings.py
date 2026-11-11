#!/usr/bin/env python3
"""Comprehensive candidate review; lexical similarity never approves an equivalence."""
import json,re,collections,hashlib
from pathlib import Path
R=Path(__file__).resolve().parent
legacy=json.loads((R/'legacy-academic-review-inputs.json').read_text())
metadata={c['courseCode']:c for c in json.loads((R/'rules/course-metadata.json').read_text())['courses']}
prereqs={c['courseVersionOrCode']:c for c in json.loads((R/'rules/all-course-prerequisites.json').read_text())['courses']}
# Explicit topic candidates, NOT approved mappings. Two plausible alternatives stay ambiguous.
anchors={
'CS1101':['CS2510'],'CS1102':['CS2511','CS3911'],'CS1103':['CS3810'],'CS1104':['CS4550'],'CS1105':['CS5710'],'CS1106':['CS4501'],'CS1108':['CS4720'],'CS1109':['CS3620'],'CS1110':['CS5610'],'CS1111':['CS4310'],'CS1112':['CS2700','CS4705'],'CS1114':['CS4200'],'CS1118':['CS5910'],
'BUS2102':['BU3502'],'BUS2103':['BU3511'],'BUS2104':['BU3800'],'BUS2106':['BU4110'],'BUS2107':['BU3905'],'BUS2108':['BU3600'],'BUS2109':['PE2420'],'BUS2110':['PE2430'],'BUS2111':['MA2000'],
'MATH3101':['MA2310'],'MATH3102':['MA2320'],'MATH3103':['MA3160'],'MATH3104':['MA3030'],'MATH3105':['MA3210'],'MATH3106':['MA3330'],'MATH3107':['MA1020'],'MATH3108':['MA2090'],
'SCI4101':['BS2400'],'SCI4102':['CP2120'],'SCI4103':['PX2000','PX2200'],'SCI4111':['CP3300'],'SCI4112':['CP3310'],
'ENGL6101':['EL1000'],'ENGL6105':['EL3901'],'ENGL6106':['EL3010'],
'SOC7101':['PY2010'],'SOC7112':['PY3510','PY3570'],'HSC9105':['PH2000']}
anchors={code:[c for c in codes if c in metadata] for code,codes in anchors.items()}
bundles={'SCI4101':[['BS2400','BS2401']],'SCI4102':[['CP2120','CP2121']],'SCI4103':[['PX2000','PX2001'],['PX2200','PX2201']],'SCI4111':[['CP3300','CP3302']],'SCI4112':[['CP3310','CP3312']]}
subjects={1:['CS'],2:['BU','PE','MA'],3:['MA'],4:['BS','CP','PX'],5:['PX','CS','MA'],6:['EL','ML'],7:['PY','SY','PE','HI','AS','CR'],8:['ED','PY'],9:['PH','BS','PY','CP'],10:['MC','VA','AS','MD']}
stop={'and','the','of','in','to','for','an','a','with','fundamentals','principles','introduction'}
def words(s):
 s=s.lower().replace('&',' and ').replace('object-oriented','object oriented').replace('communications','communication').replace('programming','program').replace('computing','computer').replace('organisation','organization')
 return set(re.findall(r'[a-z]+|\b[123]\b',s))-stop
def score(a,b):
 x,y=words(a),words(b)
 return len(x&y)/max(1,len(x|y))
legacy_pre=collections.defaultdict(list)
for p in legacy['prerequisites']:legacy_pre[p['course_ID']].append(p)
review=[]
for course in legacy['courses']:
 code=course['course_ID'];graduate=course['course_Type']=='Graduate';generic=course['course_DSC'].startswith('Study of ')
 candidates=[]
 if not graduate:
  ranks=[]
  for target,m in metadata.items():
   if re.match(r'[A-Z]+',target)[0] not in subjects[course['dept_ID']]:continue
   title=score(course['course_Name'],m['sourceTitle']);desc=score(course['course_DSC'],m.get('displayDescription',''))
   rank=.85*title+.15*desc
   if target in anchors.get(code,[]):rank=max(rank,1.1-anchors[code].index(target)*.02)
   if rank>=.28:ranks.append((rank,target,title,desc))
  for rank,target,title,desc in sorted(ranks,reverse=True)[:4]:
   m=metadata[target];creditsOK=m['creditMinimum'] is not None and m['creditMinimum']<=course['course_Credits']<=m['creditMaximum']
   candidates.append({'courseVersionOrCode':target,'title':m['sourceTitle'],'catalogCreditMinimum':m['creditMinimum'],'catalogCreditMaximum':m['creditMaximum'],'creditSnapshotFits':creditsOK,'rankingScore':round(rank,4),'titleSimilarity':round(title,4),'method':'explicit-topic-candidate' if target in anchors.get(code,[]) else 'lexical-topic-candidate','catalogPrerequisiteRecord':prereqs.get(target),'sourceEvidence':m['sourceEvidence'],'approved':False})
 findings=[]
 if graduate:findings.append('Legacy Graduate classification is explicit; exclude from undergraduate equivalency conversion even if an undergraduate catalog has a similar title.')
 else:
  if generic:findings.append('Legacy description is a generic title-based placeholder; learning outcomes, depth and assessed coverage cannot be verified from it.')
  else:findings.append('Legacy description contains substantive topics, but full assessed coverage and prerequisite alignment still need explicit review.')
  if not candidates:findings.append('No defensible topic candidate identified in supplied catalog scope; retain the original course meaning.')
  if len(anchors.get(code,[]))>1:findings.append('More than one distinct catalog course is plausible; do not choose automatically or award both.')
  if candidates and not candidates[0]['creditSnapshotFits']:findings.append('Leading candidate has different credit bounds; never change historical four-credit attempts to force a match.')
  if code in bundles:findings.append('Lecture/lab bundle review required; the legacy description does not establish separate lab completion.')
  if 'Capstone' in course['course_Name']:findings.append('A generic capstone label does not establish the target project, prerequisites or experience requirements.')
  findings.append('Compare legacy prerequisite snapshots and the preserved full target rule, including all grade/AND/OR/proficiency conditions; neither overrides the other.')
 # Narrow assumptions for diagnostics only. Nothing becomes an approved transcript equivalence.
 preferred=anchors.get(code,[])
 planning=preferred[0] if len(preferred)==1 and preferred[0] in metadata and metadata[preferred[0]]['creditMinimum']==metadata[preferred[0]]['creditMaximum']==course['course_Credits'] and code not in bundles else None
 review.append({'legacyCourseCode':code,'legacyTitle':course['course_Name'],'legacyCredits':course['course_Credits'],'legacyAcademicLevel':course['course_Type'],'legacyDescription':course['course_DSC'],'legacyDescriptionSHA256':hashlib.sha256(course['course_DSC'].encode()).hexdigest(),'legacyPrerequisites':legacy_pre[code],'descriptionIsBoilerplate':generic,'status':'excluded-graduate-preserve' if graduate else ('candidate-review-required' if candidates else 'no-supported-candidate-retain-legacy'),'candidates':candidates,'lectureLabBundleCandidates':bundles.get(code,[]),'planningScenarioTarget':planning,'planningAssumptionOnly':bool(planning),'equivalenceApproved':False,'findings':findings})
majorTargets={1:['Computer and Information Science, B.S.'],2:['Computer and Information Science, B.S.','Management Information Systems, B.S.'],3:['Business Administration, B.S.'],4:['Accounting, B.S.'],5:['Mathematics, B.S.'],6:['Biological Sciences, B.S.'],7:['Physics, B.S.'],8:['English, B.A.'],9:['Psychology, B.S.'],10:['Childhood Education 1-6, B.S.'],11:['Public Health, B.S.'],12:['Media & Communications, B.A.']}
minorTargets={1:['Computer Information Science, Minor'],2:[],3:['General Business, Minor'],4:['Marketing, Minor'],5:['Mathematics, Minor'],6:['Environmental Studies, Minor'],7:[],8:[],9:[],10:[],11:['Public Health, Minor'],12:['Media and Communications, Minor']}
programReview=[]
for kind,table,targets in [('major',legacy['majors'],majorTargets),('minor',legacy['minors'],minorTargets)]:
 for p in table:
  id=p[kind+'_ID'];name=p[kind+'_Name'];candidates=targets[id]
  notes=['Review every undergraduate declaration separately; a target suggestion is not an approved program conversion.','Shared IDs never authorize changing graduate program labels.']
  if not candidates:notes.append('No same-program counterpart in the selected catalog set; retain the legacy declaration pending a deliberate program choice. Do not match by numeric ID.')
  if name in ['Cybersecurity','Engineering','Health Sciences','Education']:notes.append('Proposed target(s) have materially different scope; not a title rename or academic equivalence.')
  programReview.append({'kind':kind,'legacyId':id,'legacyName':name,'candidatePrograms':candidates,'status':'individual-choice-required' if candidates else 'no-like-for-like-target-retain-legacy','assignmentApproved':False,'notes':notes})
result={'status':'comprehensive-candidate-review-not-approved-equivalencies','asOf':'2026-11-07','legacyCoursesReviewed':len(review),'undergraduateCourses':sum(c['legacyAcademicLevel']=='Undergraduate' for c in review),'graduateCoursesExcluded':sum(c['legacyAcademicLevel']=='Graduate' for c in review),'boilerplateDescriptions':sum(c['descriptionIsBoilerplate'] for c in review),'undergraduateNoCandidate':sum(c['status']=='no-supported-candidate-retain-legacy' for c in review),'planningOnlyTargets':sum(c['planningScenarioTarget'] is not None for c in review),'approvedEquivalencies':0,'courses':review,'programs':programReview,'method':'Complete comparison of supplied legacy descriptions/credits/prerequisites against retained catalog candidate definitions; similarity ranks retrieval candidates only. No title, score or source alias approves an equivalence.'}
(R/'legacy-mapping-review.json').write_text(json.dumps(result,indent=2)+'\n')
print(json.dumps({k:v for k,v in result.items() if k not in ['courses','programs','method']}))
