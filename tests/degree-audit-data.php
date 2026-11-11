<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit;
ini_set('memory_limit','512M');
require_once __DIR__.'/rehearsal-receipts.php';
require dirname(__DIR__).'/config/database.php';
require dirname(__DIR__).'/includes/auth.php';
require dirname(__DIR__).'/includes/academic/degree-audit.php';
require dirname(__DIR__).'/includes/academic/degree-worksheet.php';
$fingerprints=static function()use($pdo):array {$out=[];foreach (['User','Login','Student','Student_Major','Student_Minor','Enrollment','Student_History','Course_Section','NB_AcademicStudent'] as $table) $out[$table]=$pdo->query("CHECKSUM TABLE `$table` EXTENDED")->fetch()['Checksum'];return $out;};
$before=$fingerprints();$service=new \Northbridge\Academic\DegreeAuditService($pdo);$registrar=new \Northbridge\Academic\RebuildRegistrar($pdo);
$ids=$pdo->query("SELECT student_ID FROM Student WHERE student_Type='Undergraduate' ORDER BY student_ID")->fetchAll(PDO::FETCH_COLUMN);
$majors=[];$minorNames=[];$checked=0;$notices=0;$rendered=0;
$gatewayVerified=false;
foreach ($ids as $sid) {
    $audit=$service->audit((int)$sid);$expected=$registrar->evaluateStudent((int)$sid);
    if (!$audit || $audit['availability']!=='available') throw new RuntimeException('Undergraduate audit unavailable.');
    if ($audit['earnedCredits']!=$expected['completedCredits'] || $audit['majors'][0]['evaluation']['status']!==$expected['majorEvaluation']['status'] || ($audit['majors'][0]['evaluation']['totals']??null)!=($expected['majorEvaluation']['totals']??null)) throw new RuntimeException('Live primary degree audit differs from verified registrar evaluation.');
    if ($audit['minors'] && (($audit['minors'][0]['evaluation']['status']??null)!==($expected['minorEvaluation']['status']??null) || ($audit['minors'][0]['evaluation']['totals']??null)!=($expected['minorEvaluation']['totals']??null))) throw new RuntimeException('Live minor audit differs from verified registrar evaluation.');
    $current=0;$planned=0;
    foreach ($audit['attempts'] as $a) {
        if ($a['phase']==='planned') {$planned+=$a['credits'];if ($a['grade']!==null) throw new RuntimeException('Planned course has a final grade.');}
        if ($a['phase']==='in-progress') {$current+=$a['credits'];if ($a['grade']!==null) throw new RuntimeException('Current course has a final grade.');}
    }
    if ($current!=$audit['inProgressCredits'] || $planned!=$audit['plannedCredits']) throw new RuntimeException('Course phases are mixed.');
    $majors[$audit['majors'][0]['name']]=true;foreach ($audit['minors'] as $minor) $minorNames[$minor['name']]=true;
    $notices+=count($audit['progression']);
    if (!$gatewayVerified && $audit['majors'][0]['name']==='Computer and Information Science, B.S.' && $audit['earnedCredits']==0) {
        foreach ($audit['attempts'] as $a) if ($a['courseCode']==='MA1020' && $a['phase']==='planned' && $a['termOrder']===$audit['nextTerm']) foreach ($audit['progression'] as $notice) foreach ($notice['courses'] as $c) if ($c['courseCode']==='CS2510') {
            if ($c['prerequisite']['status']!=='unsatisfied' || !str_contains(\Northbridge\Academic\audit_prerequisite_text($c['rule']),'MA1020')) throw new RuntimeException('Future same-term algebra cannot satisfy the programming prerequisite.');
            $gatewayVerified=true;
        }
    }
    if (!$gatewayVerified && $audit['majors'][0]['name']==='Computer and Information Science, B.S.' && $audit['earnedCredits']==0) {
        foreach ($audit['attempts'] as $a) if ($a['courseCode']==='MA1020' && $a['phase']==='in-progress' && $a['termOrder']===$audit['currentTerm']) foreach ($audit['progression'] as $notice) foreach ($notice['courses'] as $c) if ($c['courseCode']==='CS2510') {
            if ($c['prerequisite']['status']!=='conditional') throw new RuntimeException('Current algebra must leave future programming conditional.');
            $gatewayVerified=true;
        }
    }
    ob_start();\Northbridge\Academic\worksheet_render($audit,$service);$html=ob_get_clean();
    if (!str_contains($html,'data-audit-student="'.$sid.'"') || !str_contains($html,'Upper Division Requirement') || !str_contains($html,'General Education Requirements') || preg_match('/(?:Warning|Fatal error|Notice|Deprecated):/',$html)) throw new RuntimeException('Audit rendering incomplete.');
    $rendered++;$checked++;
}
if ($checked!==count($ids) || count($majors)!==12 || !$gatewayVerified) throw new RuntimeException('Incomplete undergraduate coverage or missing programming progression case.');
$graduate=(int)$pdo->query("SELECT student_ID FROM Student WHERE student_Type='Graduate' LIMIT 1")->fetchColumn();
if ($service->audit($graduate)['availability']!=='graduate-not-configured') throw new RuntimeException('Graduate rules must not be inferred.');
if ($service->audit(-1)!==null) throw new RuntimeException('Unknown student must not return another audit.');
if ($fingerprints()!==$before) throw new RuntimeException('Read-only audit changed database records.');
$summary=['status'=>'all-undergraduate-live-degree-audits-verified','studentsAudited'=>$checked,'auditsRendered'=>$rendered,'majorsCovered'=>count($majors),'minorsRepresented'=>count($minorNames),'advisingNotices'=>$notices,'currentAndPlannedCreditsKeptSeparate'=>true,'databaseWrites'=>false,'graduateScope'=>'not-configured','checkedDateNewYork'=>(new DateTimeImmutable('now',new DateTimeZone('America/New_York')))->format('Y-m-d')];
file_put_contents(test_receipt_path('student-degree-audit-verification.json'),json_encode($summary,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");echo json_encode($summary),"\n";
