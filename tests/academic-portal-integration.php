<?php
declare(strict_types=1);
/** Check actual database-backed histories and evaluations, aggregate output only. */
if (PHP_SAPI!=='cli') exit;
ini_set('memory_limit','512M');
require_once __DIR__.'/rehearsal-receipts.php';
require dirname(__DIR__).'/config/database.php';
require dirname(__DIR__).'/includes/academic/rebuild-registrar.php';
require dirname(__DIR__).'/includes/student-schedule.php';
require dirname(__DIR__).'/includes/faculty-schedule.php';
$args=getopt('', ['candidate:','degrees:']);
if (!preg_match('/^northbridge_ow_rebuild_2026_[4-9][0-9]*$/D',$database)) throw new RuntimeException('Isolated rebuilt database required.');
$read=static function($p):array {if (!str_starts_with(realpath($p)?:'','/private/tmp/')) throw new RuntimeException('Private paths required.');return json_decode(file_get_contents($p),true,512,JSON_THROW_ON_ERROR);};
$candidate=$read($args['candidate']);$reviews=$read($args['degrees']);$expected=[];
foreach ($reviews['results'] as $r) $expected[$r['studentId']]=$r;
$registrar=new \Northbridge\Academic\RebuildRegistrar($pdo);
$checked=0;$completed=0;$currentRows=0;$nextRows=0;$degreeMismatches=0;$graduationReady=0;$programs=[];
foreach ($candidate['students'] as $person) {
 $sid=$person['studentId'];$actual=$registrar->evaluateStudent($sid);$want=$expected[$sid];$checked++;
 if ($actual===null || $actual['completedCredits']!=$want['completedCredits']) throw new RuntimeException('Database-backed earned-credit mismatch.');
 foreach (['majorEvaluation','minorEvaluation'] as $kind) {
  if (($actual[$kind]['status']??null)!==($want[$kind]['status']??null) || ($actual[$kind]['totals']??null)!=($want[$kind]['totals']??null)) {
   $degreeMismatches++;
   $label=$person['major'].':'.$kind.':'.($want[$kind]['status']??'none').'->'.($actual[$kind]['status']??'none');
   $differenceCounts[$label]=($differenceCounts[$label]??0)+1;
   if ($kind==='minorEvaluation') {
    $normalized=$registrar->student($sid);$known=array_column(array_filter($normalized['attempts'],static fn($a)=>$a['status']==='completed'),'courseCode');
    $missing=array_diff($want[$kind]['selectedCourses']??[],$actual[$kind]['selectedCourses']??[]);
    $allocationDifferences[$person['minor']??'none']=['candidateCredits'=>$want[$kind]['totals']['programCredits']??null,'databaseCredits'=>$actual[$kind]['totals']['programCredits']??null,'differentCourseCodes'=>array_values($missing),'missingFromCompletedInput'=>array_values(array_diff($missing,$known))];
   }
   foreach ($actual[$kind]['totals']??[] as $key=>$value) if ($value!=($want[$kind]['totals'][$key]??null)) $totalDifferenceFields[$key]=true;
  }
 }
 if ($actual['majorEvaluation']['graduationReady']??false) $graduationReady++;
 $completed+=$actual['completedCredits'];$programs[$person['major']]=true;
 // Exercise both source-aware readers for one student in each program.
 if (!isset($seenPrograms[$person['major']])) {$seenPrograms[$person['major']]=true;$current=student_schedule_meetings($pdo,$sid,'Fall 2026');$next=student_schedule_meetings($pdo,$sid,'Spring 2027');$currentRows+=count($current);$nextRows+=count($next);foreach (array_merge($current,$next) as $row) if (!isset($row['display_CRN'],$row['display_course_ID'])) throw new RuntimeException('Missing official source labels.');}
}
if ($degreeMismatches) {echo json_encode(['mismatchCounts'=>$differenceCounts,'totalDifferenceFields'=>array_keys($totalDifferenceFields??[]),'allocationDiagnostics'=>$allocationDifferences??[],'studentIdentifiersPublished'=>false]),"\n";throw new RuntimeException('Database-backed degree evaluations disagree with candidate evaluations: '.$degreeMismatches);}
$history=(int)$pdo->query("SELECT COUNT(*) FROM Student_History h JOIN Student s ON s.student_ID=h.student_ID WHERE s.student_Type='Undergraduate'")->fetchColumn();
$expectedHistory=count(array_filter($candidate['attempts'],static fn($a)=>$a['status']==='completed'));
if ($history!==$expectedHistory) throw new RuntimeException('Completed-history coverage mismatch.');
$bad=(int)$pdo->query("SELECT COUNT(*) FROM Enrollment e JOIN Student s ON s.student_ID=e.student_ID LEFT JOIN NB_AcademicSection a ON a.CRN=e.CRN WHERE s.student_Type='Undergraduate' AND a.CRN IS NULL")->fetchColumn();
if ($bad) throw new RuntimeException('Legacy undergraduate registrations remain in the active records.');
$summary=['status'=>'actual-database-history-and-degree-checks-passed','studentsEvaluated'=>$checked,'majorsRepresented'=>count($programs),'completedAttemptsVerified'=>$history,'degreeEvaluationMismatches'=>$degreeMismatches,'graduationReady'=>$graduationReady,'currentAndFutureCountedAsCompleted'=>false,'candidateFileSHA256'=>hash_file('sha256',$args['candidate']),'studentIdentifiersPublished'=>false,'database'=>$database,'workingDatabaseChanged'=>false];
file_put_contents(test_receipt_path('portal-degree-verification.json'),json_encode($summary,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
echo json_encode($summary),"\n";
