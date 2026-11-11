<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/config/database.php';
require dirname(__DIR__).'/includes/academic/preview.php';
require dirname(__DIR__).'/includes/academic/test-database.php';
$root=dirname(__DIR__).'/database/staging/academic-alignment/';$receipt=json_decode(file_get_contents($root.'mapping-review-receipt.json'),true,512,JSON_THROW_ON_ERROR);
$input=json_decode(file_get_contents($receipt['privateDirectory'].'/student-inputs.json'),true,512,JSON_THROW_ON_ERROR);
$academic=json_decode(file_get_contents($root.'legacy-academic-review-inputs.json'),true,512,JSON_THROW_ON_ERROR);
$review=json_decode(file_get_contents($root.'legacy-mapping-review.json'),true,512,JSON_THROW_ON_ERROR);
$before=academic_legacy_fingerprints($pdo);$db=\Northbridge\Academic\preview_database();$package=\Northbridge\Academic\preview_package($db);$repo=$package['repository'];$copyBefore=academic_legacy_fingerprints($db);
$programs=$repo->programs();$metadata=$repo->courseMetadata();$institutional=$repo->policy('institutional-rules');$engine=new \Northbridge\Academic\CurriculumEvaluator($metadata,$programs);
$courseMap=[];foreach($review['courses'] as $c)$courseMap[$c['legacyCourseCode']]=$c;
$programMap=[];foreach($review['programs'] as $p)$programMap[$p['kind']][(int)$p['legacyId']]=$p;
$termOrder=[];foreach($academic['terms'] as $t){preg_match('/^(Spring|Fall) (\d{4})$/',$t['semester_Name'],$m);$termOrder[(int)$t['semester_ID']]=((int)$m[2])*10+($m[1]==='Spring'?1:2);}
$history=[];$enrollment=[];$minors=[];$majors=[];
foreach($input['history'] as $h)$history[(int)$h['student_ID']][]=$h;
foreach($input['enrollment'] as $e)$enrollment[(int)$e['student_ID']][]=$e;
foreach($input['declaredMinors'] as $m)$minors[(int)$m['student_ID']][]=(int)$m['minor_ID'];
foreach($input['declaredMajors'] as $m)$majors[(int)$m['student_ID']][]=(int)$m['major_ID'];
$auditCount=0;$undergraduateCount=0;$graduateCount=0;$minorDeclarations=0;$gradeConflicts=0;$collisions=0;$engineStatuses=[];
umask(0077);
$written=0;$stream=fopen($receipt['privateDirectory'].'/student-mapping-projections.json.part','w');
fwrite($stream,'{"status":"planning-assumptions-not-verified-degree-audits","asOf":"2026-11-07","catalogId":7,"students":['."\n");
$csv=fopen($receipt['privateDirectory'].'/student-mapping-projections.csv.part','w');fputcsv($csv,['Student ID','Level','Status','Legacy major','Projected attempts','Unmapped legacy courses','Candidate program/path evaluations','Official degree verdict'],',','"','');
$writeRow=static function(array $row)use($stream,$csv,&$written):void{
 if($written)fwrite($stream,",\n");fwrite($stream,json_encode($row,JSON_THROW_ON_ERROR));$written++;
 fputcsv($csv,[$row['studentId'],$row['level'],$row['status'],$row['legacyPrimaryMajorId'],$row['projectedAttemptCount']??0,count($row['unmappedLegacyCourseIdsPreserved']??[]),count($row['candidateAudits']??[]),'Not issued'],',','"','');
};
foreach($input['students'] as $s){
 $id=(int)$s['student_ID'];$graduate=$s['student_Type']==='Graduate';
 if($graduate){$graduateCount++;$writeRow(['studentId'=>$id,'level'=>'Graduate','status'=>'excluded-graduate-preserve','legacyPrimaryMajorId'=>(int)$s['major_ID'],'recordsChanged'=>0,'verifiedDegreeResult'=>null]);continue;}
 $undergraduateCount++;$attempts=[];$unmapped=[];$assumptions=[];$seen=[];$conflicts=[];$targets=[];
 foreach($history[$id]??[] as $h){$key=$h['semester_ID'].':'.$h['CRN'];$seen[$key]=$h;}
 foreach($enrollment[$id]??[] as $e){$key=$e['semester_ID'].':'.$e['CRN'];if(isset($seen[$key])){if($seen[$key]['Grade']!==$e['Grade']||$seen[$key]['course_ID']!==$e['course_ID'])$conflicts[]=$key;continue;}$seen[$key]=$e;}
 foreach($seen as $h){
  $legacy=$h['course_ID'];$candidate=$courseMap[$legacy]??null;$target=$candidate['planningScenarioTarget']??null;
  if(!$target){$unmapped[$legacy]=true;continue;}
  $targets[$target][$legacy]=true;
  $status=$h['Grade']===null?'in-progress':'completed';
  $attempts[]=['courseCode'=>$target,'status'=>$status,'grade'=>$h['Grade'],'credits'=>$candidate['legacyCredits'],'origin'=>'local','termOrder'=>$termOrder[(int)$h['semester_ID']],'attemptOrder'=>(int)$h['CRN'],'catalogVersion'=>'2026-2027'];
  $assumptions[$legacy]=$target;
 }
 $collisionTargets=array_keys(array_filter($targets,static fn($x)=>count($x)>1));
 if($collisionTargets)$attempts=array_values(array_filter($attempts,static fn($a)=>!in_array($a['courseCode'],$collisionTargets,true)));
 $gradeConflicts+=count($conflicts);$collisions+=count($collisionTargets);
 $student=['attempts'=>$attempts,'proficiencies'=>[],'approvals'=>[]];
 $audits=[];$programReviews=[];
 foreach(array_unique(array_merge([(int)$s['major_ID']],$majors[$id]??[])) as $major){$programReviews[]=$programMap['major'][$major];}
 foreach($minors[$id]??[] as $minor){$programReviews[]=$programMap['minor'][$minor];$minorDeclarations++;}
 foreach($programReviews as $programReview) foreach($programReview['candidatePrograms'] as $name){
  $kind=$programReview['kind'];$matches=array_values(array_filter($programs,static fn($p)=>$p['program']===$name&&$p['kind']===$kind));if(!$matches)throw new RuntimeException('Candidate program outside reviewed catalog.');$program=$matches[0];
  foreach(array_keys($program['concentrations']??['none'=>[]]) as $concentration){
   $context=$student;if($concentration!=='none')$context['concentration']=$concentration;
   // This is an explicit planning hypothesis, not an approved course identity or student assignment.
   $audit=$engine->evaluate($program,$context,$institutional);$auditCount++;$engineStatuses[$audit['status']]=($engineStatuses[$audit['status']]??0)+1;
   if($audit['graduationReady']??false)throw new RuntimeException('Mapping-limited input must not produce a verified completion.');
   $audits[]=['candidateProgram'=>$name,'kind'=>$kind,'concentration'=>$concentration==='none'?null:$concentration,'assumptionScope'=>'Unapproved program/course projections only; omitted legacy credits remain preserved and unevaluated. No GE/attribute/proficiency/overlap approvals invented.','engineResultUnderAssumptions'=>$audit,'interpretation'=>'Incomplete mapping coverage is not a student failure or a final graduation deficit.','verifiedDegreeResult'=>null];
  }
 }
 $writeRow(['studentId'=>$id,'level'=>'Undergraduate','status'=>'mapping-limited-diagnostic-only','legacyPrimaryMajorId'=>(int)$s['major_ID'],'declaredLegacyMajorIds'=>$majors[$id]??[],'declaredLegacyMinorIds'=>$minors[$id]??[],'programMappingReviews'=>$programReviews,'deduplicatedLegacyAttemptCount'=>count($seen),'projectedAttemptCount'=>count($attempts),'unapprovedCourseProjectionAssumptions'=>$assumptions,'unmappedLegacyCourseIdsPreserved'=>array_keys($unmapped),'conflictingHistoryEnrollmentAttempts'=>$conflicts,'differentLegacyCoursesCollidingOnTarget'=>$collisionTargets,'candidateAudits'=>$audits,'verifiedDegreeResult'=>null,'approvedProgramAssignment'=>null,'approvedEquivalencies'=>[],'recordsChanged'=>0]);
 if($undergraduateCount%500===0)echo 'Reviewed undergraduate records: ',$undergraduateCount,"\n";
}
if($written!==count($input['students']))throw new RuntimeException('Student coverage incomplete.');
if(academic_legacy_fingerprints($pdo)!==$before||academic_legacy_fingerprints($db)!==$copyBefore)throw new RuntimeException('Working/copy fingerprints changed during projection.');$db->rollBack();
fwrite($stream,"\n".'],"studentCount":'.$written.'}');fclose($stream);fclose($csv);
rename($receipt['privateDirectory'].'/student-mapping-projections.json.part',$receipt['privateDirectory'].'/student-mapping-projections.json');
rename($receipt['privateDirectory'].'/student-mapping-projections.csv.part',$receipt['privateDirectory'].'/student-mapping-projections.csv');
$summary=['status'=>'all-student-program-mapping-review-with-limited-diagnostics','asOf'=>'2026-11-07','studentRecordsReviewed'=>$written,'undergraduatesReviewed'=>$undergraduateCount,'graduatesExcluded'=>$graduateCount,'undergraduateMinorDeclarationsReviewed'=>$minorDeclarations,'candidateProgramPathEvaluations'=>$auditCount,'engineStatusesUnderAssumptions'=>$engineStatuses,'historyEnrollmentGradeOrCourseConflicts'=>$gradeConflicts,'targetCourseCollisions'=>$collisions,'approvedProgramAssignments'=>0,'approvedCourseEquivalencies'=>0,'verifiedStudentDegreeVerdicts'=>0,'workingRecordsChanged'=>0,'legacyTablesUnchanged'=>count($before),'privateDirectory'=>$receipt['privateDirectory'],'detailedProjectionSHA256'=>hash_file('sha256',$receipt['privateDirectory'].'/student-mapping-projections.json'),'limitations'=>['29 explicit topic/credit assumptions support limited diagnostics only; they are not approved equivalent courses.','Unmapped courses retain original meanings and grades; omissions cannot be read as lost credit or final graduation deficits.','GE, course attributes, English proficiency, transfer/admission status and overlap require actual reviewed student evidence.','No grade, program assignment, course identity or graduation verdict is written.']];
file_put_contents($root.'all-student-mapping-review-summary.json',json_encode($summary,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");echo json_encode($summary,JSON_PRETTY_PRINT),"\n";
