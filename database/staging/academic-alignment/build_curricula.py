#!/usr/bin/env python3
"""Build all 24 Northbridge curriculum definitions from retained academic sources.
No database/network access. Source uncertainty stays explicit in the rule model.
"""
import json
import re
from pathlib import Path
R=Path(__file__).resolve().parent
OUT=R/'rules'
def load(p):return json.loads((R/p).read_text())
def save(p,d):(OUT/p).write_text(json.dumps(d,indent=2)+'\n')
P={p['program']:p for p in load('program-review-inputs.json')}
M={c['courseCode']:c for c in load('rules/course-metadata.json')['courses']}
existing={p['program']:p for p in load('rules/pilot-program-groups.json')['programs']}

def codes(name,start,end=None):
 s='\n'.join(P[name]['pages']);a=s.index(start)+len(start);b=s.index(end,a) if end else len(s)
 return list(dict.fromkeys(a+b for a,b in re.findall(r'^([A-Z]{2})\s+(\d{4}[A-Z]?)\s*-',s[a:b],re.M)))
def allof(items,credits=None):return {'type':'all-courses','courses':items,**({'minimumCredits':credits} if credits is not None else {})}
def choose(n,items,credits=None):return {'type':'choose-distinct-courses','minimumCount':n,'eligibleCourses':items,**({'minimumCredits':credits} if credits is not None else {})}
def select(n,subject,levels,credits=None,exclude=None):return {'type':'choose-by-selector','minimumCount':n,'subject':subject,'levels':levels,'excludeCourses':exclude or [],**({'minimumCredits':credits} if credits is not None else {})}
def creditpool(n,items):return {'type':'choose-credits','minimumCredits':n,'eligibleCourses':items}
def pathset(paths):return {'type':'choose-one-path','paths':paths}
def bundle(items):return {'type':'course-bundle','courses':items}
def choose_units(n,options,credits=None):return {'type':'choose-distinct-units','minimumCount':n,'options':options,**({'minimumCredits':credits} if credits is not None else {})}
def reviewed(key,text):return {'type':'reviewed-evidence','key':key,'description':text,'mustBeSatisfied':True}
def degree(bs=True,total=120,residency=40):return {'minimumTotalCredits':total,'minimumInstitutionalGPA':2.0,'minimumUpperDivisionCredits':45,'minimumLiberalArtsCredits':60 if bs else 90,'minimumInstitutionalResidencyCredits':residency,'maximumTransferCredits':80}

def add(name,groups,policies=None,concentrations=None,notes=None):
 minor=name.endswith('Minor');bachelor='B.A.' not in name
 entry={'program':name,'kind':'minor' if minor else 'major','catalogEdition':'2026-2027','status':'defined-for-isolated-test','groups':groups,
        'countingPolicy':{'distinctCoursesWithinProgram':True,'degreeCreditsCountedOnce':True,'prerequisitesAreAdditionalUnlessExplicitlyCounted':True},
        'policies':policies or {},'source':{'pdf':P[name]['archivedPDF'],'sha256':P[name]['sourceSHA256']},'notes':notes or []}
 if not minor:entry['degreeRequirements']=degree(bachelor)
 if concentrations:entry['concentrations']=concentrations
 curricula[name]=entry;return entry
