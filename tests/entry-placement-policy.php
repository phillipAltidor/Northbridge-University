<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/academic/rebuild-prerequisites.php';
use Northbridge\Academic\RebuildPrerequisiteEvaluator;
$policy=json_decode(file_get_contents(dirname(__DIR__).'/database/staging/academic-alignment/ma1020-entry-placement-policy.json'),true,512,JSON_THROW_ON_ERROR);
$scope='placement:MA1020-entry';
$student=['studentId'=>1,'attempts'=>[],'registrarEvidence'=>[$scope=>['status'=>'approved','scope'=>$scope,'studentId'=>1,'termOrder'=>202609,'authority'=>'northbridge-admissions-simulation','approvalId'=>'TEST-FICTIONAL-PLACEMENT','approvedBy'=>'Northbridge admission simulation','approvedAt'=>'2026-11-08','fictionalSimulation'=>true]]];
$check=static function(array $s,string $key,array $rule,string $expected)use(&$count):void{$actual=(new RebuildPrerequisiteEvaluator($s,[],202609,$key))->evaluate($rule)['status'];if($actual!==$expected)throw new RuntimeException($key.': expected '.$expected.', got '.$actual);$count++;};
$count=0;$rule=$policy['rule'];$cs=['type'=>'course','courseCode'=>'MA1020'];
$check($student,'MA1020',$rule,'satisfied');
$check($student,'CS2510',$cs,'unsatisfied');
$without=$student;unset($without['registrarEvidence']);$check($without,'MA1020',$rule,'review-required');
$wrong=$student;$wrong['registrarEvidence'][$scope]['studentId']=2;$check($wrong,'MA1020',$rule,'review-required');
$future=$student;$future['registrarEvidence'][$scope]['termOrder']=202702;$check($future,'MA1020',$rule,'review-required');
$pending=$student;$pending['attempts']=[['courseCode'=>'MA1020','catalogVersion'=>'2026-2027','status'=>'in-progress','grade'=>null,'credits'=>4,'origin'=>'local','termOrder'=>202602,'attemptOrder'=>1]];$check($pending,'CS2510',$cs,'conditional');
$passed=$pending;$passed['attempts'][0]['status']='completed';$passed['attempts'][0]['grade']='C';$check($passed,'CS2510',$cs,'satisfied');
$failed=$passed;$failed['attempts'][0]['grade']='F';$check($failed,'CS2510',$cs,'unsatisfied');
echo json_encode(['status'=>'staged-entry-placement-boundaries-passed','checks'=>$count,'databaseChanged'=>false,'schemaChanged'=>false]),"\n";
