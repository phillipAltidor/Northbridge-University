<?php
declare(strict_types=1);
/** Store a blocked, immutable candidate in the isolated rebuild database only. */
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
require dirname(__DIR__).'/config/database.php';
$args=getopt('', ['candidate:', 'schedule:']);$candidatePath=realpath($args['candidate']??'');
if (!$candidatePath || !str_starts_with($candidatePath,'/private/tmp/')) throw new RuntimeException('Private candidate path required.');
if (!in_array($host,['127.0.0.1','localhost'],true) || $database!=='northbridge_university') throw new RuntimeException('Expected local portal configuration.');
$read=static fn(string $p):array=>json_decode(file_get_contents($p),true,512,JSON_THROW_ON_ERROR);
$root=dirname(__DIR__);$reportPath=$root.'/database/staging/academic-alignment/rebuild-candidate-audit.json';
$report=$read($reportPath);$candidate=$read($candidatePath);$hash=hash_file('sha256',$candidatePath);
if (($report['candidateFileSHA256']??null)!==$hash || ($report['status']??null)!=='blocked-from-activation' || ($candidate['status']??null)!=='generated-not-applied') throw new RuntimeException('An exact, blocked candidate audit is required.');
if (!empty($report['structuralErrors']) || !empty($candidate['underloadGroups'])) throw new RuntimeException('Fix structural errors before staging.');
$schedule=null;$scheduleHash=null;
if (isset($args['schedule'])) {
    $schedulePath=realpath($args['schedule']);
    if (!$schedulePath || !str_starts_with($schedulePath,'/private/tmp/')) throw new RuntimeException('Private schedule path required.');
    $schedule=$read($schedulePath);$scheduleHash=hash_file('sha256',$schedulePath);
    if (($report['scheduleFileSHA256']??null)!==$scheduleHash || ($schedule['candidateSHA256']??null)!==($report['candidateSHA256']??null) || ($schedule['status']??null)!=='candidate-scheduled'
        || !empty($report['scheduleValidationErrors']) || in_array('complete-student-faculty-room-schedule-not-verified',$report['activationBlockers'],true)
        || count($report['independentlyCheckedScheduleTerms'])!==count($candidate['rosters'])) throw new RuntimeException('An exact, independently verified full schedule is required.');
}
$target='northbridge_ow_rebuild_2026';
$test=new PDO('mysql:host='.$host.';port='.$port.';dbname='.$target.';charset=utf8mb4',$username,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$fingerprints=static function(PDO $db):array {
    $tables=$db->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_NUM);$out=[];
    foreach ($tables as $row) {
        $t=$row[0];if (in_array($t,['NB_RebuildDataset','NB_RebuildStudentHistory','NB_RebuildCourseVersion','NB_RebuildSchedule','NB_RebuildSection'],true)) continue;
        if (!preg_match('/^[A-Za-z0-9_]+$/',$t)) throw new RuntimeException('Invalid table identifier.');
        $out[$t]=[(int)$db->query('SELECT COUNT(*) FROM `'.$t.'`')->fetchColumn(),$db->query('CHECKSUM TABLE `'.$t.'` EXTENDED')->fetch()['Checksum']];
    }
    ksort($out);return $out;
};
$before=$fingerprints($test);$workingBefore=$fingerprints($pdo);
$byStudent=[];foreach ($candidate['attempts'] as $attempt) $byStudent[$attempt['studentId']][]=$attempt;
$allowed=array_fill_keys($test->query("SELECT student_ID FROM Student WHERE student_Type='Undergraduate'")->fetchAll(PDO::FETCH_COLUMN),true);
$seen=[];foreach ($candidate['students'] as $person) {
    $id=$person['studentId'];if (!isset($allowed[$id]) || isset($seen[$id])) throw new RuntimeException('Invalid undergraduate population.');$seen[$id]=true;
}
if (count($seen)!==count($allowed)) throw new RuntimeException('Incomplete undergraduate replacement population.');
$schema=file_get_contents($root.'/database/migrations/2026-11-07-rebuild-candidates.sql');
foreach (explode(';',$schema) as $statement) if (trim($statement)!=='') $test->exec($statement);
$exists=$test->prepare('SELECT studentCount,attemptCount,courseVersionCount FROM NB_RebuildDataset WHERE datasetSHA256=?');$exists->execute([$hash]);
if ($exists->fetch()) throw new RuntimeException('This immutable candidate is already staged; refusing to overwrite.');
$encode=static fn(array $x):string=>json_encode($x,JSON_THROW_ON_ERROR|JSON_PRESERVE_ZERO_FRACTION);
try {
    $test->beginTransaction();
    $batch=$test->prepare('INSERT INTO NB_RebuildDataset (datasetSHA256,datasetStatus,auditJSON,studentCount,attemptCount,courseVersionCount) VALUES (?,?,?,?,?,?)');
    $batch->execute([$hash,'blocked-from-activation',$encode($report),count($candidate['students']),count($candidate['attempts']),count($candidate['courses'])]);
    $history=$test->prepare('INSERT INTO NB_RebuildStudentHistory VALUES (?,?,?,?)');
    foreach ($candidate['students'] as $person) $history->execute([$hash,$person['studentId'],$encode($person),$encode($byStudent[$person['studentId']]??[])]);
    $course=$test->prepare('INSERT INTO NB_RebuildCourseVersion VALUES (?,?,?)');
    foreach ($candidate['courses'] as $key=>$row) $course->execute([$hash,$key,$encode($row)]);
    if ($schedule!==null) {
        $q=$test->prepare('INSERT INTO NB_RebuildSchedule (scheduleSHA256,datasetSHA256,scheduleStatus,termCount,sectionCount,auditJSON) VALUES (?,?,?,?,?,?)');
        $q->execute([$scheduleHash,$hash,'verified-known-meeting-times-only',count($schedule['scheduledTerms']),count($schedule['sections']),$encode($report)]);
        $section=$test->prepare('INSERT INTO NB_RebuildSection VALUES (?,?,?,?,?,?)');
        foreach ($schedule['sections'] as $row) $section->execute([$scheduleHash,$hash,$row['term'],$row['courseKey'],$row['deliveryGroup'],$encode($row)]);
        $q=$test->prepare("SELECT COUNT(*),COUNT(DISTINCT semesterCode),SUM(JSON_LENGTH(proposedSectionJSON,'$.studentIds')) FROM NB_RebuildSection WHERE scheduleSHA256=?");
        $q->execute([$scheduleHash]);$scheduled=$q->fetch(PDO::FETCH_NUM);
        if ((int)$scheduled[0]!==count($schedule['sections']) || (int)$scheduled[1]!==count($candidate['rosters']) || (int)$scheduled[2]!==count($candidate['attempts'])) throw new RuntimeException('Schedule round-trip count mismatch.');
    }
    $q=$test->prepare('SELECT COUNT(*),SUM(JSON_LENGTH(proposedAttemptsJSON)) FROM NB_RebuildStudentHistory WHERE datasetSHA256=?');$q->execute([$hash]);$verified=$q->fetch(PDO::FETCH_NUM);
    $q=$test->prepare('SELECT COUNT(*) FROM NB_RebuildCourseVersion WHERE datasetSHA256=?');$q->execute([$hash]);
    if ((int)$verified[0]!==count($candidate['students']) || (int)$verified[1]!==count($candidate['attempts']) || (int)$q->fetchColumn()!==count($candidate['courses'])) throw new RuntimeException('Candidate round-trip count mismatch.');
    if ($fingerprints($test)!==$before || $fingerprints($pdo)!==$workingBefore) throw new RuntimeException('Protected table changed during staging.');
    $test->commit();
} catch (Throwable $e) {if ($test->inTransaction()) $test->rollBack();throw $e;}
$receipt=['status'=>'blocked-candidate-staged-for-review','targetDatabase'=>$target,'datasetSHA256'=>$hash,'students'=>count($candidate['students']),'attempts'=>count($candidate['attempts']),'courseVersions'=>count($candidate['courses']),'protectedTestTablesVerified'=>count($before),'workingPortalTablesVerified'=>count($workingBefore),'originalTestTablesUnchanged'=>true,'workingDatabaseChanged'=>false,'portalConfigurationChanged'=>false,'activationPermitted'=>false];
if ($schedule!==null) $receipt+=['scheduleSHA256'=>$scheduleHash,'scheduledTerms'=>count($schedule['scheduledTerms']),'scheduledSections'=>count($schedule['sections']),'knownMeetingTimesOnly'=>true];
file_put_contents($root.'/database/staging/academic-alignment/rebuild-staging-receipt.json',json_encode($receipt,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
echo json_encode($receipt,JSON_PRETTY_PRINT),"\n";