curricula={}
# Existing, explicitly reviewed and approved pilot definitions.
for name in ['Computer and Information Science, B.S.','Management Information Systems, B.S.','Computer Information Science, Minor','Marketing, Minor']:
 x=existing[name]
 if name=='Computer and Information Science, B.S.':
  groups=[allof(x['core']['courses'],40),choose(3,x['electives']['eligibleCourses'],12),allof(x['math']['all'],12),choose(1,x['math']['chooseOne'],4)]
  p=add(name,groups,{'minimumGrade':'C','majorCredits':68,'transferMajorResidency':x['transferMajorResidency']})
 elif name=='Management Information Systems, B.S.':
  groups=[allof(x['computerScience']['courses'],36),allof(x['business']['courses'],14),choose(2,x['economicsAnalytics']['eligibleCourses'],8),choose(1,['MA2300','MA2310','MA3030'],4),choose(1,['MA2000','MA3210'],4)]
  p=add(name,groups,{'minimumGrade':'C','majorCredits':66,'transferMajorResidency':{'credits':28,'minimumCourses':7,'minimumLevel':3000}})
 elif name=='Computer Information Science, Minor':
  p=add(name,[allof(x['core'],16),select(1,'CS',[4000,5000],4,['CS4100','CS5620'])],{'minimumCredits':20,'minimumGrade':'C','minimumCreditsOutsideMajor':16,'maximumTransferCourses':2,'prerequisiteSubstitutions':x['prerequisiteSubstitutions']})
 else:p=add(name,[allof(x['core'],16),choose(1,x['elective']['eligibleCourses'],4)],{'minimumCredits':20,'minimumCourseCount':5})
 p['projectDecisions']=x.get('projectDecisions',[])

name='Business Administration, B.S.'
tracks={
 'Management':{'groups':[allof(['BU4120','BU5196','BU5171'],12),choose(2,['BU4035','BU4130','BU5125','BU5195'],8)],'minimumLocalTrackCredits':16},
 'General Business':{'groups':[allof(['BU4120','BU4774','BU4851','BU5171'],16),choose(1,['BU4035','BU4130','BU4160','BU5125','BU5880'],4)],'minimumLocalTrackCredits':16},
 'Business Analytics':{'groups':[allof(['BU4160','BU4180','BU5180','CS2521'],16),choose(1,['BU5710','BU5800'],4)]}}
add(name,[allof(codes(name,'Business Core: 50 Credits','Students must choose'),50),{'type':'selected-concentration','minimumCredits':20}],{'majorCredits':70},tracks)

name='Accounting, B.S.'
x=add(name,[allof(codes(name,'Accounting Courses Required:','Accounting elective'),38),creditpool(2,['BU5520','BU5525','BU5560']),allof(codes(name,'Business Courses Required:','Total Accounting/Business'),26),allof(codes(name,'Liberal Arts Requirement: 22 Credits','Business or Liberal Arts Electives'),22)],{'accountingMinimumCredits':40,'accountingBusinessMinimumCredits':66},notes=['Supporting-course group label does not automatically classify every BU course as liberal arts; use approved classification evidence.'])
x['degreeRequirements']=degree(True,126,46)

name='Mathematics, B.S.'
x=add(name,[allof(['MA2310','MA2320','MA3030','MA3160','MA3210','MA3330','MA3520','MA4360','MA5120','MA5320'],40),choose(1,['CS2510','CS2521'],4),{'type':'choose-from-union','minimumCount':3,'minimumCredits':12,'eligibleCourses':['CS3810'],'selectors':[{'subject':'MA','levels':[4000,5000]}]}],{'minimumGrade':'C','majorCredits':56,'transferMajorResidency':{'credits':28,'minimumCourses':7,'minimumLevel':3000}})

name='Biological Sciences, B.S.'
bio_pool=codes(name,'Biology Electives','3. Completion')
bio_core=['BS2400','BS2401','BS2410','BS2411']
physics_tracks={'Algebra':allof(['PX2000','PX2001','PX2100','PX2101'],8),'Calculus':allof(['PX2200','PX2201','PX2300','PX2301'],8)}
upper={'type':'allocation-group','minimumCount':6,'minimumCredits':24,'groups':[
 choose(1,['BS4400','BS4460','BS4560','BS3910']),choose(1,['BS3400','BS3520','BS4430','BS4410']),choose(1,['BS4440','BS4470','BS4474','BS3530']),choose(3,bio_pool)],
 'constraints':[{'type':'minimum-lab-bearing-electives','minimum':1,'scope':'additional-electives','attributeEvidenceRequired':True}],
 'allowAdditionalEligibleCoursesToMeetCredits':True}
