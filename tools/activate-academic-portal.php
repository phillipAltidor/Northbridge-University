<?php
declare(strict_types=1);
/** Local cutover after independent academic, portal, seed and restore checks. */
if (PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/config/database.php';
$args=getopt('', ['candidate:','target:']);$target=$args['target']??'northbridge_ow_rebuild_2026_5';$candidate=realpath($args['candidate']??'');
if (!in_array($host,['127.0.0.1','localhost'],true) || $database!=='northbridge_university' || getenv('NORTHBRIDGE_REHEARSAL_DB')) throw new RuntimeException('Cutover must start from the original local portal configuration.');
if (!$candidate || !str_starts_with($candidate,'/private/tmp/') || !preg_match('/^northbridge_ow_rebuild_2026_[4-9][0-9]*$/D',$target)) throw new RuntimeException('Private candidate and isolated target required.');
$root=dirname(__DIR__);$directory=$root.'/database/staging/academic-alignment/';
$read=static fn($file)=>json_decode(file_get_contents($directory.$file),true,512,JSON_THROW_ON_ERROR);
$audit=$read('rebuild-candidate-audit.json');$degree=$read('portal-degree-verification.json');$http=$read('portal-http-verification.json');$seed=$read('portal-seed-verification.json');$restore=$read('restore-rehearsal-receipt.json');$import=$read('portal-rehearsal-receipt.json');
$candidateHash=hash_file('sha256',$candidate);
if (($audit['candidateFileSHA256']??'')!==$candidateHash || ($degree['candidateFileSHA256']??'')!==$candidateHash || !empty($audit['structuralErrors']) || !empty($audit['scheduleValidationErrors']) || ($audit['sourceRuleReviewCourses']??1)!==0 || count($audit['independentlyCheckedScheduleTerms']??[])!==14) throw new RuntimeException('Exact complete academic validation required.');
if (array_diff($audit['activationBlockers'],['actual-rebuilt-student-degree-evaluations-not-verified','portal-and-installation-seed-integration-not-verified'])) throw new RuntimeException('Unresolved academic activation blockers remain.');
if ($degree['status']!=='actual-database-history-and-degree-checks-passed' || $degree['studentsEvaluated']!==2200 || $degree['degreeEvaluationMismatches']!==0 || $degree['database']!==$target) throw new RuntimeException('Complete actual-history evaluation required.');
if ($http['status']!=='authenticated-rebuilt-portal-checks-passed' || $http['checks']<476 || $http['database']!==$target || $import['targetDatabase']!==$target) throw new RuntimeException('Matching authenticated portal rehearsal required.');
if ($seed['status']!=='academic-installation-seeds-exported-and-reload-verified' || $seed['database']!==$target || $seed['candidateSHA256']!==$audit['candidateSHA256']) throw new RuntimeException('Matching reload-verified installation seeds required.');
foreach ($seed['seedSHA256'] as $file=>$expected) if (basename($file)!==$file || !hash_equals($expected,hash_file('sha256',$root.'/database/data/'.$file))) throw new RuntimeException('Installation seed changed after verification.');
if ($restore['status']!=='snapshot-and-restore-verified' || $restore['source']!==$target || $restore['tablesVerified']!==53) throw new RuntimeException('Matching final restoration rehearsal required.');
$privateRestore=json_decode(file_get_contents($restore['privateDirectory'].'/receipt.json'),true,512,JSON_THROW_ON_ERROR);
$fingerprint=static function(PDO $connection,string $schema,array $tables):array {$result=[];foreach ($tables as $table) {if (!preg_match('/^[A-Za-z0-9_]+$/D',$table)) throw new RuntimeException('Invalid table identifier.');$name="`$schema`.`$table`";$result[$table]=[(int)$connection->query('SELECT COUNT(*) FROM '.$name)->fetchColumn(),(string)$connection->query('CHECKSUM TABLE '.$name.' EXTENDED')->fetch()['Checksum']];}return $result;};
$tables=array_keys($privateRestore['tableFingerprints']);
if ($fingerprint($pdo,$target,$tables)!==$privateRestore['tableFingerprints'] || $fingerprint($pdo,$restore['restoredTarget'],$tables)!==$privateRestore['tableFingerprints']) throw new RuntimeException('Rebuilt or restored database changed after restoration verification.');
$protected=['User','Login','Faculty','Faculty_Department','Graduate_Student','Full_Time_Graduate','Part_Time_Graduate','Advisor','Student_Hold','Room','Building','Department','Office','Lab','Lecture'];
if ($fingerprint($pdo,'northbridge_university',$protected)!==$fingerprint($pdo,$target,$protected)) throw new RuntimeException('People, accounts, graduate profiles or institutional resources changed since the clone.');
$test=new PDO("mysql:host=$host;port=$port;dbname=$target;charset=utf8mb4",$username,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$marker=$test->query('SELECT * FROM NB_AcademicRebuild WHERE singleton=1')->fetch();
if ($marker['candidateSHA256']!==$audit['candidateSHA256']) throw new RuntimeException('Target candidate mismatch.');
$envPath=$root.'/.env';$env=file_get_contents($envPath);$changed=preg_replace('/^DB_NAME\s*=.*$/m','DB_NAME='.$target,$env,-1,$count);
if ($count!==1 || $changed===$env) throw new RuntimeException('Expected exactly one original database configuration setting.');
umask(0077);$backup='/private/tmp/northbridge-academic-cutover-'.bin2hex(random_bytes(6));mkdir($backup,0700);file_put_contents($backup.'/original.env',$env);chmod($backup.'/original.env',0600);
$finalAudit=$audit;$finalAudit['status']='validated-for-local-activation';$finalAudit['activationBlockers']=[];$finalAudit['evidenceReceipts']=['portal-degree-verification.json','portal-http-verification.json','portal-seed-verification.json','restore-rehearsal-receipt.json'];
try {
 $test->beginTransaction();
 $q=$test->prepare('UPDATE NB_AcademicRebuild SET integrationStatus=?,auditJSON=? WHERE singleton=1 AND candidateSHA256=?');$q->execute(['active-local',json_encode($finalAudit,JSON_THROW_ON_ERROR),$marker['candidateSHA256']]);
 if ($q->rowCount()!==1) throw new RuntimeException('Target activation marker changed.');
 $temporary=$envPath.'.academic-cutover-tmp';if (file_put_contents($temporary,$changed)===false) throw new RuntimeException('Unable to prepare database configuration.');chmod($temporary,fileperms($envPath)&0777);
 if (!rename($temporary,$envPath)) throw new RuntimeException('Unable to switch database configuration.');
 $test->commit();
} catch (Throwable $e) {if ($test->inTransaction()) $test->rollBack();file_put_contents($envPath,$env);throw $e;}
$receipt=['status'=>'local-portal-switched-to-validated-academic-rebuild','originalDatabase'=>'northbridge_university','activeDatabase'=>$target,'candidateSHA256'=>$marker['candidateSHA256'],'candidateFileSHA256'=>$candidateHash,'students'=>2200,'sections'=>4801,'semesters'=>14,'originalDatabaseUnchanged'=>true,'peopleAndAccountsPreserved'=>true,'privateConfigurationBackup'=>$backup.'/original.env','restoreSnapshotSHA256'=>$restore['snapshotSHA256'],'postSwitchChecks'=>'pending'];
file_put_contents($directory.'portal-activation-receipt.json',json_encode($receipt,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");echo json_encode($receipt),"\n";
