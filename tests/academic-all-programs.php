<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/academic/curriculum-evaluator.php';
$r=dirname(__DIR__).'/database/staging/academic-alignment/rules/';
$programs=json_decode(file_get_contents($r.'curricula.json'),true)['programs'];$institutional=json_decode(file_get_contents($r.'institutional-rules.json'),true);$metadata=[];
foreach (json_decode(file_get_contents($r.'course-metadata.json'),true)['courses'] as $c) $metadata[$c['courseCode']]=$c;
$engine=new \Northbridge\Academic\CurriculumEvaluator($metadata,$programs);
$cases=json_decode(file_get_contents(__DIR__.'/fixtures/academic/all-programs.json'),true)['cases'];$results=[];$fail=0;
foreach ($cases as $case) {
    $program=array_values(array_filter($programs,static fn($p)=>$p['program']===$case['name']&&$p['kind']===$case['kind']))[0];
    $a=$engine->evaluate($program,$case['student'],$institutional);$ok=$a['status']==='satisfied';
    $results[]=['program'=>$case['name'],'concentration'=>$case['concentration'],'paths'=>$case['paths'],'status'=>$a['status'],'examined'=>$a['allocationsExamined']??null];
    if (!$ok) {$fail++;echo json_encode(['failed'=>$results[count($results)-1],'reason'=>$a['reason'],'checks'=>array_filter($a['checks']??[],static fn($c)=>$c['status']!=='satisfied')],JSON_PRETTY_PRINT),"\n";}
}
echo json_encode(['cases'=>count($results),'failed'=>$fail,'results'=>$results],JSON_PRETTY_PRINT),"\n";
exit($fail?1:0);
