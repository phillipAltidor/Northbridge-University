<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/academic/curriculum-evaluator.php';
require __DIR__.'/fixtures/academic/cis.php';
use Northbridge\Academic\CurriculumEvaluator;
use Northbridge\Academic\PrerequisiteEvaluator;
$r=dirname(__DIR__).'/database/staging/academic-alignment/rules/';
$programs=json_decode(file_get_contents($r.'curricula.json'),true)['programs'];$institutional=json_decode(file_get_contents($r.'institutional-rules.json'),true);$metadata=[];
foreach (json_decode(file_get_contents($r.'course-metadata.json'),true)['courses'] as $c) $metadata[$c['courseCode']]=$c;
$engine=new CurriculumEvaluator($metadata,$programs);$cases=json_decode(file_get_contents(__DIR__.'/fixtures/academic/all-programs.json'),true)['cases'];$checks=0;
function boundary(bool $ok,string $label): void {global $checks;$checks++;if (!$ok) throw new RuntimeException('FAIL: '.$label);}
function scenario(string $name,?string $track=null): array {global $cases;foreach ($cases as $c) if ($c['name']===$name && ($track===null||$c['concentration']===$track)) return $c;throw new RuntimeException('Missing fixture.');}
function evaluate_case(array $case): array {global $programs,$engine,$institutional;$p=array_values(array_filter($programs,static fn($p)=>$p['program']===$case['name']&&$p['kind']===$case['kind']))[0];return $engine->evaluate($p,$case['student'],$institutional);}
function edit_course(array &$case,string $code,array $values): void {foreach ($case['student']['attempts'] as &$a) if ($a['courseCode']===$code) $a=array_replace($a,$values);unset($a);}
function remove_course(array &$case,string $code): void {$case['student']['attempts']=array_values(array_filter($case['student']['attempts'],static fn($a)=>$a['courseCode']!==$code));}
foreach ($cases as $case) {
    $a=evaluate_case($case);boundary($a['status']==='satisfied','Complete path '.$case['name'].'/'.$case['concentration']);
    boundary(count($a['selectedCourses'])===count(array_unique($a['selectedCourses'])),'No course used twice '.$case['name']);
    if ($case['kind']==='minor') boundary($a['graduationReady']===false,'A completed minor alone is not a completed degree');
    $empty=$case;$empty['student']['attempts']=[];boundary(evaluate_case($empty)['status']!=='satisfied','Empty transcript never completes '.$case['name']);
    $pending=$case;foreach ($pending['student']['attempts'] as &$a) $a['status']='in-progress';unset($a);boundary(!evaluate_case($pending)['programComplete'],'Pending work is not completion '.$case['name']);
    $bad=$case;$bad['student']['attempts'][0]['credits']=99;boundary(evaluate_case($bad)['status']==='review-required','Catalog credits enforced '.$case['name']);
    $failed=$case;foreach ($failed['student']['attempts'] as &$a) $a['grade']='F';unset($a);boundary(evaluate_case($failed)['status']!=='satisfied','Failed work cannot complete '.$case['name']);
}
// Business: select one track, and honor 16 local track credits.
$c=scenario('Business Administration, B.S.','Management');unset($c['student']['concentration']);boundary(!evaluate_case($c)['programComplete'],'Business track selection required');
$c=scenario('Business Administration, B.S.','Management');$a=evaluate_case($c);$codes=$a['groups'][1]['codes'];foreach (array_slice($codes,0,2) as $code) edit_course($c,$code,['origin'=>'transfer','equivalenceApproval'=>cis_approval('equivalence:'.$code)]);boundary(evaluate_case($c)['checks']['track-residency']['status']==='unsatisfied','Business 16 local track credits');
// Accounting: exact 126 degree total and 46 local credits, with two-credit electives.
$c=scenario('Accounting, B.S.');$a=evaluate_case($c);boundary($a['totals']['earnedCredits']===126.0,'Accounting fixture earns actual 126');
$codes=array_keys(array_filter($c['roles'],static fn($r)=>$r==='general-elective'));remove_course($c,$codes[0]);boundary(evaluate_case($c)['checks']['total-credits']['status']==='unsatisfied','Accounting does not use 120-credit floor');
$c=scenario('Accounting, B.S.');foreach ($c['student']['attempts'] as &$a) {$a['origin']='transfer';$a['equivalenceApproval']=cis_approval('equivalence:'.$a['courseCode']);}unset($a);boundary(evaluate_case($c)['checks']['institutional-residency']['status']==='unsatisfied','Accounting 46 local credits retained');
// Biology: no extra D exceptions, no third attempt, lab evidence cannot be discarded.
$c=scenario('Biological Sciences, B.S.');foreach (['CP2120','PX2000'] as $code) edit_course($c,$code,['grade'=>'D']);boundary(!evaluate_case($c)['programComplete'],'Biology D exceptions require approval');
$c['student']['approvals']['biology-low-grade-exception']=cis_approval('biology-low-grade-exception');boundary(evaluate_case($c)['programComplete'],'Two approved non-biology D grades allowed');
edit_course($c,'MA2310',['grade'=>'D']);boundary(evaluate_case($c)['checks']['biology-low-grades']['status']==='unsatisfied','Third low-grade exception rejected');
$c=scenario('Biological Sciences, B.S.');edit_course($c,'BS2400',['grade'=>'D']);boundary(!evaluate_case($c)['programComplete'],'Biology courses retain C floor');
$c=scenario('Biological Sciences, B.S.');$c['student']['attempts'][]=cis_attempt('BS2400','B',['credits'=>3,'termOrder'=>2,'catalogVersion'=>'2026-2027']);$c['student']['attempts'][]=cis_attempt('BS2400','A',['credits'=>3,'termOrder'=>3,'catalogVersion'=>'2026-2027']);boundary(evaluate_case($c)['checks']['biology-attempts-BS2400']['status']==='unsatisfied','Biology third attempt rejected');
$c=scenario('Biological Sciences, B.S.');foreach ($c['student']['attempts'] as &$a) $a['labBearing']=false;unset($a);boundary(!evaluate_case($c)['programComplete'],'At least one additional lab-bearing elective required');
// Lecture/lab tracks must stay complete; a lab is not an extra elective unit.
$c=scenario('Physics, Minor');remove_course($c,'PX2901');boundary(!evaluate_case($c)['programComplete'],'Incomplete astronomy bundle is not an elective unit');
$c=scenario('Physics, Minor');foreach ($c['student']['attempts'] as &$a) $a['upperDivision']=false;unset($a);boundary(!evaluate_case($c)['programComplete'],'Physics minor requires two upper elective units');
$c=scenario('Environmental Studies, Minor');$core=cis_attempt('HI3001','A',['termOrder'=>2,'catalogVersion'=>'2026-2027']);$c['student']['attempts']=[$c['student']['attempts'][0],$core];boundary(!evaluate_case($c)['programComplete'],'Repeated HI3001 cannot fill environmental elective units');
// English: one C-minus, three transfers, repeat approval and category distribution.
$c=scenario('English, B.A.');$a=evaluate_case($c);$codes=$a['selectedCourses'];edit_course($c,$codes[0],['grade'=>'C-']);boundary(evaluate_case($c)['programComplete'],'One English C-minus accepted');edit_course($c,$codes[1],['grade'=>'C-']);boundary(!evaluate_case($c)['programComplete'],'Two English C-minus grades rejected');
$c=scenario('English, B.A.');foreach (array_slice(evaluate_case($c)['selectedCourses'],0,4) as $code) edit_course($c,$code,['origin'=>'transfer','equivalenceApproval'=>cis_approval('equivalence:'.$code)]);boundary(evaluate_case($c)['checks']['maximumTransferCourses']['status']==='unsatisfied','English four-transfer limit rejected');
$c=scenario('English, B.A.');foreach ($c['student']['attempts'] as &$a) $a['classifications']=[];unset($a);boundary(evaluate_case($c)['checks']['english-distribution']['status']==='unsatisfied','English historical and literature distributions required');
$c=scenario('English, B.A.');$base=$c['student']['attempts'][0];$c['student']['attempts'][]=array_replace($base,['termOrder'=>2]);$c['student']['attempts'][]=array_replace($base,['termOrder'=>3]);boundary(evaluate_case($c)['checks']['english-repeat-'.$base['courseCode']]['status']==='review-required','English third attempt needs chair approval');$c['student']['approvals']['english-repeat:'.$base['courseCode']]=cis_approval('english-repeat:'.$base['courseCode']);boundary(evaluate_case($c)['programComplete'],'Scoped English repeat approval accepted');
// Psychology highest-grade department alternative is distinct from latest institutional GPA.
$c=scenario('Psychology, B.S.');foreach (evaluate_case($c)['selectedCourses'] as $code) edit_course($c,$code,['grade'=>'C']);boundary(evaluate_case($c)['programComplete'],'Psychology all-C alternative works below 2.5');
$c=scenario('Psychology, B.S.');$code=evaluate_case($c)['selectedCourses'][0];edit_course($c,$code,['grade'=>'D']);boundary(evaluate_case($c)['programComplete'],'Psychology 2.5 GPA alternative can allow low passing grade');
$c=scenario('Psychology, B.S.');foreach (evaluate_case($c)['selectedCourses'] as $code) edit_course($c,$code,['grade'=>'C-']);boundary(!evaluate_case($c)['programComplete'],'Psychology neither grade alternative satisfied');
$c=scenario('Psychology, B.S.');array_pop($c['student']['approvals']['psychology-bs-math-science-plan']['scienceSemesters']);boundary(evaluate_case($c)['checks']['psychology-science-plan']['status']==='unsatisfied','Psychology four science semesters required');
$c=scenario('Psychology, B.S.');$c['student']['approvals']['psychology-bs-math-science-plan']['advisorDeadlineReviewed']=false;boundary(evaluate_case($c)['checks']['psychology-science-plan']['status']==='review-required','Psychology advisor deadline evidence required');
$c=scenario('Psychology, Minor');foreach ($c['student']['attempts'] as &$a) $a['grade']='C';unset($a);boundary(!evaluate_case($c)['programComplete'],'Psychology minor requires 2.5 GPA');
// Education: correct historical course meaning, B student teaching, formal admission and concentration limits.
$c=scenario('Childhood Education 1-6, B.S.');edit_course($c,'ED5925',['catalogVersion'=>'2026-2027']);boundary(!evaluate_case($c)['programComplete'],'New ED5925 title/version cannot stand for historical approved version');
$c=scenario('Childhood Education 1-6, B.S.');edit_course($c,'ED5940',['grade'=>'B-']);boundary(!evaluate_case($c)['programComplete'],'Student teaching B minimum');
$c=scenario('Childhood Education 1-6, B.S.');$c['student']['approvals']['school-of-education-admission']['gpaAtAdmission']=2.99;boundary(!evaluate_case($c)['programComplete'],'Education admission GPA boundary');
$c=scenario('Childhood Education 1-6, B.S.');unset($c['student']['approvals']['education-field-experience']);boundary(!evaluate_case($c)['programComplete'],'Education field completion approval required');
$c=scenario('Childhood Education 1-6, B.S.');foreach (array_slice(evaluate_case($c)['groups'][2]['codes'],0,5) as $code) edit_course($c,$code,['origin'=>'transfer','equivalenceApproval'=>cis_approval('equivalence:'.$code)]);boundary(evaluate_case($c)['checks']['track-transfer-count']['status']==='unsatisfied','Education concentration four-transfer limit');
// Public Health practice and overlap restrictions.
$c=scenario('Public Health, B.S.');$c['student']['approvals']['applied-practice-90-hours']['hours']=89;boundary(!evaluate_case($c)['programComplete'],'89 practice hours insufficient');
$c=scenario('Public Health, Minor');$c['student']['majorCourseCodes']=array_slice(evaluate_case($c)['selectedCourses'],0,2);boundary(!evaluate_case($c)['programComplete'],'Public Health minor at most one shared major course');
$c=scenario('Public Health, Minor');foreach (array_slice(evaluate_case($c)['selectedCourses'],0,2) as $code) edit_course($c,$code,['origin'=>'transfer','equivalenceApproval'=>cis_approval('equivalence:'.$code)]);boundary(!evaluate_case($c)['programComplete'],'Public Health minor at most one transfer');
// Minor outside-major rules and transfer ceilings.
foreach (['Computer Information Science, Minor','Mathematics, Minor','Accounting, Minor'] as $name) {
    $c=scenario($name);$a=evaluate_case($c);$c['student']['majorCourseCodes']=$a['selectedCourses'];boundary(!evaluate_case($c)['programComplete'],'Outside-major credits enforced '.$name);
    $c=scenario($name);unset($c['student']['majorAllocationApproval']);boundary(!evaluate_case($c)['programComplete'],'Overlap evidence required '.$name);
    $c=scenario($name);foreach (array_slice(evaluate_case($c)['selectedCourses'],0,3) as $code) edit_course($c,$code,['origin'=>'transfer','equivalenceApproval'=>cis_approval('equivalence:'.$code)]);boundary(!evaluate_case($c)['programComplete'],'Minor transfer course cap '.$name);
}
$c=scenario('General Business, Minor');boundary(evaluate_case($c)['totals']['programCredits']===19.0,'General Business retains actual 19 credits');
$c=scenario('Marketing, Minor');remove_course($c,'BU5880');boundary(!evaluate_case($c)['programComplete'],'Marketing mandatory core cannot be replaced by extra elective');
$c=scenario('Media & Communications, B.A.');foreach ($c['student']['attempts'] as &$a) $a['classifications']=[];unset($a);boundary(!evaluate_case($c)['programComplete'],'Media upper electives and local production require reviewed categories');
$c=scenario('Media & Communications, B.A.');$a=evaluate_case($c);$low=$a['selectedCourses'][0];edit_course($c,$low,['grade'=>'D']);boundary(!evaluate_case($c)['programComplete'],'Media low grade requires chair approval');$c['student']['approvals']['media-low-grade']=cis_approval('media-low-grade');boundary(evaluate_case($c)['programComplete'],'One approved Media D accepted with GPA');
$c=scenario('Visual Arts, Minor');foreach ($c['student']['attempts'] as &$a) $a['classifications']=[];unset($a);boundary(!evaluate_case($c)['programComplete'],'Art History path requires classification');
// Bounded search and package identity must fail closed.
$p=$programs[0];$c=scenario($p['program']);$limited=new CurriculumEvaluator($metadata,$programs,1);boundary($limited->evaluate($p,$c['student'],$institutional)['status']==='review-required','Search exhaustion never completes a degree');
$p['groups']=[];boundary($engine->evaluate($p,$c['student'],$institutional)['status']==='review-required','Tampered program definition rejected');

