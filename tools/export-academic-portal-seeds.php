<?php
declare(strict_types=1);
/** Export only fictional academic tables after a successful isolated rehearsal. */
if (PHP_SAPI!=='cli') exit;
ini_set('memory_limit','512M');
require dirname(__DIR__).'/config/database.php';
require dirname(__DIR__).'/database/migrations/data_helpers.php';
if (!preg_match('/^northbridge_ow_rebuild_2026_[4-9][0-9]*$/D',$database)) throw new RuntimeException('Isolated rebuilt database required.');
$root=dirname(__DIR__);$directory=$root.'/database/staging/academic-alignment/';$exportDirectory=$root.'/database/data/';
$args=getopt('', ['output:','receipts:','full-history']);$privateExport=isset($args['output'])||isset($args['receipts']);
if($privateExport){foreach(['output','receipts']as$k){$real=realpath($args[$k]??'');if(!$real||!str_starts_with($real,'/private/tmp/'))throw new RuntimeException('Existing private output/receipt directories required.');if($k==='output')$exportDirectory=$real.'/';else$directory=$real.'/';}}

$read=static fn($name)=>json_decode(file_get_contents($directory.$name),true,512,JSON_THROW_ON_ERROR);
$degree=$read('portal-degree-verification.json');$http=$read('portal-http-verification.json');$import=$read(isset($args['full-history'])?'full-history-database-receipt.json':'portal-rehearsal-receipt.json');
if(isset($args['full-history'])){if(!$privateExport)throw new RuntimeException('Full-history seeds remain private until cutover review.');$import['candidateSHA256']=$import['candidateFileSHA256'];if($degree['candidateFileSHA256']!==$import['candidateSHA256'])throw new RuntimeException('Degree receipt candidate mismatch.');}
if (($degree['status']??'')!=='actual-database-history-and-degree-checks-passed' || ($http['status']??'')!=='authenticated-rebuilt-portal-checks-passed' || $degree['database']!==$database || $http['database']!==$database || $import['targetDatabase']!==$database) throw new RuntimeException('Matching degree, HTTP and import checks required.');
$marker=$pdo->query('SELECT * FROM NB_AcademicRebuild WHERE singleton=1')->fetch();
if ($marker['candidateSHA256']!==$import['candidateSHA256']) throw new RuntimeException('Rehearsal candidate changed.');
$audit=json_decode($marker['auditJSON'],true,512,JSON_THROW_ON_ERROR);
if(isset($args['full-history'])) {
 $timeline=require $root.'/config/academic-timeline.php';
 foreach(['Full_Time_Faculty','Part_Time_Faculty'] as $table) {
  $q=$pdo->prepare("SELECT COUNT(*) FROM `$table` f WHERE f.no_of_Classes<>(SELECT COUNT(*) FROM Course_Section c JOIN Semester s ON s.semester_ID=c.semester_ID WHERE c.faculty_ID=f.faculty_ID AND s.semester_Name=?)");
  $q->execute([$timeline['current_semester']]);if((int)$q->fetchColumn())throw new RuntimeException('Faculty subtype counts are stale; refusing full-history export.');
 }
}
$mapping=['Course'=>'course.sql','Major'=>'major.sql','Minor'=>'minor.sql','Student'=>'student.sql','Undergraduate'=>'undergraduate.sql','Full_Time_Undergraduate'=>'full_time_undergraduate.sql','Part_Time_Undergraduate'=>'part_time_undergraduate.sql','Time_Slot'=>'time_slot.sql','Time_Slot_Day'=>'time_slot_day.sql','Time_Slot_Period'=>'time_slot_period.sql','Period'=>'period.sql','Faculty_History'=>'faculty_history.sql','Enrollment'=>'enrollment.sql','Student_History'=>'student_history.sql','Attendance'=>'attendance.sql','Student_Major'=>'student_major.sql','Student_Minor'=>'student_minor.sql'];
if(isset($args['full-history'])) {
    $mapping['Faculty_Department']='faculty_department.sql';
    $mapping['Full_Time_Faculty']='full_time_faculty.sql';
    $mapping['Part_Time_Faculty']='part_time_faculty.sql';
}
if(isset($args['full-history'])&&is_file($directory.'four-building-receipt.json')){$campus=$read('four-building-receipt.json');if($campus['database']!==$database||$campus['candidateFileSHA256']!==$marker['candidateSHA256']||($http['fourBuildingPlanSHA256']??null)!==$campus['planFileSHA256'])throw new RuntimeException('Campus overlay receipt mismatch.');$audit=json_decode($marker['auditJSON'],true,512,JSON_THROW_ON_ERROR);if(($audit['fourCampusBuildings']['planFileSHA256']??null)!==$campus['planFileSHA256'])throw new RuntimeException('Campus overlay marker mismatch.');$mapping['Building']='building.sql';$mapping['Room']='room.sql';}
umask(0077);$backup='/private/tmp/northbridge-academic-seed-backup-'.bin2hex(random_bytes(6));mkdir($backup,0700);
$exports=[];$verified=[];
foreach ($mapping as $table=>$file) {
 $rows=timeline_rows($pdo,$table);$sql=timeline_sql($pdo,$table,$rows);$temp='seed_verify_'.$table;
 $pdo->exec("CREATE TEMPORARY TABLE `$temp` LIKE `$table`");$pdo->exec(str_replace("INSERT INTO `$table`","INSERT INTO `$temp`",$sql));
 if (timeline_rows($pdo,$temp)!==$rows) throw new RuntimeException('Seed reload differs: '.$table);
 $pdo->exec("DROP TEMPORARY TABLE `$temp`");$exports[$file]='-- Owner-authorized fictional academic rebuild, 2026-11-07. Original people/accounts are unchanged.' . "\n\n".$sql;$verified[$table]=count($rows);
}
$sections=timeline_rows($pdo,'Course_Section');$exports['course_section.sql']=timeline_sql($pdo,'Course_Section',array_values(array_filter($sections,static fn($r)=>in_array($r['semester_ID'],[1,2],true))));
$exports['historical_course_section.sql']=timeline_sql($pdo,'Course_Section',array_values(array_filter($sections,static fn($r)=>!in_array($r['semester_ID'],[1,2],true))));
$pdo->exec('CREATE TEMPORARY TABLE seed_verify_sections LIKE Course_Section');
$pdo->exec(str_replace('INSERT INTO `Course_Section`','INSERT INTO `seed_verify_sections`',$exports['course_section.sql'].$exports['historical_course_section.sql']));
if (timeline_rows($pdo,'seed_verify_sections')!==$sections) throw new RuntimeException('Section seed reload mismatch.');$pdo->exec('DROP TEMPORARY TABLE seed_verify_sections');$verified['Course_Section']=count($sections);
$academic='';foreach (['NB_AcademicCourse','NB_AcademicProgram','NB_AcademicStudent','NB_AcademicSection','NB_AcademicMeeting','NB_AcademicRebuild'] as $table) {
 $rows=timeline_rows($pdo,$table);$temp='seed_verify_'.$table;$sql=timeline_sql($pdo,$table,$rows);
 $pdo->exec("CREATE TEMPORARY TABLE `$temp` LIKE `$table`");$pdo->exec(str_replace("INSERT INTO `$table`","INSERT INTO `$temp`",$sql));
 if (timeline_rows($pdo,$temp)!==$rows) throw new RuntimeException('Versioned academic seed reload mismatch.');$pdo->exec("DROP TEMPORARY TABLE `$temp`");$academic.=$sql."\n";$verified[$table]=count($rows);
}
$exports['academic_rebuild.sql']='-- Load 2026-11-07-portal-academic-rebuild.sql after all ordinary seed tables, then load this file.' . "\n".$academic;
$hashes=[];
foreach ($exports as $file=>$sql) {
 $path=$exportDirectory.$file;if (is_file($path)) {copy($path,$backup.'/'.$file);chmod($backup.'/'.$file,0600);}
 $temporary=$path.'.rebuild-tmp';if (file_put_contents($temporary,$sql)===false) throw new RuntimeException('Unable to write seed.');chmod($temporary,$privateExport?0600:0644);rename($temporary,$path);$hashes[$file]=hash_file('sha256',$path);
}
$receipt=['status'=>'academic-installation-seeds-exported-and-reload-verified','database'=>$database,'candidateSHA256'=>$marker['candidateSHA256'],'tablesReloadVerified'=>$verified,'seedSHA256'=>$hashes,'privateSeedBackup'=>$backup,'peopleAccountSeedsChanged'=>false,'workingDatabaseChanged'=>false,'privateStagedExport'=>$privateExport];
if(isset($campus))$receipt['fourBuildingPlanSHA256']=$campus['planFileSHA256'];
if(isset($args['full-history']))$receipt['facultyClassCountSemester']=$timeline['current_semester'];
if(isset($audit['facultyClassCountSync']))$receipt['facultyClassCountSync']=$audit['facultyClassCountSync'];
file_put_contents($directory.'portal-seed-verification.json',json_encode($receipt,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
echo json_encode(['status'=>$receipt['status'],'filesExported'=>count($exports),'tablesReloadVerified'=>count($verified),'peopleAccountSeedsChanged'=>false,'workingDatabaseChanged'=>false]),"\n";
