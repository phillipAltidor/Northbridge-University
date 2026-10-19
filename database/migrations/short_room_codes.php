<?php
// Shorten room identifiers while preserving all building and room information.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/config/database.php';
require dirname(__DIR__,2).'/includes/schedule.php';
if($host!=='127.0.0.1'||$database!=='northbridge_local')throw new RuntimeException('Expected local database.');
$prefix=['NAB'=>'N','SCI'=>'S','TECH'=>'T','BUS'=>'B','HSC'=>'H','ART'=>'A','LIB'=>'L','ADM'=>'AD'];
$mapping=[];
foreach($pdo->query('SELECT * FROM Room') as $r){$new=$prefix[$r['Bldg_ID']].$r['room_Number'];$mapping[$r['room_ID']]=$new;}
if(count(array_unique($mapping))!==count($mapping))throw new RuntimeException('Duplicate room code.');
if(array_keys($mapping)===array_values($mapping)){echo "Already applied.\n";exit;}
$q=$pdo->prepare("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND COLUMN_NAME IN ('room_ID','lab_ID','lecture_ID','office_ID')");$q->execute([$database]);$columns=$q->fetchAll();
$q=$pdo->prepare("SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND REFERENCED_TABLE_NAME IN ('Room','Lab','Lecture','Office')");$q->execute([$database]);$keys=$q->fetchAll();
function short_room_json($v,$mapping){if(!is_array($v))return $v;foreach($v as $k=>$a){if(in_array($k,['room_ID','lab_ID','lecture_ID','office_ID'],true)&&$a!==null)$v[$k]=$mapping[$a]??$a;else $v[$k]=short_room_json($a,$mapping);}return $v;}
$tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);$counts=[];
$backup=rtrim(sys_get_temp_dir(),'/').'/northbridge-before-short-room-codes-'.date('Ymd-His').'.sql';$handle=fopen($backup,'x');if(!$handle)throw new RuntimeException('Cannot create backup.');chmod($backup,0600);fwrite($handle,"SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
foreach($tables as $t){$counts[$t]=(int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();$ddl=$pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM)[1];fwrite($handle,"DROP TABLE IF EXISTS `$t`;\n$ddl;\n");foreach($pdo->query("SELECT * FROM `$t`") as $r){$names=implode(',',array_map(static fn($c)=>"`$c`",array_keys($r)));$values=implode(',',array_map(static fn($v)=>$v===null?'NULL':$pdo->quote((string)$v),array_values($r)));fwrite($handle,"INSERT INTO `$t` ($names) VALUES ($values);\n");}}fwrite($handle,"SET FOREIGN_KEY_CHECKS=1;\n");fclose($handle);echo "Backup saved: $backup\n";

try{
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');$pdo->beginTransaction();
foreach($columns as $c){$stmt=$pdo->prepare("UPDATE `{$c['TABLE_NAME']}` SET `{$c['COLUMN_NAME']}`=? WHERE `{$c['COLUMN_NAME']}`=?");foreach($mapping as $a=>$b)if($a!==$b)$stmt->execute([$b,$a]);}
foreach(['Master_Schedule'=>['semester_ID',['schedule_Data']],'Course_Section_Update_Log'=>['log_ID',['before_Data','after_Data']]] as $t=>[$pk,$fields])foreach($pdo->query("SELECT * FROM `$t`")->fetchAll() as $r)foreach($fields as $field){$json=json_encode(short_room_json(json_decode($r[$field],true,512,JSON_THROW_ON_ERROR),$mapping),JSON_THROW_ON_ERROR);$stmt=$pdo->prepare("UPDATE `$t` SET `$field`=? WHERE `$pk`=?");$stmt->execute([$json,$r[$pk]]);}
foreach($keys as $k){$n=$pdo->query("SELECT COUNT(*) FROM `{$k['TABLE_NAME']}` c LEFT JOIN `{$k['REFERENCED_TABLE_NAME']}` p ON c.`{$k['COLUMN_NAME']}`=p.`{$k['REFERENCED_COLUMN_NAME']}` WHERE c.`{$k['COLUMN_NAME']}` IS NOT NULL AND p.`{$k['REFERENCED_COLUMN_NAME']}` IS NULL")->fetchColumn();if($n)throw new RuntimeException('Broken room reference.');}
foreach($counts as $t=>$n)if((int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn()!==$n)throw new RuntimeException('Row count changed.');
$pdo->commit();echo 'Updated '.count($mapping)." room codes; references and row counts verified.\n";
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}finally{$pdo->exec('SET FOREIGN_KEY_CHECKS=1');}
