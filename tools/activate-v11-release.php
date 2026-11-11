<?php
declare(strict_types=1);
/** V11-only preflight, private-file rehearsal, guarded connection switch and rollback. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/config/database.php';
require dirname(__DIR__).'/includes/release-cutover.php';
$args=getopt('',['release-directory:','candidate-directory:','seal-verification','rehearse','apply','approved-plan-sha256:','working-backup:','writes-drained','rollback-receipt:']);
$root=dirname(__DIR__);$envPath=$root.'/.env';
if(!in_array($host,['127.0.0.1','localhost'],true)||getenv('NORTHBRIDGE_REHEARSAL_DB'))throw new RuntimeException('Local working configuration without test override required.');
$read=static function(string $path):array {
    $real=realpath($path);if(!$real||!str_starts_with($real,'/private/tmp/'))throw new RuntimeException('Existing private evidence required.');
    return json_decode(file_get_contents($real),true,512,JSON_THROW_ON_ERROR);
};
$connect=static function(string $name)use($host,$port,$username,$password):PDO{if(!preg_match('/^northbridge_ow_(?:rebuild|restore)_2026_[0-9]+$/D',$name))throw new RuntimeException('Invalid release database name.');return new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",$username,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);};
$write=static function(string $path,array $value):void {$json=json_encode($value,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";if(file_put_contents($path,$json,LOCK_EX)!==strlen($json))throw new RuntimeException('Unable to write private receipt.');chmod($path,0600);};
umask(0077);
if(isset($args['rollback-receipt'])) {
    $receipt=$read($args['rollback-receipt']);
    if($database!=='northbridge_ow_rebuild_2026_8'||($receipt['status']??null)!=='v11-main-connection-switched'||$receipt['from']!=='northbridge_ow_rebuild_2026_5'||$receipt['to']!==$database)throw new RuntimeException('Matching applied V11 receipt required.');
    $result=release_switch_environment($envPath,hash_file('sha256',$envPath),$database,$receipt['from']);
    $write(dirname(realpath($args['rollback-receipt'])).'/rollback-receipt.json',['status'=>'v11-main-connection-rolled-back']+$result);
    echo "Rolled back DB_NAME to preserved _5; unrelated environment settings retained.\n";exit;
}
if($database!=='northbridge_ow_rebuild_2026_5')throw new RuntimeException('V11 promotion must start from working _5.');
$dir=realpath($args['release-directory']??'');$candidateDir=realpath($args['candidate-directory']??'');
foreach([$dir,$candidateDir] as $path)if(!$path||!str_starts_with($path,'/private/tmp/'))throw new RuntimeException('Private release/candidate directories required.');
$candidateHash=hash_file('sha256',$candidateDir.'/candidate.json');$scheduleHash=hash_file('sha256',$candidateDir.'/schedule.json');
if($candidateHash!=='69c1d4099a0788c56cc7e1bf8700d2c0bb32ef0fe6eb8e55d66f679f838e2560'||$scheduleHash!=='c814f689766d079905ac8ab13974e0959598a9fa851183822994bd4a5c3a192b')throw new RuntimeException('Exact reviewed V11 required.');
$source=$connect('northbridge_ow_rebuild_2026_7');$release=$connect('northbridge_ow_rebuild_2026_8');
$workingBefore=release_fingerprints($pdo);$sourceBefore=release_fingerprints($source);$releaseBefore=release_fingerprints($release);
$originalWorking=$read('/private/tmp/northbridge-rebuild-snapshot-0e96cbfe0586/receipt.json');
if($originalWorking['source']!==$database||$originalWorking['tableFingerprints']!=$workingBefore)throw new RuntimeException('Working data changed since the reviewed rebuild baseline; reconcile before promotion.');
$copy=$read($dir.'/rebuild-snapshot-receipt.json');$copyPrivate=$read($copy['privateDirectory'].'/receipt.json');
$restore=$read($dir.'/restore-rehearsal-receipt.json');$restorePrivate=$read($restore['privateDirectory'].'/receipt.json');
foreach([[$copy,$copyPrivate,'northbridge_ow_rebuild_2026_7','northbridge_ow_rebuild_2026_8'],[$restore,$restorePrivate,'northbridge_ow_rebuild_2026_8','northbridge_ow_restore_2026_13']] as [$public,$private,$from,$to]) {
    if($public['status']!=='snapshot-and-restore-verified'||$private['source']!==$from||$private['restoredTarget']!==$to||$private['tablesVerified']!==53||!hash_equals($private['snapshotSHA256'],hash_file('sha256',$private['privateDirectory'].'/database.sql')))throw new RuntimeException('Verified release copy/restore required.');
}
if(count($releaseBefore)!==53||$sourceBefore!==$releaseBefore||$copyPrivate['tableFingerprints']!==$releaseBefore||$restorePrivate['tableFingerprints']!==$releaseBefore||release_fingerprints($connect($restore['restoredTarget']))!==$releaseBefore)throw new RuntimeException('Release/source/restore data drifted.');
$definitions=release_definition_hashes($release);
if($definitions!==['schema'=>'3bdeb67a0f87cac21c99b76e2bcea201ba613431c70f210a6217b74b2b195d41','triggers'=>'5936968154ec1186642c038abdab94675d51db53749f8a719733ac37b608c246']||release_definition_hashes($pdo)!==$definitions||release_definition_hashes($source)!==$definitions||release_definition_hashes($connect($restore['restoredTarget']))!==$definitions)throw new RuntimeException('Schema or triggers differ.');
$marker=$release->query('SELECT * FROM NB_AcademicRebuild WHERE singleton=1')->fetch();$audit=json_decode($marker['auditJSON'],true,512,JSON_THROW_ON_ERROR);
$campus=$read($candidateDir.'/four-building-receipt.json');$counts=$read($candidateDir.'/faculty-class-count-sync.json');$seed=$read($candidateDir.'/portal-seed-verification.json');
if($marker['candidateSHA256']!==$candidateHash||$marker['scheduleSHA256']!==$scheduleHash||$campus['candidateFileSHA256']!==$candidateHash||$counts['candidateFileSHA256']!==$candidateHash||$counts['scheduleFileSHA256']!==$scheduleHash||$seed['candidateSHA256']!==$candidateHash||$campus['planFileSHA256']!==($audit['fourCampusBuildings']['planFileSHA256']??null)||($audit['facultyClassCountSync']??null)!=$counts||($seed['facultyClassCountSync']??null)!=$counts)throw new RuntimeException('Approved overlays are not bound to V11.');
if(count($seed['seedSHA256'])!==25)throw new RuntimeException('Complete private installation bundle required.');
foreach($seed['seedSHA256'] as $file=>$hash)if(basename($file)!==$file||!hash_equals($hash,hash_file('sha256',$candidateDir.'/seeds/'.$file)))throw new RuntimeException('Private installation seed changed.');
// Preserve every table outside the explicitly rebuilt academic/resource scope.
$allowed=['Course','Major','Minor','Student','Undergraduate','Full_Time_Undergraduate','Part_Time_Undergraduate','Time_Slot','Time_Slot_Day','Time_Slot_Period','Period','Faculty_History','Enrollment','Student_History','Attendance','Student_Major','Student_Minor','Faculty_Department','Building','Room','Full_Time_Faculty','Part_Time_Faculty','Course_Section','Master_Schedule','Course_Section_Update_Log','NB_AcademicCourse','NB_AcademicProgram','NB_AcademicStudent','NB_AcademicSection','NB_AcademicMeeting','NB_AcademicRebuild'];
$drift=[];foreach($workingBefore as $table=>$value)if(!in_array($table,$allowed,true)&&($releaseBefore[$table]??null)!==$value)$drift[]=$table;
if($drift)throw new RuntimeException('Protected working data changed; reconcile tables before activation: '.implode(', ',$drift));
$rowsHash=static function(PDO $db,string $sql):string {$rows=$db->query($sql)->fetchAll();$rows=array_map(static fn($r)=>json_encode($r,JSON_THROW_ON_ERROR),$rows);sort($rows);return hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));};
foreach([
    "SELECT * FROM Student WHERE student_Type='Graduate'",
    "SELECT e.* FROM Enrollment e JOIN Student s ON s.student_ID=e.student_ID WHERE s.student_Type='Graduate'",
    "SELECT h.* FROM Student_History h JOIN Student s ON s.student_ID=h.student_ID WHERE s.student_Type='Graduate'",
    "SELECT a.* FROM Attendance a JOIN Student s ON s.student_ID=a.student_ID WHERE s.student_Type='Graduate'",
    "SELECT c.* FROM Course_Section c JOIN Course k ON k.course_ID=c.course_ID WHERE k.course_Type='Graduate'",
    "SELECT h.* FROM Faculty_History h JOIN Course k ON k.course_ID=h.course_ID WHERE k.course_Type='Graduate'",
    'SELECT faculty_ID FROM Full_Time_Faculty',
    'SELECT faculty_ID,DOA FROM Part_Time_Faculty',
] as $sql)if($rowsHash($pdo,$sql)!==$rowsHash($release,$sql))throw new RuntimeException('Protected graduate records or faculty identity drift.');
$evidence=[
    'portal-http-verification.json'=>['status'=>'authenticated-rebuilt-portal-checks-passed','database'=>'northbridge_ow_rebuild_2026_8','baseURL'=>'http://127.0.0.1:8004/'],
    'portal-degree-verification.json'=>['status'=>'actual-database-history-and-degree-checks-passed','database'=>'northbridge_ow_rebuild_2026_8','studentsEvaluated'=>2200,'degreeEvaluationMismatches'=>0,'candidateFileSHA256'=>$candidateHash],
    'master-catalog-verification.json'=>['status'=>'master-catalog-level-and-pagination-checks-passed','database'=>'northbridge_ow_rebuild_2026_8'],
    'faculty-rosters-verification.json'=>['status'=>'faculty-rosters-and-employment-profile-verified','database'=>'northbridge_ow_rebuild_2026_8'],
    'student-degree-audit-http-verification.json'=>['status'=>'degree-audit-authenticated-http-checks-passed','database'=>'northbridge_ow_rebuild_2026_8','baseURL'=>'http://127.0.0.1:8004/'],
];
$evidenceHashes=[];foreach($evidence as $file=>$expected){$receipt=$read($dir.'/'.$file);foreach($expected as $key=>$value)if(($receipt[$key]??null)!==$value)throw new RuntimeException('Direct release verification missing: '.$file);$evidenceHashes[$file]=hash_file('sha256',$dir.'/'.$file);}
$runtimeHash=release_runtime_hash($root);foreach(array_keys($evidence) as $file)if(($read($dir.'/'.$file)['runtimeSHA256']??null)!==$runtimeHash)throw new RuntimeException('Verification runtime is stale: '.$file);$fingerprintHash=hash('sha256',json_encode($releaseBefore,JSON_THROW_ON_ERROR));
if(isset($args['seal-verification']))$write($dir.'/release-verification.json',['status'=>'direct-release-evidence-sealed','database'=>'northbridge_ow_rebuild_2026_8','runtimeSHA256'=>$runtimeHash,'releaseFingerprintSHA256'=>$fingerprintHash,'receiptSHA256'=>$evidenceHashes]);
$seal=$read($dir.'/release-verification.json');
if($seal['runtimeSHA256']!==$runtimeHash||$seal['releaseFingerprintSHA256']!==$fingerprintHash||$seal['receiptSHA256']!==$evidenceHashes)throw new RuntimeException('Code/data changed since direct release verification.');
$plan=['status'=>'v11-cutover-preflight-passed-not-applied','from'=>'northbridge_ow_rebuild_2026_5','to'=>'northbridge_ow_rebuild_2026_8','source'=>'northbridge_ow_rebuild_2026_7','candidateFileSHA256'=>$candidateHash,'scheduleFileSHA256'=>$scheduleHash,'campusPlanSHA256'=>$campus['planFileSHA256'],'runtimeSHA256'=>$runtimeHash,'activationToolSHA256'=>hash_file('sha256',__FILE__),'releaseFingerprintSHA256'=>$fingerprintHash,'workingFingerprintSHA256'=>hash('sha256',json_encode($workingBefore,JSON_THROW_ON_ERROR)),'environmentSHA256'=>hash_file('sha256',$envPath),'schemaSHA256'=>$definitions['schema'],'triggersSHA256'=>$definitions['triggers'],'tablesVerified'=>53,'privateSeedFilesVerified'=>25,'schemaChanged'=>false,'databaseChanged'=>false,'mainConnectionChanged'=>false,'ownerFinalReview'=>'pending','applyRequires'=>'explicit approved plan hash, fresh matching working backup and drained writes'];
if(release_fingerprints($pdo)!==$workingBefore||release_fingerprints($source)!==$sourceBefore||release_fingerprints($release)!==$releaseBefore)throw new RuntimeException('Data changed during preflight.');
if(isset($args['rehearse'])) {
    $fixture=$dir.'/rehearsal.env';file_put_contents($fixture,file_get_contents($envPath));chmod($fixture,0600);
    $forward=release_switch_environment($fixture,$plan['environmentSHA256'],$plan['from'],$plan['to']);
    if(release_database_setting(file_get_contents($fixture))!==$plan['to'])throw new RuntimeException('Rehearsal switch failed.');
    release_switch_environment($fixture,$forward['afterSHA256'],$plan['to'],$plan['from']);
    if(!hash_equals($plan['environmentSHA256'],hash_file('sha256',$fixture))||!hash_equals($plan['environmentSHA256'],hash_file('sha256',$envPath)))throw new RuntimeException('Rehearsal rollback failed.');
    unlink($fixture);$write($dir.'/switch-rehearsal.json',['status'=>'private-environment-switch-and-rollback-passed','from'=>$plan['from'],'to'=>$plan['to'],'realEnvironmentUnchanged'=>true,'databaseRecordsUnchanged'=>true]);
}
$planJSON=json_encode($plan,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";$planHash=hash('sha256',$planJSON);
if(isset($args['apply'])) {
    if(!hash_equals($planHash,$args['approved-plan-sha256']??'')||!isset($args['writes-drained']))throw new RuntimeException('Exact approved plan and drained writes required.');
    $backupPath=realpath($args['working-backup']??'');$backup=$read($backupPath?:'');
    if(time()-filemtime($backupPath)>3600||$backup['source']!==$plan['from']||$backup['tableFingerprints']!==$workingBefore||!hash_equals($backup['snapshotSHA256'],hash_file('sha256',dirname($backupPath).'/database.sql'))||release_fingerprints($connect($backup['restoredTarget']))!==$workingBefore)throw new RuntimeException('Fresh restored current-working backup required.');
    $forward=release_switch_environment($envPath,$plan['environmentSHA256'],$plan['from'],$plan['to']);
    try {
        if(release_fingerprints($pdo)!==$workingBefore||release_fingerprints($release)!==$releaseBefore)throw new RuntimeException('Database drift during switch.');
        $write($dir.'/activation-receipt.json',['status'=>'v11-main-connection-switched','postSwitchChecks'=>'pending','workingBackup'=>$backupPath,'approvedPlanSHA256'=>$planHash]+$forward);
    } catch(Throwable $error) {
        release_switch_environment($envPath,$forward['afterSHA256'],$plan['to'],$plan['from']);
        throw new RuntimeException('Activation failed; main connection rolled back.',0,$error);
    }
    echo "Main connection switched to _8; preserved _5 remains available. Post-switch checks required.\n";exit;
}
file_put_contents($dir.'/cutover-plan.json',$planJSON);chmod($dir.'/cutover-plan.json',0600);
echo json_encode($plan+['planFileSHA256'=>$planHash],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
