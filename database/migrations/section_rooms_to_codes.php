<?php
// Local migration: lab_ID -> room_ID, then building-code + room-number identifiers.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__,2);
require $root.'/config/database.php';
require $root.'/includes/schedule.php';
if($host!=='127.0.0.1'||$database!=='northbridge_local')throw new RuntimeException('Expected northbridge_local at 127.0.0.1.');
$q=$pdo->query('SHOW COLUMNS FROM Course_Section');$sectionColumns=array_column($q->fetchAll(),'Type','Field');
if(isset($sectionColumns['room_ID'])&&str_starts_with($sectionColumns['room_ID'],'varchar')){echo "Room migration already applied; no changes made.\n";exit;}
if(!isset($sectionColumns['lab_ID']))throw new RuntimeException('Unexpected source schema.');
$rooms=$pdo->query('SELECT * FROM Room')->fetchAll();$mapping=[];$roomTypes=[];
foreach($rooms as $r){$code=$r['Bldg_ID'].$r['room_Number'];if(!preg_match('/^[A-Z]+[0-9]+$/D',$code)||strlen($code)>20)throw new RuntimeException('Invalid generated room code.');$mapping[(string)$r['room_ID']]=$code;$roomTypes[$r['room_ID']]=$r['room_Type'];}
if(count(array_unique($mapping))!==count($mapping))throw new RuntimeException('Duplicate building/room number; review before converting.');
$assignments=[];$f=fopen(__DIR__.'/section_room_assignments.csv','r');fgetcsv($f,0,',','"','');while($r=fgetcsv($f,0,',','"',''))if($r[1]!=='')$assignments[(int)$r[0]]=(int)$r[1];fclose($f);
$sections=$pdo->query('SELECT * FROM Course_Section')->fetchAll();$planned=[];$recovered=0;
// Keep explicit admin removals of lab assignments rather than replacing their edits.
$removed=[];
foreach($pdo->query('SELECT CRN,before_Data,after_Data FROM Course_Section_Update_Log ORDER BY log_ID') as $l){$b=json_decode($l['before_Data'],true);$a=json_decode($l['after_Data'],true);if(($b['lab_ID']??null)!==($a['lab_ID']??null))$removed[$l['CRN']]=($a['lab_ID']??null)===null;}
foreach($sections as $s){$id=$s['lab_ID'];if($id===null&&!($removed[$s['CRN']]??false)&&isset($assignments[$s['CRN']])){$id=$assignments[$s['CRN']];$recovered++;}if($id!==null&&(!isset($mapping[(string)$id])||!in_array($roomTypes[$id],['Lecture','Lab'],true)))throw new RuntimeException('Invalid restored section room.');$planned[$s['CRN']]=$id;}
// Verify restored room assignments don't introduce overlapping room bookings.
$slotMeetings=[];
foreach($pdo->query('SELECT d.time_Slot_ID,d.day_ID,p.start_Time,p.end_Time FROM Time_Slot_Day d JOIN Time_Slot_Period tp ON tp.time_Slot_ID=d.time_Slot_ID JOIN Period p ON p.period_ID=tp.period_ID') as $m)$slotMeetings[$m['time_Slot_ID']][]=$m;
$bookings=[];
foreach($sections as $s){$r=$planned[$s['CRN']];if($r===null)continue;$key=$s['semester_ID'].':'.$r;foreach($bookings[$key]??[] as $other){if($s['lab_ID']!==null&&$other['lab_ID']!==null)continue;foreach($slotMeetings[$s['time_Slot_ID']]??[] as $a)foreach($slotMeetings[$other['time_Slot_ID']]??[] as $b)if($a['day_ID']===$b['day_ID']&&$a['start_Time']<$b['end_Time']&&$a['end_Time']>$b['start_Time'])throw new RuntimeException('Restored room conflict between CRNs '.$s['CRN'].' and '.$other['CRN']);}$bookings[$key][]=$s;}
$q=$pdo->prepare("SELECT TABLE_NAME,COLUMN_NAME,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND COLUMN_NAME IN ('room_ID','lab_ID','lecture_ID','office_ID') ORDER BY TABLE_NAME,ORDINAL_POSITION");$q->execute([$database]);$columns=$q->fetchAll();
$q=$pdo->prepare("SELECT k.TABLE_NAME,k.CONSTRAINT_NAME,k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.UPDATE_RULE,r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.TABLE_NAME=k.TABLE_NAME AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA=? AND k.REFERENCED_TABLE_NAME IN ('Room','Lab','Lecture','Office') ORDER BY k.TABLE_NAME,k.CONSTRAINT_NAME");$q->execute([$database]);$keys=$q->fetchAll();
$tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);$counts=[];
$backup=rtrim(sys_get_temp_dir(),'/').'/northbridge-before-room-codes-'.date('Ymd-His').'.sql';$handle=fopen($backup,'x');if(!$handle)throw new RuntimeException('Cannot create backup.');chmod($backup,0600);fwrite($handle,"SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
foreach($tables as $t){$counts[$t]=(int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();$ddl=$pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM)[1];fwrite($handle,"DROP TABLE IF EXISTS `$t`;\n$ddl;\n");foreach($pdo->query("SELECT * FROM `$t`") as $r){$names=implode(',',array_map(static fn($c)=>"`$c`",array_keys($r)));$values=implode(',',array_map(static fn($v)=>$v===null?'NULL':$pdo->quote((string)$v),array_values($r)));fwrite($handle,"INSERT INTO `$t` ($names) VALUES ($values);\n");}}fwrite($handle,"SET FOREIGN_KEY_CHECKS=1;\n");fclose($handle);echo "Backup saved: $backup\n";
function room_history($v,array $mapping){if(!is_array($v))return $v;foreach(array_keys($v) as $key){if(in_array($key,['room_ID','lab_ID','office_ID','lecture_ID'],true)&&$v[$key]!==null){$old=(string)$v[$key];if(isset($mapping[$old]))$v[$key]=$mapping[$old];elseif(!in_array($old,$mapping,true))throw new RuntimeException('Unknown room in history.');}else $v[$key]=room_history($v[$key],$mapping);}if(isset($v['CRN'])&&array_key_exists('lab_ID',$v)){$v['room_ID']=$v['lab_ID'];unset($v['lab_ID']);}return $v;}
$jsonChanges=[];
foreach(['Master_Schedule'=>['semester_ID',['schedule_Data']],'Course_Section_Update_Log'=>['log_ID',['before_Data','after_Data']]] as $t=>[$pk,$fields])foreach($pdo->query("SELECT * FROM `$t`") as $r)foreach($fields as $field)$jsonChanges[]=[$t,$pk,$r[$pk],$field,json_encode(room_history(json_decode($r[$field],true,512,JSON_THROW_ON_ERROR),$mapping),JSON_THROW_ON_ERROR)];
foreach($keys as $k)$pdo->exec("ALTER TABLE `{$k['TABLE_NAME']}` DROP FOREIGN KEY `{$k['CONSTRAINT_NAME']}`");
$q=$pdo->prepare('SELECT DEFAULT_CHARACTER_SET_NAME,DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?');$q->execute([$database]);$charset=$q->fetch();
foreach($columns as $c){$new=$c['TABLE_NAME']==='Course_Section'&&$c['COLUMN_NAME']==='lab_ID'?'room_ID':$c['COLUMN_NAME'];$clause=$new!==$c['COLUMN_NAME']?"CHANGE COLUMN `{$c['COLUMN_NAME']}` `$new`":"MODIFY COLUMN `$new`";$pdo->exec("ALTER TABLE `{$c['TABLE_NAME']}` $clause VARCHAR(20) CHARACTER SET {$charset['DEFAULT_CHARACTER_SET_NAME']} COLLATE {$charset['DEFAULT_COLLATION_NAME']} ".($c['IS_NULLABLE']==='NO'?'NOT NULL':'NULL'));}
try{$pdo->beginTransaction();foreach($columns as $c){$column=$c['TABLE_NAME']==='Course_Section'?'room_ID':$c['COLUMN_NAME'];$stmt=$pdo->prepare("UPDATE `{$c['TABLE_NAME']}` SET `$column`=? WHERE `$column`=?");foreach($mapping as $old=>$new)$stmt->execute([$new,(string)$old]);}
$stmt=$pdo->prepare('UPDATE Course_Section SET room_ID=? WHERE CRN=? AND room_ID IS NULL');foreach($planned as $crn=>$room)if($room!==null)$stmt->execute([$mapping[(string)$room],$crn]);
foreach($jsonChanges as [$t,$pk,$id,$field,$json]){$stmt=$pdo->prepare("UPDATE `$t` SET `$field`=? WHERE `$pk`=?");$stmt->execute([$json,$id]);}
foreach($pdo->query('SELECT semester_ID FROM Master_Schedule')->fetchAll(PDO::FETCH_COLUMN) as $sid){$stmt=$pdo->prepare('SELECT semester_Name FROM Semester WHERE semester_ID=?');$stmt->execute([$sid]);$snapshot=['semester_Name'=>$stmt->fetchColumn(),'sections'=>schedule_sections($pdo,(int)$sid)];$stmt=$pdo->prepare('UPDATE Master_Schedule SET schedule_Data=? WHERE semester_ID=?');$stmt->execute([json_encode($snapshot,JSON_THROW_ON_ERROR),$sid]);}
$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
foreach($keys as $k){if($k['TABLE_NAME']==='Course_Section'){$k['COLUMN_NAME']='room_ID';$k['REFERENCED_TABLE_NAME']='Room';$k['REFERENCED_COLUMN_NAME']='room_ID';}$pdo->exec("ALTER TABLE `{$k['TABLE_NAME']}` ADD CONSTRAINT `{$k['CONSTRAINT_NAME']}` FOREIGN KEY (`{$k['COLUMN_NAME']}`) REFERENCES `{$k['REFERENCED_TABLE_NAME']}` (`{$k['REFERENCED_COLUMN_NAME']}`) ON UPDATE {$k['UPDATE_RULE']} ON DELETE {$k['DELETE_RULE']}");}
foreach($counts as $t=>$n)if((int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn()!==$n)throw new RuntimeException('Row count mismatch in '.$t);
$byCRN=[];foreach($pdo->query('SELECT * FROM Course_Section') as $r)$byCRN[$r['CRN']]=$r;
foreach($sections as $s){$expected=$s;unset($expected['lab_ID']);$expected['room_ID']=$planned[$s['CRN']]===null?null:$mapping[(string)$planned[$s['CRN']]];ksort($expected);$actual=$byCRN[$s['CRN']];ksort($actual);if($expected!==$actual)throw new RuntimeException('Section verification mismatch.');}
echo 'Converted '.count($mapping).' room IDs and '.count($columns).' related columns; restored '.count($keys)." foreign keys.\n";
echo "Recovered $recovered original room assignments; all section data and table row counts verified.\n";
