<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/academic/evaluator.php';
require __DIR__.'/fixtures/academic/mis.php';
use Northbridge\Academic\MisDegreeEvaluator;
use Northbridge\Academic\PrerequisiteEvaluator;
$root=dirname(__DIR__).'/database/staging/academic-alignment/rules/';
$read=static fn($file)=>json_decode(file_get_contents($root.$file.'.json'),true,512,JSON_THROW_ON_ERROR);
$program=array_values(array_filter($read('curricula')['programs'],static fn($p)=>$p['program']==='Management Information Systems, B.S.'))[0];
$metadata=[];foreach ($read('course-metadata')['courses'] as $c) $metadata[$c['courseCode']]=$c;
$rules=[];foreach ($read('all-course-prerequisites')['courses'] as $r) $rules[$r['courseVersionOrCode']]=$r['rule'];
$institutional=$read('institutional-rules');$degree=new MisDegreeEvaluator($metadata);$prerequisites=new PrerequisiteEvaluator();
$audit=static fn($s)=>$degree->evaluate($program,$s,$institutional);
$checks=0;
function mis_check(bool $ok,string $label): void {global $checks;$checks++;if (!$ok) throw new RuntimeException('FAIL: '.$label);}
$f=mis_complete_fixture($program);$a=$audit($f);
mis_check($a['graduationReady']===true,'Complete 120-credit MIS fixture');
mis_check(array_sum(array_column($a['groups'],'credits'))===66.0,'MIS major is 66 credits');
mis_check($a['groups'][1]['credits']===14.0,'Two three-credit accounting courses keep business core at 14');
mis_check($a['totals']['earnedCredits']===120.0,'Actual mixed credit values total 120');
mis_check($a['scope']==='isolated-mis-pilot','MIS result correctly labeled');
$partial=$f;$partial['attempts']=array_slice($f['attempts'],0,17);$a=$audit($partial);
mis_check($a['checks']['group-5']['status']==='satisfied' && !$a['graduationReady'],'66 major credits alone do not graduate');
$short=$f;array_pop($short['attempts']);mis_check($audit($short)['totals']['earnedCredits']===118.0 && !$audit($short)['graduationReady'],'118 is not rounded up to 120');
// Every permitted economics pair and math/statistics combination is independent.
$pairs=[['MA2701','PE2420'],['MA2701','PE2430'],['PE2420','PE2430']];
foreach ($pairs as $pair) foreach (['MA2300','MA2310','MA3030'] as $math) foreach (['MA2000','MA3210'] as $stats) {
    $s=$f;$replace=['MA2701','PE2420','PE2430','MA2300','MA2310','MA3030','MA2000','MA3210'];
    $s['attempts']=array_values(array_filter($s['attempts'],static fn($a)=>!in_array($a['courseCode'],$replace,true)));
    foreach (array_merge($pair,[$math,$stats]) as $code) $s['attempts'][]=cis_attempt($code);
    $a=$audit($s);mis_check($a['graduationReady']===true,'Valid distinct pair/math/stats combination '.implode('/',$pair).'/'.$math.'/'.$stats);
}
$s=$f;$s['attempts']=array_values(array_filter($s['attempts'],static fn($a)=>!in_array($a['courseCode'],['MA2701','PE2420','PE2430'],true)));
$s['attempts'][]=cis_attempt('MA2701');$s['attempts'][]=cis_attempt('MA2701','A',['termOrder'=>2]);$a=$audit($s);
mis_check($a['checks']['group-3']['status']==='unsatisfied','Repeating one economics/analytics course cannot fill two slots');
mis_check(count($a['groups'][2]['selectedCourses'])===1,'Distinct economics choice counts once');
$s=$f;$s['attempts'][]=cis_attempt('PE2430');$a=$audit($s);mis_check(count($a['groups'][2]['selectedCourses'])===2,'Taking all three does not require or allocate all three');
foreach (array_slice($f['attempts'],0,17) as $i=>$attempt) {
    $s=$f;$s['attempts'][$i]['grade']='C-';$a=$audit($s);mis_check(!$a['graduationReady'],'C-minus fails required major course '.$attempt['courseCode']);
}
$s=$f;foreach ($s['attempts'] as &$attempt) $attempt['grade']='C';unset($attempt);mis_check($audit($s)['graduationReady']===true,'Exact C passes all MIS course and GPA floors');
$s=$f;$s['attempts'][13]['status']='in-progress';$a=$audit($s);mis_check($a['checks']['group-3']['status']==='unsatisfied','Pending economics choice is not earned');mis_check($a['totals']['earnedCredits']===116.0,'Pending economics credits excluded');
$s=$f;$s['attempts'][9]['credits']=4;mis_check($audit($s)['status']==='review-required','BU3502 cannot be silently converted to four credits');
$s=$f;$s['attempts'][0]['origin']='transfer';$s['attempts'][0]['grade']='TC';mis_check(!$audit($s)['graduationReady'],'Unapproved transfer equivalent blocked');
$s['attempts'][0]['equivalenceApproval']=cis_approval('equivalence:CS2510');$a=$audit($s);mis_check($a['graduationReady']===true,'Approved transferred C satisfies MIS minimum');
$s=$f;$s['isTransferStudent']=true;
foreach ($s['attempts'] as &$attempt) {$attempt['origin']='transfer';$attempt['equivalenceApproval']=cis_approval('equivalence:'.$attempt['courseCode']);}unset($attempt);
foreach (array_merge(range(2,8),range(17,19)) as $i) $s['attempts'][$i]['origin']='local';
$a=$audit($s);mis_check($a['graduationReady']===true && $a['totals']['localCredits']===40.0 && $a['totals']['transferCredits']===80.0,'Transfer boundary: 40 local and 28 across seven upper major courses');
$s['attempts'][2]['origin']='transfer';$s['attempts'][20]['origin']='local';$a=$audit($s);mis_check($a['totals']['localCredits']===40.0 && $a['checks']['transfer-major-residency']['status']==='unsatisfied','General local electives cannot replace upper major residency');
$s=$f;unset($s['isTransferStudent']);mis_check(!$audit($s)['graduationReady'],'Unknown transfer admission status requires review');
$s=$f;unset($s['proficiencies']['CompositionII']);mis_check(!$audit($s)['graduationReady'],'MIS retains local writing proficiency');
$s=$f;foreach ($s['attempts'] as &$attempt) unset($attempt['attributeApproval']);unset($attempt);mis_check($audit($s)['checks']['liberal-arts']['status']==='review-required','Liberal arts attributes require evidence');
$eligibility=static fn($code,$student)=>$prerequisites->evaluate($rules[$code],$student,10)['status'];
$s=['attempts'=>[cis_attempt('MA2000','C'),cis_attempt('MA2300','C')]];mis_check($eligibility('MA2701',$s)==='satisfied','Analytics requires statistics AND business calculus at C');
$s['attempts'][0]['grade']='C-';mis_check($eligibility('MA2701',$s)==='unsatisfied','Analytics statistics C minimum');
$s['attempts'][0]['grade']='C';$s['attempts'][1]['grade']='C-';mis_check($eligibility('MA2701',$s)==='unsatisfied','Analytics business calculus C minimum');
$s['attempts'][1]=cis_attempt('MA2310','A');mis_check($eligibility('MA2701',$s)==='unsatisfied','Degree math alternative is not an automatic prerequisite equivalency');
$s['attempts'][1]=cis_attempt('MA2300','B',['status'=>'in-progress']);mis_check($eligibility('MA2701',$s)==='conditional','Pending analytics prerequisite needs later grade recheck');
$s=['attempts'=>[],'program'=>'MIS','completedStandingCredits'=>57,'proficiencies'=>cis_writing()];mis_check($eligibility('CS4100',$s)==='satisfied','MIS junior standing and writing accepted');
mis_check($eligibility('BU3800',$s)==='review-required','EMS token is not inferred from Composition II');
$s['proficiencies']['EMS']=cis_approval('proficiency:EMS',['basis'=>'approved-equivalent','termOrder'=>1]);mis_check($eligibility('BU3800',$s)==='satisfied' && $eligibility('BU4110',$s)==='satisfied','Explicit scoped EMS approval satisfies business conditions');
$other=$program;$other['program']='Computer and Information Science, B.S.';mis_check(!$degree->evaluate($other,$f,$institutional)['graduationReady'],'MIS entry point rejects another major');
echo "PASS: $checks MIS degree/prerequisite scenario checks. Synthetic evidence only; no DB/network access.\n";
