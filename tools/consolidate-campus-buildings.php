<?php
declare(strict_types=1);
/** Owner-approved four-campus-building overlay, DML only, test database only. */
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/config/database.php';
$args=getopt('',['candidate-directory:','apply']);$dir=realpath($args['candidate-directory']??'');
if($database!=='northbridge_ow_rebuild_2026_7'||getenv('NORTHBRIDGE_REHEARSAL_DB')!==$database||!$dir||!str_starts_with($dir,'/private/tmp/'))throw new RuntimeException('Explicit _7 test database and private candidate required.');
umask(0077);$read=static fn($p)=>json_decode(file_get_contents($p),true,512,JSON_THROW_ON_ERROR);
$receipt=$read($dir.'/full-history-database-receipt.json');$marker=$pdo->query('SELECT * FROM NB_AcademicRebuild WHERE singleton=1')->fetch();
if($receipt['targetDatabase']!==$database||$marker['candidateSHA256']!==$receipt['candidateFileSHA256']||$marker['scheduleSHA256']!==$receipt['scheduleFileSHA256'])throw new RuntimeException('Matching materialized academic candidate required.');
$map=['ADM'=>'ADM','ART'=>'NAB','BUS'=>'NAB','HSC'=>'SCI','LIB'=>'LIB','NAB'=>'NAB','SCI'=>'SCI','TECH'=>'SCI'];
$buildings=$pdo->query('SELECT * FROM Building ORDER BY Bldg_ID')->fetchAll();if(array_column($buildings,'Bldg_ID')!==array_keys($map))throw new RuntimeException('Expected original eight campus buildings.');
$rooms=$pdo->query('SELECT * FROM Room ORDER BY Bldg_ID,room_Number,room_ID')->fetchAll();$numbers=[];$maximum=[];
foreach($rooms as$r)if($map[$r['Bldg_ID']]===$r['Bldg_ID']){$key=$r['Bldg_ID'].':'.$r['room_Number'];if(isset($numbers[$key]))throw new RuntimeException('Existing duplicate room number.');$numbers[$key]=true;$maximum[$r['Bldg_ID']]=max($maximum[$r['Bldg_ID']]??0,$r['room_Number']);}
$moves=[];foreach($rooms as$r)if($map[$r['Bldg_ID']]!==$r['Bldg_ID']){$target=$map[$r['Bldg_ID']];$number=++$maximum[$target];$moves[]=['roomId'=>$r['room_ID'],'previousBuilding'=>$r['Bldg_ID'],'previousRoomNumber'=>$r['room_Number'],'building'=>$target,'roomNumber'=>$number];}
$fingerprint=static function(PDO$p):array{$out=[];foreach($p->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN)as$t)$out[$t]=[(int)$p->query("SELECT COUNT(*) FROM `$t`")->fetchColumn(),(string)$p->query("CHECKSUM TABLE `$t` EXTENDED")->fetch()['Checksum']];ksort($out);return$out;};
$schema=static function(PDO$p):string{$out=[];foreach($p->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN)as$t)$out[$t]=preg_replace('/ AUTO_INCREMENT=\d+/','',$p->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM)[1]);ksort($out);return hash('sha256',json_encode($out,JSON_THROW_ON_ERROR));};
$working=new PDO("mysql:host=$host;port=$port;dbname=northbridge_ow_rebuild_2026_5;charset=utf8mb4",$username,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$before=$fingerprint($pdo);$workingBefore=$fingerprint($working);$schemaBefore=$schema($pdo);
$plan=['status'=>'four-campus-building-plan-not-applied','database'=>$database,'ownerDecision'=>'2026-11-09: Four campus buildings total','buildingMap'=>$map,'retainedBuildings'=>['Administration Building','Northbridge Academic Building','Science Center','Northbridge Library'],'roomsPreserved'=>count($rooms),'roomsRelocated'=>count($moves),'roomIdentityTypeAndCapacitiesPreserved'=>true,'graduateCourseAssignmentsPreserved'=>true,'graduateRoomLocationLabelsChange'=>true,'candidateFileSHA256'=>$receipt['candidateFileSHA256'],'scheduleFileSHA256'=>$receipt['scheduleFileSHA256'],'schemaChanged'=>false,'workingDatabaseChanged'=>false,'activationAllowed'=>false,'roomMoves'=>$moves];
file_put_contents($dir.'/four-building-plan.json',json_encode($plan,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
if(!isset($args['apply'])){$out=$plan;unset($out['roomMoves']);echo json_encode($out),"\n";exit;}
try{
 $pdo->beginTransaction();$q=$pdo->prepare('UPDATE Room SET Bldg_ID=?,room_Number=? WHERE room_ID=? AND Bldg_ID=? AND room_Number=?');foreach($moves as$m){$q->execute([$m['building'],$m['roomNumber'],$m['roomId'],$m['previousBuilding'],$m['previousRoomNumber']]);if($q->rowCount()!==1)throw new RuntimeException('Original room mapping changed.');}
 $pdo->exec("DELETE FROM Building WHERE Bldg_ID IN('ART','BUS','HSC','TECH')");
 $pdo->prepare('UPDATE Building SET building_Usage=? WHERE Bldg_ID=?')->execute(['General, business, arts and media classrooms, studios, and academic offices','NAB']);
 $pdo->prepare('UPDATE Building SET building_Usage=? WHERE Bldg_ID=?')->execute(['Science, health, technology and engineering laboratories, classrooms, and offices','SCI']);
 $audit=json_decode($marker['auditJSON'],true,512,JSON_THROW_ON_ERROR);$audit['fourCampusBuildings']=['ownerDecision'=>$plan['ownerDecision'],'planFileSHA256'=>hash_file('sha256',$dir.'/four-building-plan.json'),'roomIdentitiesAndCapacitiesPreserved'=>true];$pdo->prepare('UPDATE NB_AcademicRebuild SET auditJSON=? WHERE singleton=1')->execute([json_encode($audit,JSON_THROW_ON_ERROR)]);
 $after=$fingerprint($pdo);foreach($before as$t=>$hash)if(!in_array($t,['Building','Room','NB_AcademicRebuild'],true)&&$after[$t]!==$hash)throw new RuntimeException('Unrelated table changed.');
 if($schema($pdo)!==$schemaBefore||$fingerprint($working)!==$workingBefore||(int)$pdo->query('SELECT COUNT(*) FROM Building')->fetchColumn()!==4||(int)$pdo->query('SELECT COUNT(*) FROM Room r LEFT JOIN Building b ON b.Bldg_ID=r.Bldg_ID WHERE b.Bldg_ID IS NULL')->fetchColumn()||(int)$pdo->query('SELECT COUNT(*) FROM (SELECT Bldg_ID,room_Number FROM Room GROUP BY Bldg_ID,room_Number HAVING COUNT(*)>1) d')->fetchColumn())throw new RuntimeException('Campus, schema, or working database preservation check failed.');
 $pdo->commit();
}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
$out=$plan;unset($out['roomMoves']);$out['status']='four-campus-buildings-materialized-in-test-clone';$out['planFileSHA256']=hash_file('sha256',$dir.'/four-building-plan.json');$out['unrelatedTablesUnchanged']=true;file_put_contents($dir.'/four-building-receipt.json',json_encode($out,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");echo json_encode($out),"\n";