add(name,[allof(bio_core,8),allof(['BS5400'],4),allof(['CP2120','CP2121','CP2130','CP2131','CP3300','CP3302','CP3310','CP3312'],18),pathset(physics_tracks),allof(['MA2310'],4),upper],{'departmentMinimumCredits':66,'biologyMinimumGrade':'C','nonBiologyLowGradeException':{'minimumGrade':'D','maximumCourses':2,'subjects':['CP','PX','MA'],'reviewedExceptionRequired':True},'maximumAttemptsPerBiologyCourse':2},notes=['Honor both the 24-credit floor and six-course minimum; eligible 3-credit electives may require an additional course.','The same course cannot fill a distribution slot and an additional-elective slot.'])

name='Physics, B.S.'
tracks={
 'General Physics':{'groups':[select(2,'PX',[3000,4000,5000],6),{'type':'choose-from-union','minimumCount':2,'minimumCredits':8,'eligibleCourses':['MA3160'],'selectors':[{'subject':'MA','levels':[4000,5000]}]}]},
 'Health Sciences':{'groups':[allof(['BS2400','BS2401','BS2410','BS2411','CP3300','CP3302','CP3310','CP3312'],18),choose(1,['CP4490','CP4510'],3)]},
 'Quantitative Finance':{'groups':[allof(['CS2510','MA2000','MA3160','MA4360','BU4762','BU5740','BU5789','PX5100'],31)]},
 'Applied/Computational':{'groups':[allof(['CS2510','CS2511','CS3810','MA3030','PX4600'],19)]}}
add(name,[allof(['PX2200','PX2201','PX2300','PX2301','CP2120','CP2121','CP2130','CP2131'],16),allof(['PX3000','PX3100','PX3200','PX3600','PX3700','PX3900','PX4100','PX4200','PX5500','PX5600'],26),allof(['MA2310','MA2320','MA3330'],12),{'type':'selected-concentration','minimumCredits':14}],{'coreCredits':54},tracks)

name='English, B.A.'
a=['EL3600','EL3610','EL3800','EL3810'];b=['EL3560','EL3561','EL3700'];c=['EL3950','EL4200','EL4300','EL4311','EL4312','EL4325','EL4500','EL4510','EL4520']
multicultural=['EL4030','EL4050','EL4060','EL4080','ML3410','EL3811','EL4000','EL4010','EL4015','EL4020','EL4040','EL4070','EL4090','EL4091','EL4405','EL4550','EL4560']
tracks={'Multicultural U.S.-British Literature':{'groups':[choose(2,multicultural,8),allof(['EL4800'],4)]},'World Literature':{'groups':[choose(1,['EL3865','EL3870','EL3880','ML3880'],4),choose(2,['EL4405','EL4600','EL4630','EL4640','EL4650'],8)]},'Creative Writing':{'groups':[allof(['EL3901'],4),choose(2,['EL3920','EL3950','EL4100','EL4520'],8)]}}
add(name,[allof(['EL3010'],4),choose(2,['EL3500','EL3510','EL4400'],8),{'type':'allocation-group','groups':[choose(1,a),choose(1,b),choose(1,a+b+c)],'minimumCredits':12},{'type':'selected-concentration','minimumCredits':12},allof(['EL5000','EL5010'],8)],{'majorCredits':44,'minimumGrade':'C-','maximumCoursesAtGradeCMinus':1,'maximumTransferCourses':3,'repeatPolicy':'More than two attempts requires chair approval','distribution':[{'type':'course-attribute-count','attribute':'literature-before-1800-or-US-before-1900','minimum':2},{'type':'each-attribute-required','attributes':['British-literature','US-literature','World-literature'],'specialRule':'EL3500/EL3510 may satisfy British or US, not both.'}]},tracks,notes=['EL3811 is retained as a source-listed option; insufficient metadata prevents automatic registration/credit evaluation for that option until reviewed.'])

