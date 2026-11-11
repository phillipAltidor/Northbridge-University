<?php
declare(strict_types=1);
/** Aggregate-only advising readiness for the configured current/next semesters. */
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/config/database.php';
$timeline=require dirname(__DIR__).'/config/academic-timeline.php';
$terms=[$timeline['current_semester'],$timeline['next_semester']];
$fingerprint=static function() use ($pdo):array {
    $out=[];
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if (!preg_match('/^[A-Za-z0-9_]+$/D',$table)) throw new RuntimeException('Unexpected identifier.');
        $out[$table]=$pdo->query("CHECKSUM TABLE `$table` EXTENDED")->fetch()['Checksum'];
    }
    return $out;
};
$before=$fingerprint();
$q=$pdo->prepare("SELECT COUNT(*) registered,COALESCE(SUM(NOT EXISTS(SELECT 1 FROM Advisor a WHERE a.student_ID=s.student_ID)),0) missing FROM Student s WHERE s.student_Type='Undergraduate' AND EXISTS(SELECT 1 FROM Enrollment e JOIN Semester term ON term.semester_ID=e.semester_ID WHERE e.student_ID=s.student_ID AND term.semester_Name IN (?,?))");
$q->execute($terms);$coverage=$q->fetch();
$checks=[
    'currentNextUndergraduatesWithoutAdvisor'=>(int)$coverage['missing'],
    'advisorsOverFifteenAdvisees'=>(int)$pdo->query('SELECT COUNT(*) FROM (SELECT faculty_ID FROM Advisor GROUP BY faculty_ID HAVING COUNT(DISTINCT student_ID)>15) x')->fetchColumn(),
    'partTimeFacultyWithAdvisees'=>(int)$pdo->query("SELECT COUNT(DISTINCT a.faculty_ID) FROM Advisor a JOIN Faculty f ON f.faculty_ID=a.faculty_ID WHERE f.faculty_Type<>'Full-Time'")->fetchColumn(),
    'undergraduateAdvisorsOutsideMajorDepartment'=>(int)$pdo->query("SELECT COUNT(*) FROM Advisor a JOIN Student s ON s.student_ID=a.student_ID JOIN Major m ON m.major_ID=s.major_ID WHERE s.student_Type='Undergraduate' AND NOT EXISTS(SELECT 1 FROM Faculty_Department d WHERE d.faculty_ID=a.faculty_ID AND d.dept_ID=m.dept_ID)")->fetchColumn(),
];
$missing=(int)$pdo->query("SELECT COUNT(*) FROM Student s WHERE s.student_Type='Undergraduate' AND NOT EXISTS(SELECT 1 FROM Advisor a WHERE a.student_ID=s.student_ID)")->fetchColumn();
$checks['auditChangedDatabase']=$before===$fingerprint()?0:1;
$failures=array_filter($checks,static fn($n)=>$n!==0);
echo json_encode(['status'=>$failures?'advisor-readiness-failed':'advisor-readiness-passed','database'=>$database,'semesters'=>$terms,'registeredUndergraduates'=>(int)$coverage['registered'],'registeredUndergraduatesWithAdvisor'=>(int)$coverage['registered']-(int)$coverage['missing'],'undergraduatesWithoutAdvisorOutsideCurrentNextRegistrations'=>$missing-(int)$coverage['missing'],'checks'=>count($checks),'failures'=>$failures,'databaseChanged'=>$checks['auditChangedDatabase']!==0,'scopeNote'=>'No registration in these terms does not prove graduation or formal inactive status. Historical-only advisor policy and graduate provisioning are outside this check.'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
exit($failures?1:0);
