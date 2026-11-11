<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
require dirname(__DIR__).'/config/database.php';
require dirname(__DIR__).'/includes/academic/test-database.php';
if ($database!=='northbridge_university'||!in_array($host,['127.0.0.1','localhost'],true)) throw new RuntimeException('Expected local working source required.');
umask(0077);
$pdo->exec('SET SESSION TRANSACTION READ ONLY');$pdo->beginTransaction();
$before=academic_legacy_fingerprints($pdo);
$students=$pdo->query('SELECT s.student_ID,s.major_ID,s.student_Type,s.student_Year,m.major_Name FROM Student s JOIN Major m ON m.major_ID=s.major_ID ORDER BY s.student_ID')->fetchAll();
$majors=[];foreach ($pdo->query('SELECT major_ID,major_Name FROM Major') as $m) $majors[(int)$m['major_ID']]=$m['major_Name'];
$minors=[];foreach ($pdo->query('SELECT minor_ID,minor_Name FROM Minor') as $m) $minors[(int)$m['minor_ID']]=$m['minor_Name'];
$declared=[];foreach ($pdo->query('SELECT student_ID,major_ID FROM Student_Major ORDER BY student_ID,major_ID') as $d) $declared[(int)$d['student_ID']][]=(int)$d['major_ID'];
$minorByStudent=[];foreach ($pdo->query('SELECT student_ID,minor_ID FROM Student_Minor ORDER BY student_ID,minor_ID') as $d) $minorByStudent[(int)$d['student_ID']][]=(int)$d['minor_ID'];
$history=[];
foreach ($pdo->query('SELECT student_ID,course_ID,COUNT(*) AS attempts FROM Student_History GROUP BY student_ID,course_ID ORDER BY student_ID,course_ID') as $h) $history[(int)$h['student_ID']][(string)$h['course_ID']]=(int)$h['attempts'];
$enrollment=[];foreach ($pdo->query('SELECT e.student_ID,c.course_ID,COUNT(*) AS rows_present FROM Enrollment e JOIN Course_Section c ON c.CRN=e.CRN GROUP BY e.student_ID,c.course_ID ORDER BY e.student_ID,c.course_ID') as $e) $enrollment[(int)$e['student_ID']][(string)$e['course_ID']]=(int)$e['rows_present'];
$legacyCourses=$pdo->query('SELECT course_ID,course_Name,course_Credits,course_Type FROM Course ORDER BY course_ID')->fetchAll();
$directory='/private/tmp/northbridge-academic-impact-'.bin2hex(random_bytes(6));
if (!mkdir($directory,0700)) throw new RuntimeException('Cannot create private report directory.');
$report=[];$levels=[];$byMajor=[];$withMinors=0;$declarationDifferences=0;
foreach ($students as $s) {
    $id=(int)$s['student_ID'];$graduate=$s['student_Type']==='Graduate';
    $primary=(int)$s['major_ID'];$declarations=$declared[$id]??[];$minorIds=$minorByStudent[$id]??[];
    $hist=$history[$id]??[];$enroll=$enrollment[$id]??[];$courses=array_values(array_unique(array_merge(array_keys($hist),array_keys($enroll))));sort($courses);
    $notes=$graduate?['Graduate curriculum is outside this undergraduate catalog scope; preserve existing graduate program and records.']:['Review program selection individually; a matching title or shared legacy major ID does not approve a new program assignment.','Review every legacy course/version equivalent before computing a new catalog degree result.'];
    if ($minorIds) {$withMinors++;$notes[]='Review all declared minors and their overlap against the individually selected major.';}
    if (!in_array($primary,$declarations,true)) {$declarationDifferences++;$notes[]='Primary Student.major_ID is not present in Student_Major; reconcile declarations without discarding history.';}
    $row=['studentId'=>$id,'level'=>$s['student_Type'],'legacyYear'=>(int)$s['student_Year'],'legacyPrimaryMajor'=>['id'=>$primary,'name'=>$s['major_Name']],
        'legacyDeclaredMajors'=>array_map(static fn($m)=>['id'=>$m,'name'=>$majors[$m]??'Unknown'],$declarations),
        'legacyDeclaredMinors'=>array_map(static fn($m)=>['id'=>$m,'name'=>$minors[$m]??'Unknown'],$minorIds),
        'historyRows'=>array_sum($hist),'enrollmentRows'=>array_sum($enroll),'legacyCourseIdsNeedingMeaningPreservation'=>$courses,
        'historyAttemptsByLegacyCourse'=>$hist,'enrollmentRowsByLegacyCourse'=>$enroll,
        'reviewStatus'=>$graduate?'excluded-graduate-preserve':'individual-program-and-equivalence-review-required',
        'proposedProgramAssignment'=>null,'approvedCourseEquivalencies'=>[],'newCatalogDegreeResult'=>null,'recordsToRewrite'=>0,'reviewNotes'=>$notes];
    $report[]=$row;$levels[$s['student_Type']]=($levels[$s['student_Type']]??0)+1;
    $key=(string)$primary;
    $byMajor[$key]??=['legacyMajorId'=>$primary,'legacyMajorName'=>$s['major_Name'],'undergraduates'=>0,'graduates'=>0];
    $byMajor[$key][$graduate?'graduates':'undergraduates']++;
}
if (count($report)!==(int)$pdo->query('SELECT COUNT(*) FROM Student')->fetchColumn()) throw new RuntimeException('Student report coverage mismatch.');
if (array_sum(array_column($report,'historyRows'))!==(int)$pdo->query('SELECT COUNT(*) FROM Student_History')->fetchColumn()) throw new RuntimeException('Student history coverage mismatch.');
if (array_sum(array_column($report,'enrollmentRows'))!==(int)$pdo->query('SELECT COUNT(*) FROM Enrollment')->fetchColumn()) throw new RuntimeException('Enrollment coverage mismatch.');
if (academic_legacy_fingerprints($pdo)!==$before) throw new RuntimeException('Source fingerprint changed during review.');
$pdo->rollBack();
$private=['status'=>'individual-review-inventory-not-a-migration','createdAt'=>gmdate('c'),'sourceDatabase'=>$database,'targetCatalogId'=>7,'studentCount'=>count($report),'courseEquivalenciesApproved'=>0,'students'=>$report,'legacyCourseDefinitions'=>$legacyCourses,'privacy'=>'No names, emails, credentials, faculty identities or raw individual grades included. Student IDs and academic details stay in this private directory.'];
file_put_contents($directory.'/student-impact.json',json_encode($private,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
$csv=fopen($directory.'/student-impact.csv','w');fputcsv($csv,['Student ID','Level','Legacy primary major','Declared minor count','History rows','Enrollment rows','Distinct legacy course IDs','Review status'],',','"','');
foreach ($report as $row) fputcsv($csv,[$row['studentId'],$row['level'],$row['legacyPrimaryMajor']['name'],count($row['legacyDeclaredMinors']),$row['historyRows'],$row['enrollmentRows'],count($row['legacyCourseIdsNeedingMeaningPreservation']),$row['reviewStatus']],',','"','');fclose($csv);
$summary=['status'=>'per-student-review-inventory-created-no-mapping-approved','verifiedOn'=>gmdate('Y-m-d'),'studentRowsReviewed'=>count($report),'studentsByLevel'=>$levels,'studentsWithDeclaredMinors'=>$withMinors,'primaryDeclarationDifferences'=>$declarationDifferences,'historyRowsAccountedFor'=>array_sum(array_column($report,'historyRows')),'enrollmentRowsAccountedFor'=>array_sum(array_column($report,'enrollmentRows')),'legacyCoursesPreserved'=>count($legacyCourses),'legacyMajorImpact'=>array_values($byMajor),'approvedStudentProgramChanges'=>0,'approvedCourseEquivalencies'=>0,'degreeVerdictsIssued'=>0,'databaseWritesPerformed'=>0,'sourceTablesVerifiedUnchanged'=>count($before),'sourceFingerprintSHA256'=>hash('sha256',json_encode($before,JSON_THROW_ON_ERROR)),'privateReportDirectory'=>$directory,'detailedReportSHA256'=>hash_file('sha256',$directory.'/student-impact.json'),'limitations'=>['An inventory of every student, not a completed academic equivalence review or migration approval.','Existing fictional academic histories cannot be evaluated under new meanings until individual program and course mappings are reviewed.','Graduate students remain outside the undergraduate migration; do not rename shared major IDs.','Private report directory is temporary local storage, not a durable backup.']];
file_put_contents(dirname(__DIR__).'/database/staging/academic-alignment/student-impact-summary.json',json_encode($summary,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
echo json_encode(['status'=>$summary['status'],'students'=>count($report),'levels'=>$levels,'historyRows'=>$summary['historyRowsAccountedFor'],'enrollmentRows'=>$summary['enrollmentRowsAccountedFor'],'legacyTablesUnchanged'=>count($before),'privateReportDirectory'=>$directory,'databaseWritesPerformed'=>0],JSON_PRETTY_PRINT),"\n";