name='Psychology, B.S.'
x=existing[name];f=x['foundations']
groups=[allof(x['core']),choose(1,x['seniorSeminar']['options']),choose(1,f['cognitivePhysiological']['options']),{'type':'choose-distinct-domain-courses','minimumDomains':2,'coursesPerDomain':1,'domains':f['developmentalClinicalSocial']['domains']},choose(1,f['diversitySocialJustice']['options']),select(1,'PY',[3000,4000]),{**select(1,'PY',[4000]),'localCourseRequired':True},reviewed('psychology-bs-math-science-plan',x['BSAdditionalRequirements'])]
p=add(name,groups,{'majorCourseCount':11,'departmentalGradeRule':x['departmentalGradeRule']},notes=['Use the supplied prerequisite definitions for progression; no additional policy PDF required.','Department GPA uses highest repeat grades; institution GPA follows its own repeat policy.','Science plan and advisor approval are reviewed evidence; raw source wording is preserved.']);p['degreeRequirements']=x['degreeRequirements'];p['projectDecisions']=x['projectDecisions']

name='Childhood Education 1-6, B.S.'
x=existing[name]
math_concentration={'groups':[allof(['MA2010','MA3020','MA2310','MA2320','MA3030','MA3160','MA3210'],28),select(1,'MA',[3000,4000],4)],'minimumCredits':32,'minimumGrade':'C','maximumTransferCourses':4,'maximumTransferCredits':16,'source':'supplemental-sources/education-concentrations.json'}
p=add(name,[{'type':'all-course-versions','courses':x['core']['courses'],'minimumCredits':36},allof(['ED5940'],12),{'type':'selected-concentration','minimumCredits':32},reviewed('school-of-education-admission','Use the collected admissions rules: 32 completed credits, required math/writing proficiency, ED3700/ED3820, C in ED3900, overall GPA >=3.0 for Childhood Education; log formal admission.')],{'courseMinimumGrades':{'ED5940':'B'},'fieldExperienceApprovalRequired':True},{'Mathematics':math_concentration},notes=['Mathematics is the initially offered concentration recommended for the isolated pilot; other official options remain in source evidence for later expansion. No existing student is reassigned.','Historical ED4050/ED5925 versions remain explicit. Malformed ED5925 prerequisite text requires a logged advisor resolution, not an invented ED400 course.']);p['projectDecisions']=['ED-CORE-001','REC-ED-MATH-001']

name='Public Health, B.S.'
ph_core=['PH2000','PH3600','PH3610','PH4670','PH4800','PH4900','PH5900','PH5920']
ph_options=codes(name,'Three Public Health Department Electives','Public Health Applied Practice Experience')
add(name,[choose(1,['BS2100','BS2300','BS2310'],3),allof(ph_core,32),choose(3,ph_options,12),reviewed('applied-practice-90-hours','At least 90 hours of applied practice with completion evidence; do not infer hours solely from course grade.')],{'minimumMajorCourseUnits':12},notes=['The explicit eight-core + three-elective + one-biology groups determine completion; loose minimum-11 wording does not remove a listed group.','Count actual biology credits: an eligible 4-credit BS2100 is not reduced to 3.'])

name='Media & Communications, B.A.'
p=add(name,[choose(1,codes(name,'1. Knowledge of American Society','2. Media Studies'),4),choose(2,codes(name,'2. Media Studies','3. Writing, Analysis'),8),allof(['MC2802','MC3800','MC4802'],12),{**choose(2,codes(name,'4. Media Production','5. Upper-Division'),8),'maximumSubjectCourses':{'VA':1}}, {'type':'allocation-group','groups':[select(1,['AS','MC'],[3000,4000],4),select(1,['AS','MC'],[4000,5000],4)],'approvedCategory':'Media Studies or Media Production','minimumCredits':8},creditpool(4,['MC4832','MC4835']),choose(1,codes(name,'7. Capping Course','8. Portfolio'),4)],{'majorCredits':48,'minimumMajorGPA':2.0,'maximumLowGradeCourses':{'grades':['D+','D','D-'],'count':1,'chairApprovalRequired':True},'maximumVACourses':1,'maximumTransferCourses':5,'minimumLocalProductionCourses':1,'maximumInternshipCreditsAppliedToMajor':4,'maximumIndependentStudyCreditsAppliedToMajor':4,'maximumExtraLowerDivisionCourses':2,'lowerDivisionExemptions':['MC2802','AS2802','AS2252','AS2262','AS2263','AS2300','AS2640']},notes=['The 8-credit upper-division elective group uses approved Media Studies/Production categories; an arbitrary AS/MC course is not automatically eligible.','Portfolio is recommended, not a mandatory graduation requirement.'])

