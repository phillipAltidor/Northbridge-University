<?php
declare(strict_types=1);
/** Compare portal PHP registration checks with the independent Python review. */
if (PHP_SAPI!=='cli') exit;
ini_set('memory_limit','512M');
require dirname(__DIR__).'/includes/academic/rebuild-prerequisites.php';
$args=getopt('', ['candidate:','rules:','review:','output:']);
$read=static function(string $path):array {if (!str_starts_with(realpath($path)?:'','/private/tmp/')) throw new RuntimeException('Private paths required.');return json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);};
$candidate=$read($args['candidate']);$rules=$read($args['rules']);$review=$read($args['review']);
if (($review['summary']['candidateFileSHA256']??'')!==hash_file('sha256',$args['candidate'])) throw new RuntimeException('Independent review does not match the candidate.');
if (($review['summary']['effectiveRulesFileSHA256']??'')!==hash_file('sha256',$args['rules'])) throw new RuntimeException('Rules do not match the independent review.');
$histories=[];foreach ($candidate['attempts'] as $a) $histories[$a['studentId']][]=$a;
$people=[];foreach ($candidate['students'] as $p) $people[$p['studentId']]=$p;
$expected=[];foreach ($review['results'] as $r) $expected[$r['studentId'].':'.$r['termCode'].':'.$r['courseKey']]=$r['result']['status'];
$properties=[];foreach ($candidate['sourceLaboratoryScienceEvidence'] as $code=>$_) $properties[$code]=['labBearing'=>true];
$checks=0;$mismatches=[];
foreach ($people as $sid=>$person) {
 $attempts=[];
 foreach ($histories[$sid]??[] as $i=>$a) {$c=$candidate['courses'][$a['courseKey']];$attempts[]=['courseCode'=>$c['courseCode'],'catalogVersion'=>$c['catalogVersion'],'credits'=>$c['credits'],'status'=>$a['status']==='planned'?'in-progress':$a['status'],'grade'=>$a['grade'],'termOrder'=>(int)$a['termCode'],'attemptOrder'=>$i,'origin'=>'local'];}
 $student=$person+['program'=>$person['major'],'attempts'=>$attempts];
 foreach ($histories[$sid]??[] as $a) {
  $engine=new \Northbridge\Academic\RebuildPrerequisiteEvaluator($student,$properties,(int)$a['termCode'],$a['courseKey']);
  $actual=$engine->evaluate($rules[$a['courseKey']]??['type'=>'review-required'])['status'];
  $want=$expected[$sid.':'.$a['termCode'].':'.$a['courseKey']];$checks++;
  if ($actual!==$want) {$key=$a['courseKey'].':'.$want.'->'.$actual;$mismatches[$key]=($mismatches[$key]??0)+1;}
 }
}
$receipt=['attemptsCompared'=>$checks,'mismatches'=>$mismatches,'studentIdentifiersPublished'=>false,'candidateFileSHA256'=>hash_file('sha256',$args['candidate']),'effectiveRulesFileSHA256'=>hash_file('sha256',$args['rules']),'reviewFileSHA256'=>hash_file('sha256',$args['review'])];
if(isset($args['output'])){if(!str_starts_with(realpath(dirname($args['output'])).'/','/private/tmp/'))throw new RuntimeException('Private receipt path required.');umask(0077);file_put_contents($args['output'],json_encode($receipt,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");}
echo json_encode($receipt),"\n";
if ($mismatches || $checks!==count($candidate['attempts'])) exit(1);