// Program-scoped prerequisite substitutions never become degree/transcript equivalencies.
$records=[];foreach (json_decode(file_get_contents($r.'all-course-prerequisites.json'),true)['courses'] as $record) $records[$record['courseCode']]=$record;
$prereq=new PrerequisiteEvaluator();$minor=array_values(array_filter($programs,static fn($p)=>$p['program']==='Computer Information Science, Minor'))[0];
$student=['attempts'=>[cis_attempt('MA2080','C')]];
boundary($prereq->evaluateCourse($records['MA2310'],$student,10,$minor)['status']==='satisfied','CIS minor-only MA2080 prerequisite alternative');
boundary($prereq->evaluateCourse($records['MA2310'],$student,10)['status']==='unsatisfied','Minor substitution does not leak to another program');
$co=['courseVersionOrCode'=>'SYN1000','rule'=>['type'=>'corequisite-condition','rule'=>['type'=>'course','courseCode'=>'MA2080','minimumGrade'=>'C']]];
$student['attempts'][0]['status']='in-progress';$student['attempts'][0]['termOrder']=10;
boundary($prereq->evaluateCourse($co,$student,10)['status']==='conditional','Explicit corequisite permits same-term pending enrollment');
$co['rule']=$co['rule']['rule'];boundary($prereq->evaluateCourse($co,$student,10)['status']==='unsatisfied','Ordinary prerequisite still rejects same-term work');
$permission=['courseVersionOrCode'=>'SYN1000','rule'=>['type'=>'approval','authority'=>'course-instructor']];
boundary($prereq->evaluateCourse($permission,[],10)['status']==='review-required','Missing instructor permission is never inferred');
$student['approvals']['course-permission:SYN1000']=cis_approval('course-permission:SYN1000',['authority'=>'course-instructor','termOrder'=>9]);
boundary($prereq->evaluateCourse($permission,$student,10)['status']==='satisfied','Recorded course-scoped instructor approval accepted');
$permission['courseVersionOrCode']='SYN2000';boundary($prereq->evaluateCourse($permission,$student,10)['status']==='review-required','Instructor permission cannot leak across courses');
// Constrained allocation backtracks instead of consuming the sole local 4000 elective too early.
$c=scenario('Psychology, B.S.');$a=evaluate_case($c);$flex=$a['groups'][5]['codes'][0];remove_course($c,$flex);
$c['student']['attempts'][]=cis_attempt('PY3500','B',['credits'=>2,'origin'=>'transfer','equivalenceApproval'=>cis_approval('equivalence:PY3500'),'catalogVersion'=>'2026-2027','level'=>3500,'classifications'=>[]]);
$c['student']['approvals']['departmental-transfer-gpa-policy']=cis_approval('departmental-transfer-gpa-policy',['method'=>'include']);
$a=evaluate_case($c);boundary($a['programComplete'] && $a['allocationsExamined']>1,'Backtracking protects sole local upper Psychology elective');
// Astronomy is one unit, not a second lecture/lab slot; major/private identities are not fixtures.
$c=scenario('Physics, Minor');$a=evaluate_case($c);boundary($a['groups'][1]['units']===3 && $a['totals']['programCredits']>=17,'Physics bundle contributes actual credits and one unit');
$c=scenario('Computer Information Science, Minor');$a=evaluate_case($c);$elective=$a['groups'][1]['codes'][0];remove_course($c,$elective);
$c['student']['attempts'][]=cis_attempt('CS4100','B',['level'=>4100,'classifications'=>[],'catalogVersion'=>'2026-2027']);boundary(!evaluate_case($c)['programComplete'],'CIS minor excludes CS4100 from electives');
$c=scenario('English, B.A.');foreach ($c['student']['attempts'] as &$a) {$a['classifications']=array_values(array_diff($a['classifications'],['British-literature','US-literature']));if (in_array($a['courseCode'],['EL3500','EL3510'],true)) $a['classifications']=array_merge($a['classifications'],['British-literature','US-literature']);}unset($a);boundary(!evaluate_case($c)['programComplete'],'English analysis pair cannot alone satisfy both British and US distribution');