# Remaining minors.
name='General Business, Minor';add(name,[allof(['BU3010','BU3502','BU3800','BU4110'],15),choose(1,['BU4035','BU5125','BU4851'])],{'minimumCredits':18,'minimumCourseCount':5},notes=['With the published current credits these choices normally yield 19 credits; retain actual credits.'])
name='Accounting, Minor';opts={'Internal Reporting':creditpool(8,['BU4250','BU4590','BU5505']),'Public Company':creditpool(8,['BU5510','BU5540','BU5550']),'General Accounting':creditpool(8,['BU4250','BU4590','BU5505','BU5510','BU5540','BU5550'])};add(name,[allof(['BU3502','BU3511','BU4500','BU4510','BU4570'],18),pathset(opts)],{'minimumCredits':26,'minimumCreditsOutsideMajor':20,'maximumTransferCourses':2})
name='Mathematics, Minor';add(name,[allof(['MA2310','MA2320','MA3030','MA3160'],16),{'type':'choose-from-union','minimumCount':2,'minimumCredits':8,'eligibleCourses':['MA3210','MA3330','MA3520','MA4100'],'selectors':[{'subject':'MA','levels':[4000,5000]}]}],{'minimumCredits':24,'minimumGrade':'C','minimumCreditsOutsideMajor':16,'maximumTransferCourses':2})
name='Environmental Studies, Minor'
science=['BS2490','BS3450','BS4470','BS4474','BS4491','CP2600',bundle(['CP2300','CP2301']),bundle(['CP2400','CP2401']),bundle(['CP2500','CP2501']),'CP5900',{'type':'approved-independent-study','studentHomeDepartment':True,'sourcePlaceholder':'XX9990'}]
hums=codes(name,'Elective Courses: Humanities and Social Sciences');hums=[c for c in hums if c!='HI3001']+[{'type':'approved-independent-study','studentHomeDepartment':True,'sourcePlaceholder':'XX9990'}]
add(name,[allof(['HI3001'],4),{'type':'allocation-group','minimumCredits':16,'minimumUnitCount':4,'groups':[choose_units(1,science,4),choose_units(1,hums,4),choose_units(2,science+hums)],'allowAdditionalEligibleUnitsToMeetCredits':True}],{'minimumCredits':20},notes=['HI3001 cannot count twice. A 3+1 lecture/lab bundle counts as one science elective unit with 4 earned credits. XX9990 is an approval-based placeholder, not a course to create.'])
name='Physics, Minor';astronomy=bundle(['PX2900','PX2901']);add(name,[pathset(physics_tracks),{'type':'choose-distinct-units','minimumCount':3,'minimumCredits':9,'options':[astronomy,'PX3000','PX3100','PX3200','PX3600','PX3700','PX3800','PX3900','PX4100','PX4200','PX5000'],'minimumUpperDivisionUnits':2}],{'minimumCredits':17},notes=['Astronomy lecture plus lab counts as one bundled elective unit, contributing its actual 4 credits; minimum degree/minor totals are not forced to exactly 17.'])
name='Multicultural U.S./ British Literature, Minor';add(name,[allof(['EL3010'],4),choose(1,['EL3500','EL3510','EL4400'],4),choose(1,['EL3600','EL3610','EL3800','EL3810','EL3950','EL4200','EL4300','EL4325','EL4500','EL4510','EL4520'],4),choose(1,multicultural,4),allof(['EL4800'],4)],{'minimumCredits':20},notes=['EL4800 prerequisites may require additional analysis courses beyond the minor group; do not waive prerequisites.'])
name='Psychology, Minor';add(name,[choose(1,['PY3010','PH4670','SY4520'],4),choose(1,f['cognitivePhysiological']['options'],4),choose(1,sum(f['developmentalClinicalSocial']['domains'].values(),[]),4),choose(1,f['diversitySocialJustice']['options'],4),{**select(1,'PY',[4000],4),'localCourseRequired':True}],{'minimumCredits':20,'minimumCourseCount':5,'minimumMinorGPA':2.5})
name='Public Health, Minor';add(name,[allof(['PH2000','PH3600','PH3610','PH4800'],16),{'type':'choose-from-union','minimumCount':1,'minimumCredits':4,'eligibleCourses':['PY3020'],'selectors':[{'subject':'PH','classification':'approved-public-health-elective','excludeRequiredCore':True}]}],{'minimumCredits':20,'minimumGrade':'C','maximumCoursesSharedWithMajor':1,'maximumTransferCourses':1})
name='Media and Communications, Minor';add(name,[allof(['MC2802'],4),choose(2,codes(name,'B. Media Studies','C. Media Production'),8),choose(2,codes(name,'C. Media Production'),8)],{'minimumCredits':20},notes=['Only the listed minor production pool is automatically offered. The one-VA limit does not invent unlisted VA choices; substitutions need approval.'])
name='Visual Arts, Minor';add(name,[allof(['VA2020','VA2030','VA2045'],12),pathset({'Art History':{'type':'choose-by-selector','minimumCount':2,'minimumCredits':8,'subject':'VA','minimumLevel':3000,'classification':'art-history'},'Fine Arts':choose(2,['VA2010','VA2460','VA2750'],8),'Electronic Media':choose(2,['VA2320','VA3380','VA3400','VA3456','VA4260','VA4265','VA4460'],8)})],{'minimumCredits':20})

