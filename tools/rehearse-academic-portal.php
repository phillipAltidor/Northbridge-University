<?php
declare(strict_types=1);
/** Materialize replacement rows only in a freshly cloned local rehearsal DB. */
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
ini_set('memory_limit','768M');
require dirname(__DIR__).'/config/database.php';
$args=getopt('', ['plan:', 'target:']);
$path=realpath($args['plan']??'');$target=$args['target']??'';
if (!$path || !str_starts_with($path,'/private/tmp/') || !preg_match('/^northbridge_ow_rebuild_2026_[4-9][0-9]*$/D',$target)) throw new RuntimeException('A private plan and separate rehearsal database are required.');
if (!in_array($host,['127.0.0.1','localhost'],true) || $database!=='northbridge_university') throw new RuntimeException('Expected local original portal configuration.');
$plan=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
if (($plan['status']??'')!=='prepared-for-isolated-portal-rehearsal-not-activation' || !empty($plan['sourceAudit']['structuralErrors']) || !empty($plan['sourceAudit']['scheduleValidationErrors'])) throw new RuntimeException('A structurally audited rehearsal plan is required.');
$test=new PDO("mysql:host=$host;port=$port;dbname=$target;charset=utf8mb4",$username,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$fingerprints=static function(PDO $db):array {$out=[];foreach ($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {if (!preg_match('/^[A-Za-z0-9_]+$/D',$t)) throw new RuntimeException('Invalid identifier.');$out[$t]=[(int)$db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn(),$db->query("CHECKSUM TABLE `$t` EXTENDED")->fetch()['Checksum']];}ksort($out);return $out;};
$before=$fingerprints($pdo);
$existing=$fingerprints($test);
$extraTables=['NB_AcademicProgram','NB_AcademicStudent','NB_AcademicMeeting','NB_AcademicSection','NB_AcademicCourse','NB_AcademicRebuild'];
foreach ($extraTables as $table) if (isset($existing[$table])) {
 if ($existing[$table][0]!==0) throw new RuntimeException('Refusing to overwrite a populated rehearsal.');
 unset($existing[$table]);
}
if ($existing!==$before) throw new RuntimeException('The rehearsal target must be a fresh, identical clone of the original working database.');
foreach ($extraTables as $table) $test->exec("DROP TABLE IF EXISTS `$table`");
$allowed=['Course','Major','Minor','Student','Undergraduate','Full_Time_Undergraduate','Part_Time_Undergraduate','Time_Slot','Time_Slot_Day','Time_Slot_Period','Period','Course_Section','Faculty_History','Course_Section_Update_Log','Enrollment','Student_History','Attendance','Student_Major','Student_Minor','Master_Schedule'];
if (array_diff(array_keys($plan['replacementTables']),$allowed)) throw new RuntimeException('Unexpected replacement table.');
$schema=file_get_contents(dirname(__DIR__).'/database/migrations/2026-11-07-portal-academic-rebuild.sql');
foreach (explode(';',$schema) as $sql) if (trim($sql)!=='') $test->exec($sql);
$insert=static function(PDO $db,string $table,array $rows):void {
 if (!$rows) return;$columns=array_keys($rows[0]);
 foreach ($columns as $column) if (!preg_match('/^[A-Za-z0-9_]+$/D',$column)) throw new RuntimeException('Invalid column.');
 $prefix="INSERT INTO `$table` (`".implode('`,`',$columns).'`) VALUES ';
 $one='('.implode(',',array_fill(0,count($columns),'?')).')';
 foreach (array_chunk($rows,100) as $batch) {$values=[];foreach ($batch as $row) foreach ($columns as $column) {$value=$row[$column];$values[]=is_array($value)?json_encode($value,JSON_THROW_ON_ERROR):$value;}$db->prepare($prefix.implode(',',array_fill(0,count($batch),$one)))->execute($values);}
};
$checks=[];
try {
 $test->exec('SET FOREIGN_KEY_CHECKS=0');$test->beginTransaction();
 $triggerRows=$test->query('SHOW TRIGGERS')->fetchAll();
 $enrollmentTriggers=array_filter($triggerRows,static fn($r)=>$r['Table']==='Enrollment' && $r['Event']==='INSERT');
 $rosteredSections=array_fill_keys(array_column($plan['replacementTables']['Enrollment'],'CRN'),true);
 // Delete old enrollment while its original sections still exist so installed
 // seat triggers can restore their counts. New sections load before rosters.
 $test->exec('DELETE FROM Enrollment');
 foreach ($plan['replacementTables'] as $table=>$rows) {
  $test->exec("DELETE FROM `$table`");
  if ($table==='Course_Section' && $enrollmentTriggers) foreach ($rows as &$section) if (isset($rosteredSections[$section['CRN']])) $section['available_Seats']=10;
  unset($section);$insert($test,$table,$rows);
  if ((int)$test->query("SELECT COUNT(*) FROM `$table`")->fetchColumn()!==count($rows)) throw new RuntimeException('Row count mismatch: '.$table);
 }
 $insert($test,'NB_AcademicCourse',$plan['academicCourses']);
 $insert($test,'NB_AcademicSection',$plan['academicSections']);
 $insert($test,'NB_AcademicMeeting',$plan['academicMeetings']);
 $profiles=[];foreach ($plan['academicStudents'] as $p) $profiles[]=['student_ID'=>$p['studentId'],'profile_JSON'=>$p];
 $insert($test,'NB_AcademicStudent',$profiles);
 $programRows=[];foreach ($plan['academicPrograms'] as $p) $programRows[]=['program_Name'=>$p['program'],'program_Kind'=>$p['kind'],'catalog_Edition'=>$p['catalogEdition'],'definition_JSON'=>$p];
 $insert($test,'NB_AcademicProgram',$programRows);
 $insert($test,'NB_AcademicRebuild',[['singleton'=>1,'candidateSHA256'=>$plan['candidateSHA256'],'scheduleSHA256'=>$plan['scheduleSHA256'],'integrationStatus'=>'isolated-rehearsal-not-active','auditJSON'=>$plan['sourceAudit']]]);
 $test->exec('SET FOREIGN_KEY_CHECKS=1');
 $groups=[];foreach ($test->query('SELECT TABLE_NAME,CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION') as $r) $groups[$r['TABLE_NAME'].'.'.$r['CONSTRAINT_NAME']][]=$r;
 foreach ($groups as $parts) {$on=[];$notnull=[];foreach ($parts as $r) {$on[]="c.`{$r['COLUMN_NAME']}`=p.`{$r['REFERENCED_COLUMN_NAME']}`";$notnull[]="c.`{$r['COLUMN_NAME']}` IS NOT NULL";}$r=$parts[0];$count=(int)$test->query("SELECT COUNT(*) FROM `{$r['TABLE_NAME']}` c LEFT JOIN `{$r['REFERENCED_TABLE_NAME']}` p ON ".implode(' AND ',$on).' WHERE '.implode(' AND ',$notnull)." AND p.`{$r['REFERENCED_COLUMN_NAME']}` IS NULL")->fetchColumn();if ($count) throw new RuntimeException('Orphaned foreign key: '.$r['TABLE_NAME']);}
 $checks['foreignKeyConstraintsChecked']=count($groups);
 $after=$fingerprints($test);
 foreach (['User','Login','Faculty','Faculty_Department','Graduate_Student','Full_Time_Graduate','Part_Time_Graduate','Advisor','Student_Hold'] as $table) {if ($after[$table]!==$before[$table]) throw new RuntimeException('Protected table changed: '.$table);}
 $checks['protectedIdentityAndGraduateTablesUnchanged']=true;
 $checks['historySectionMismatches']=(int)$test->query('SELECT COUNT(*) FROM Student_History h JOIN Course_Section s ON s.CRN=h.CRN WHERE h.course_ID<>s.course_ID OR h.semester_ID<>s.semester_ID')->fetchColumn();
 $checks['seatCountMismatches']=(int)$test->query('SELECT COUNT(*) FROM Course_Section s JOIN NB_AcademicSection a ON a.CRN=s.CRN WHERE s.semester_ID IN(1,2) AND s.available_Seats<>10-(SELECT COUNT(*) FROM Enrollment e WHERE e.CRN=s.CRN AND e.semester_ID=s.semester_ID)')->fetchColumn();
 if ($checks['historySectionMismatches'] || $checks['seatCountMismatches']) throw new RuntimeException('Portal materialization integrity checks failed.');
 if ($fingerprints($pdo)!==$before) throw new RuntimeException('Working database changed during rehearsal.');
 $test->commit();
} catch (Throwable $e) {if ($test->inTransaction()) $test->rollBack();$test->exec('SET FOREIGN_KEY_CHECKS=1');throw $e;}
$receipt=['status'=>'portal-rows-materialized-in-isolated-rehearsal','targetDatabase'=>$target,'candidateSHA256'=>$plan['candidateSHA256'],'planFileSHA256'=>hash_file('sha256',$path),'students'=>count($profiles),'sections'=>count($plan['academicSections']),'exactMeetingRows'=>count($plan['academicMeetings']),'checks'=>$checks,'workingDatabaseChanged'=>false,'portalConfigurationChanged'=>false,'activationPermitted'=>false];
file_put_contents(dirname(__DIR__).'/database/staging/academic-alignment/portal-rehearsal-receipt.json',json_encode($receipt,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");echo json_encode($receipt),"\n";
