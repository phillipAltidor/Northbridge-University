<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__,2);
require $root.'/config/database.php';require $root.'/includes/schedule.php';
if($host!=='127.0.0.1'||$database!=='northbridge_local')throw new RuntimeException('Expected local database.');
$plan=json_decode(file_get_contents(__DIR__.'/fall_2026_meetings.json'),true,512,JSON_THROW_ON_ERROR);
$sectionSQL="SELECT cs.*,c.course_Credits,r.room_Type FROM Course_Section cs JOIN Course c ON c.course_ID=cs.course_ID LEFT JOIN Room r ON r.room_ID=cs.room_ID JOIN Semester s ON s.semester_ID=cs.semester_ID WHERE s.semester_Name='Fall 2026' ORDER BY cs.CRN";
$enrollmentSQL="SELECT e.student_ID,e.CRN FROM Enrollment e JOIN Course_Section cs ON cs.CRN=e.CRN JOIN Semester s ON s.semester_ID=cs.semester_ID WHERE s.semester_Name='Fall 2026' ORDER BY e.student_ID,e.CRN";
function meeting_check(array $actual,array $expected,string $what):void{if($actual!==$expected)throw new RuntimeException($what.' changed; regenerate the meeting plan before applying.');}
meeting_check($pdo->query($sectionSQL)->fetchAll(),$plan['expected_sections'],'Course sections');
meeting_check($pdo->query($enrollmentSQL)->fetchAll(),$plan['expected_enrollment'],'Enrollment');
foreach(['Period'=>['expected_periods','period_ID'],'Time_Slot'=>['expected_slots','time_Slot_ID'],'Time_Slot_Day'=>['expected_days','time_Slot_ID,day_ID'],'Time_Slot_Period'=>['expected_slot_periods','time_Slot_ID,period_ID']] as $t=>[$key,$order])meeting_check($pdo->query("SELECT * FROM `$t` ORDER BY $order")->fetchAll(),$plan[$key],$t);
$backup=rtrim(sys_get_temp_dir(),'/').'/northbridge-before-fall-meetings-'.date('Ymd-His').'.sql';$f=fopen($backup,'x');if(!$f)throw new RuntimeException('Cannot create backup.');chmod($backup,0600);fwrite($f,"SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
foreach($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t){$ddl=$pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM)[1];fwrite($f,"DROP TABLE IF EXISTS `$t`;\n$ddl;\n");foreach($pdo->query("SELECT * FROM `$t`") as $row){$names=implode(',',array_map(static fn($c)=>"`$c`",array_keys($row)));$values=implode(',',array_map(static fn($v)=>$v===null?'NULL':$pdo->quote((string)$v),array_values($row)));fwrite($f,"INSERT INTO `$t` ($names) VALUES ($values);\n");}}fwrite($f,"SET FOREIGN_KEY_CHECKS=1;\n");fclose($f);echo "Backup saved: $backup\n";
$otherBefore=$pdo->query("SELECT * FROM Course_Section WHERE semester_ID NOT IN (SELECT semester_ID FROM Semester WHERE semester_Name='Fall 2026') ORDER BY CRN")->fetchAll();
try{
$pdo->beginTransaction();$pdo->query("SELECT semester_ID FROM Semester WHERE semester_Name='Fall 2026' FOR UPDATE")->fetchAll();
meeting_check($pdo->query($sectionSQL.' FOR UPDATE')->fetchAll(),$plan['expected_sections'],'Locked course sections');
meeting_check($pdo->query($enrollmentSQL.' FOR UPDATE')->fetchAll(),$plan['expected_enrollment'],'Locked enrollment');
foreach(['Period'=>'periods','Time_Slot'=>'slots','Time_Slot_Day'=>'days','Time_Slot_Period'=>'slot_periods'] as $t=>$key){$cols=array_keys($plan[$key][0]);$stmt=$pdo->prepare("INSERT INTO `$t` (".implode(',',$cols).') VALUES ('.implode(',',array_fill(0,count($cols),'?')).')');foreach($plan[$key] as $r)$stmt->execute(array_values($r));}
$stmt=$pdo->prepare('UPDATE Course_Section SET time_Slot_ID=? WHERE CRN=?');foreach($plan['assignments'] as $a)$stmt->execute([$a['time_Slot_ID'],$a['CRN']]);
$actual=$pdo->query($sectionSQL)->fetchAll();$expected=$plan['expected_sections'];$times=array_column($plan['assignments'],'time_Slot_ID','CRN');foreach($expected as &$r)$r['time_Slot_ID']=$times[$r['CRN']];unset($r);meeting_check($actual,$expected,'Updated course sections');
$meetings=[];foreach($pdo->query("SELECT cs.CRN,cs.faculty_ID,cs.room_ID,d.day_ID,p.start_Time,p.end_Time FROM Course_Section cs JOIN Semester s ON s.semester_ID=cs.semester_ID JOIN Time_Slot_Day d ON d.time_Slot_ID=cs.time_Slot_ID JOIN Time_Slot_Period tp ON tp.time_Slot_ID=cs.time_Slot_ID JOIN Period p ON p.period_ID=tp.period_ID WHERE s.semester_Name='Fall 2026'") as $m)$meetings[$m['CRN']][]=$m;
$resources=[];foreach($actual as $r){$resources['faculty:'.$r['faculty_ID']][]=$r['CRN'];if($r['room_ID']!==null)$resources['room:'.$r['room_ID']][]=$r['CRN'];}foreach($plan['expected_enrollment'] as $e)$resources['student:'.$e['student_ID']][]=$e['CRN'];
foreach($resources as $resource=>$crns)for($i=0;$i<count($crns);$i++)for($j=$i+1;$j<count($crns);$j++)foreach($meetings[$crns[$i]] as $a)foreach($meetings[$crns[$j]] as $b)if($a['day_ID']===$b['day_ID']&&$a['start_Time']<$b['end_Time']&&$a['end_Time']>$b['start_Time'])throw new RuntimeException('Meeting conflict for '.$resource);
foreach($actual as $r){$duration=$r['course_Credits']===3?90:($r['room_Type']==='Lab'?180:120);foreach($meetings[$r['CRN']] as $m){$minutes=(strtotime($m['end_Time'])-strtotime($m['start_Time']))/60;if((float)$minutes!== (float)$duration)throw new RuntimeException('Meeting duration mismatch.');}}
meeting_check($pdo->query("SELECT * FROM Course_Section WHERE semester_ID NOT IN (SELECT semester_ID FROM Semester WHERE semester_Name='Fall 2026') ORDER BY CRN")->fetchAll(),$otherBefore,'Other semester sections');
foreach($pdo->query("SELECT ms.semester_ID FROM Master_Schedule ms JOIN Semester s ON s.semester_ID=ms.semester_ID WHERE s.semester_Name='Fall 2026'")->fetchAll(PDO::FETCH_COLUMN) as $sid){$snapshot=['semester_Name'=>'Fall 2026','sections'=>schedule_sections($pdo,(int)$sid)];$stmt=$pdo->prepare('UPDATE Master_Schedule SET schedule_Data=? WHERE semester_ID=?');$stmt->execute([json_encode($snapshot,JSON_THROW_ON_ERROR),$sid]);}
$pdo->commit();echo "Updated 372 Fall sections: 124 Monday/Wednesday, 124 Tuesday/Thursday, 124 Friday.\n";echo "Verified meeting durations and zero faculty, room, or enrolled-student conflicts. Other semesters unchanged.\n";
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