assert set(curricula)==set(P), (set(P)-set(curricula),set(curricula)-set(P))
assert sum(p['kind']=='major' for p in curricula.values())==12
assert sum(p['kind']=='minor' for p in curricula.values())==12
# Attach source text and inherited institutional policy explicitly; qualifiers are not inferred from prefixes.
for p in curricula.values():
 p['source']['fullTextInput']='program-review-inputs.json'
 p['manualEvidencePolicy']='Unresolved attributes, substitutions, permissions and unparseable prerequisites require recorded review; never automatically satisfied.'
 if p['kind']=='major':p['generalEducationPolicy']='See institutional-rules.json and general-education-mappings.json; approved overlaps count course credits once.'
save('curricula.json',{'status':'complete-program-coverage-for-isolated-test-with-explicit-review-gates','programs':list(curricula.values())})
recommendations=[
 {'id':'REC-ED-MATH-001','decision':'Offer Mathematics as the initial Education concentration; retain other source options for later expansion.','basis':'Complete 32-credit definition available; shares reviewed math catalog; no student reassignment.','source':'supplemental-sources/education-concentrations.json'},
 {'id':'REC-BIO-CREDITS-001','decision':'Require at least six distinct upper-level elective/distribution course units AND 24 credits. Permit additional eligible courses if 3-credit options leave a shortfall.','basis':'Preserves both published minima rather than dropping a credit requirement.'},
 {'id':'REC-PH-COUNT-001','decision':'Use all explicit groups (one biology + eight PH core + three electives); count actual biology credits.','basis':'Specific listed requirements take precedence over a loose minimum-course summary.'},
 {'id':'REC-PHYSICS-ASTRO-001','decision':'Treat astronomy lecture/lab as one elective bundle; count actual credits and preserve at least two upper-level elective units.','basis':'Source lists lecture AND lab within the elective choices.'},
 {'id':'REC-MANUAL-001','decision':'Use reviewed evidence for genuinely unsupported rules; never treat missing data as a pass.','basis':'User delegated routine recommendations; this does not authorize invented official facts.'}]
for d in recommendations:d.update({'authority':'user delegated recommendations on November 7, 2026','status':'adopted-northbridge-project-interpretation','workingDataApplied':False})
save('recommended-interpretations.json',recommendations)
print('Built 24 curriculum definitions: 12 majors, 12 minors, and explicit concentration/counting policies.')