// Cap only credits applied to the major, while preserving all legitimately earned degree credits.
$c=scenario('Media & Communications, B.A.');edit_course($c,'MC4832',['credits'=>8]);$a=evaluate_case($c);
boundary($a['programComplete'] && $a['appliedProgramCredits']['MC4832']==4 && $a['totals']['earnedCredits']==124,'Eight internship credits earn degree credit but only four apply to Media');
$c=scenario('Media & Communications, B.A.');edit_course($c,'MC4832',['credits'=>3]);$c['student']['attempts'][]=cis_attempt('MC4835','B',['credits'=>3,'level'=>4835,'catalogVersion'=>'2026-2027','classifications'=>['internship','Media Production']]);$a=evaluate_case($c);
boundary($a['programComplete'] && $a['appliedProgramCredits']['MC4832']+$a['appliedProgramCredits']['MC4835']==4,'Split internships share a four-credit major cap');
$c=scenario('Media & Communications, B.A.');$codes=evaluate_case($c)['groups'][3]['codes'];foreach ($codes as $code) remove_course($c,$code);foreach (['VA2320','VA2400'] as $code) $c['student']['attempts'][]=cis_attempt($code,'B',['catalogVersion'=>'2026-2027','level'=>(int)substr($code,2),'classifications'=>['Media Production']]);boundary(!evaluate_case($c)['programComplete'],'At most one VA production course can count');
$c=scenario('Biological Sciences, B.S.');$extra=evaluate_case($c)['groups'][5]['children'][3]['codes'];foreach (array_slice($extra,0,2) as $code) remove_course($c,$code);foreach (['CP4490','CP4510'] as $code) $c['student']['attempts'][]=cis_attempt($code,'B',['credits'=>3,'level'=>(int)substr($code,2),'catalogVersion'=>'2026-2027','classifications'=>[],'labBearing'=>true]);boundary(!evaluate_case($c)['programComplete'],'Six Biology elective courses may still fall below 24 credits');
$c['student']['attempts'][]=cis_attempt('BS3810','B',['level'=>3810,'catalogVersion'=>'2026-2027','classifications'=>[],'labBearing'=>true]);$a=evaluate_case($c);boundary($a['programComplete'] && $a['groups'][5]['units']>=7,'Additional distinct elective allowed to reach Biology 24-credit minimum');
echo "PASS: $checks all-program scenario/boundary checks across 24 programs and 37 tracks/paths.\n";
