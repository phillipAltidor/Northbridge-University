<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/academic/rebuild-prerequisites.php';
use Northbridge\Academic\RebuildPrerequisiteEvaluator;
$rule=['type'=>'proficiency','key'=>'MATH_PROFICIENCY'];$checks=0;
$check=static function(string $code,string $grade,string $status,int $term,string $want)use($rule,&$checks):void{
 $student=['attempts'=>[['courseCode'=>$code,'catalogVersion'=>'2026-2027','credits'=>4,'grade'=>$grade?:null,'status'=>$status,'origin'=>'local','termOrder'=>$term,'attemptOrder'=>1]]];
 $actual=(new RebuildPrerequisiteEvaluator($student,[],202609,'CP2800'))->evaluate($rule)['status'];
 if($actual!==$want)throw new RuntimeException('Math proficiency boundary failed.');$checks++;
};
$source=json_decode(file_get_contents(dirname(__DIR__).'/database/staging/academic-alignment/rules/general-education-mappings.json'),true,512,JSON_THROW_ON_ERROR);
$codes=[];foreach($source['rows']as$row)if(str_contains($row['currentApprovalRaw']??'','Math Proficiency'))$codes[$row['sourceCourseCode']]=true;
if(count($codes)!==9)throw new RuntimeException('Re-review changed math proficiency source.');
foreach(array_keys($codes)as$code){$check($code,'C','completed',202602,'satisfied');$check($code,'C-','completed',202602,'unsatisfied');$check($code,'','in-progress',202602,'conditional');$check($code,'A','completed',202609,'unsatisfied');}
$check('MA3250','A','completed',202602,'unsatisfied');
echo json_encode(['mathProficiencyBoundaryChecks'=>$checks,'sourceListedRoutes'=>count($codes)]),"\n";
