<?php
declare(strict_types=1);
/** Guarded DML-only repair of derived subtype counts in the isolated test clone. */
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/config/database.php';
require dirname(__DIR__).'/includes/schedule.php';
$args=getopt('',['backup-receipt:','output:','apply']);
if ($database!=='northbridge_ow_rebuild_2026_7' || !in_array($host,['localhost','127.0.0.1'],true)) throw new RuntimeException('Isolated local _7 clone required.');
$backup=realpath($args['backup-receipt']??'');$output=realpath($args['output']??'');
if (!$backup || !$output || !str_starts_with($backup,'/private/tmp/') || !str_starts_with($output,'/private/tmp/')) throw new RuntimeException('Private verified backup and output directory required.');
$receipt=json_decode(file_get_contents($backup),true,512,JSON_THROW_ON_ERROR);
$fingerprint=static function(PDO $db):array {
    $out=[];foreach ($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if (!preg_match('/^[A-Za-z0-9_]+$/D',$table)) throw new RuntimeException('Unexpected identifier.');
        $out[$table]=[(int)$db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn(),(string)$db->query("CHECKSUM TABLE `$table` EXTENDED")->fetch()['Checksum']];
    }ksort($out);return $out;
};
$before=$fingerprint($pdo);
if (($receipt['source']??null)!==$database || $receipt['tableFingerprints']!==$before || !hash_equals($receipt['snapshotSHA256'],hash_file('sha256',dirname($backup).'/database.sql'))) throw new RuntimeException('Fresh matching backup required.');
$working=new PDO("mysql:host=$host;port=$port;dbname=northbridge_ow_rebuild_2026_5;charset=utf8mb4",$username,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$workingBefore=$fingerprint($working);
$marker=$pdo->query('SELECT * FROM NB_AcademicRebuild WHERE singleton=1')->fetch();
$timeline=require dirname(__DIR__).'/config/academic-timeline.php';
$plan=['status'=>'faculty-class-count-sync-planned','database'=>$database,'candidateFileSHA256'=>$marker['candidateSHA256'],'scheduleFileSHA256'=>$marker['scheduleSHA256'],'currentSemester'=>$timeline['current_semester'],'backupReceipt'=>$backup,'schemaChanged'=>false,'workingDatabaseChanged'=>false,'activationAllowed'=>false];
if (!isset($args['apply'])) { echo json_encode($plan),"\n";exit; }
try {
    $pdo->beginTransaction();$changed=schedule_sync_faculty_counts($pdo);
    $plan['status']='faculty-class-count-sync-applied';$plan['facultyRowsUpdated']=$changed;
    $audit=json_decode($marker['auditJSON'],true,512,JSON_THROW_ON_ERROR);$audit['facultyClassCountSync']=$plan;
    $pdo->prepare('UPDATE NB_AcademicRebuild SET auditJSON=? WHERE singleton=1')->execute([json_encode($audit,JSON_THROW_ON_ERROR)]);
    $after=$fingerprint($pdo);foreach ($before as $table=>$value) if (!in_array($table,['Full_Time_Faculty','Part_Time_Faculty','NB_AcademicRebuild'],true) && $after[$table]!==$value) throw new RuntimeException('Unrelated table changed.');
    if ($fingerprint($working)!==$workingBefore) throw new RuntimeException('Working database changed.');
    $pdo->commit();
} catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack();throw $e; }
umask(0077);file_put_contents($output.'/faculty-class-count-sync.json',json_encode($plan,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");echo json_encode($plan),"\n";
