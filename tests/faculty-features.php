<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/includes/faculty-features.php';
class FacultyFixturePDO extends PDO {
 public function prepare(string $query,array $options=[]): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE','',$query),$options); }
 public function query(string $query,?int $fetchMode=null,mixed ...$args): PDOStatement|false {
  if (str_contains($query,'information_schema.TABLES')) $query='SELECT 0';
  return $fetchMode===null?parent::query($query):parent::query($query,$fetchMode,...$args);
 }
}
$mysql = in_array('--mysql', $argv, true);
if ($mysql) {
 $private=dirname(__DIR__).'/.local';
 $config=parse_ini_file($private.'/mysql-admin.cnf',true,INI_SCANNER_RAW)['client'];
 $pdo=new PDO('mysql:unix_socket='.$private.'/mysql.sock;charset=utf8mb4','root',$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 if ((int)$pdo->query('SELECT @@port')->fetchColumn()!==3307) throw new RuntimeException('Expected dedicated demo instance.');
 $fixture='northbridge_ruba_faculty_fixture_'.bin2hex(random_bytes(5));
 $pdo->exec("CREATE DATABASE `$fixture` CHARACTER SET utf8mb4");
 register_shutdown_function(static function()use($pdo,$fixture){ if($pdo->inTransaction())$pdo->rollBack(); $pdo->exec("DROP DATABASE `$fixture`"); });
 $pdo->exec("USE `$fixture`");
} else $pdo=new FacultyFixturePDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
$pdo->exec(preg_replace('/^USE .*;$/m','',file_get_contents(dirname(__DIR__).'/database/schema.sql')));
if (!$mysql) $pdo->exec('PRAGMA foreign_keys=ON');
$pass='FacultyFixture-Example42!';
foreach ([1=>'Faculty',2=>'Faculty',3=>'Student',4=>'Student',5=>'Student',6=>'Admin'] as $id=>$role) {
 $pdo->prepare('INSERT INTO User VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute([$id,'Example'.$id,null,'Person','Other','1990-01-01','1 Test Street','Test City','NY','10000',$role]);
 $pdo->prepare('INSERT INTO Login VALUES (?,?,?,0,0,?)')->execute([$id,"fixture$id@example.test",password_hash($pass,PASSWORD_DEFAULT),$role]);
}
$pdo->exec("INSERT INTO Building VALUES ('ADM','Administration','Academic'); INSERT INTO Room VALUES ('ADM101','ADM',101,'Office'); INSERT INTO Office VALUES ('ADM101',2);
INSERT INTO Faculty VALUES (1,'ADM101','Computing','Professor','Full-Time'),(2,'ADM101','Computing','Lecturer','Part-Time');
INSERT INTO Department VALUES (1,'Computing','ADM101','computing@example.test','555-0100',1,'Example Manager');
INSERT INTO Major VALUES (1,'Computing',1); INSERT INTO Student VALUES (3,1,1,'Undergraduate'),(4,1,2,'Undergraduate'),(5,1,3,'Undergraduate');
INSERT INTO Advisor VALUES (1,4,'2026-01-01');
INSERT INTO Course VALUES ('CS101',1,'Computing',4,'Description','Undergraduate');
INSERT INTO Semester VALUES (1,'Fall 2026'),(2,'Fall 2025'),(3,'Spring 2027');
INSERT INTO Day VALUES (1,'Thursday'); INSERT INTO Period VALUES (1,'09:00:00','10:00:00');
INSERT INTO Time_Slot VALUES (1,'Thursday','09:00-10:00'); INSERT INTO Time_Slot_Day VALUES (1,1); INSERT INTO Time_Slot_Period VALUES (1,1);
INSERT INTO Course_Section VALUES (101,'CS101',1,1,1,null,1,8),(102,'CS101',2,2,1,null,1,9),(103,'CS101',1,1,1,null,2,9),(104,'CS101',1,1,1,null,3,9);
INSERT INTO Enrollment VALUES (3,101,1,null),(4,101,1,null),(5,102,1,null),(3,103,2,null),(3,104,3,null);
INSERT INTO Student_History VALUES (3,103,'CS101',2,null);");
$checks=0;
function ok(bool $yes,string $label): void { global $checks; if (!$yes) throw new RuntimeException($label); $checks++; }
function rejects(callable $fn,string $label): void { try {$fn();} catch (DomainException $e) {ok(true,$label); return;} throw new RuntimeException('Expected rejection: '.$label); }
function unchanged(PDO $pdo, callable $fn,string $label): void {
 $tables=['User','Login','Enrollment','Student_History','Attendance'];
 $snap=static function()use($pdo,$tables){$v=[];foreach($tables as $t)$v[$t]=$pdo->query('SELECT * FROM '.$t)->fetchAll();return json_encode($v);};
 $before=$snap();rejects($fn,$label);ok(!$pdo->inTransaction() && $snap()===$before,$label.' atomic rollback');
}
foreach (['0','-1','01','2147483648',[],null,'1 OR 1=1'] as $bad) rejects(fn()=>faculty_id($bad),'invalid selector');
rejects(fn()=>faculty_text("x\n"),'control text'); rejects(fn()=>faculty_account($pdo,6),'nonfaculty account');
$a=faculty_account($pdo,1); $old=faculty_address_version($a);
faculty_save_address($pdo,1,['user_ID'=>2,'street'=>'2 New Street','city'=>'New City','state'=>'NY','zip_Code'=>'10001'],$old);
ok(faculty_account($pdo,1)['street']==='2 New Street' && faculty_account($pdo,2)['street']==='1 Test Street','address ownership');
unchanged($pdo,fn()=>faculty_save_address($pdo,1,['street'=>'3 New','city'=>'City','state'=>'NY','zip_Code'=>'10001'],$old),'stale address');
unchanged($pdo,fn()=>faculty_save_password($pdo,1,['current_password'=>'wrong','new_password'=>'AnotherFixturePass42!','confirmation'=>'AnotherFixturePass42!']),'wrong current password');
$auth=faculty_save_password($pdo,1,['current_password'=>$pass,'new_password'=>'AnotherFixturePass42!','confirmation'=>'AnotherFixturePass42!']);
$a=faculty_account($pdo,1);ok(password_verify('AnotherFixturePass42!',$a['user_Password']) && $auth===hash('sha256',$a['user_Password']),'password hash and new auth version');
ok(password_verify($pass,faculty_account($pdo,2)['user_Password']),'other password unchanged');
ok(array_column(faculty_student_search($pdo,1,'','advisees'),'student_ID')===[4],'own advisees only');
ok(array_column(faculty_student_search($pdo,1,'','students'),'student_ID')===[3,4],'own section/advising students only');
ok(faculty_student_search($pdo,1,'%','transcript')===[],'literal wildcard search');
$transcript=faculty_transcript($pdo,3);ok(count($transcript)===3 && $transcript[0]['semester_Name']==='Fall 2025','history/enrollment deduplicated chronological transcript');
$section=faculty_section($pdo,1,101);$meetings=faculty_meetings($pdo,101);
$now=new DateTimeImmutable('2026-11-19T09:15:00-05:00');
$policy=['Fall 2026'=>['attendance_dates'=>['2026-11-19']]];
ok(faculty_write_window('attendance',$section,$meetings,[],$now)!==null,'unconfigured calendar closed');
ok(faculty_write_window('attendance',$section,$meetings,$policy,$now)===null,'inside actual class period');
foreach (['2026-11-19T08:59:59-05:00','2026-11-19T10:00:00-05:00','2026-11-20T09:15:00-05:00'] as $time) ok(faculty_write_window('attendance',$section,$meetings,$policy,new DateTimeImmutable($time))!==null,'outside day/period closed');
$rows=faculty_mark_rows($pdo,$section,'2026-11-19');$version=faculty_marks_version($section,'2026-11-19','attendance',$rows);
unchanged($pdo,fn()=>faculty_save_marks($pdo,2,101,'attendance',[3=>'Present'],$version,$policy,$now),'unowned section');
unchanged($pdo,fn()=>faculty_save_marks($pdo,1,101,'attendance',[3=>'Present',5=>'Absent'],$version,$policy,$now),'unowned student rollback');
unchanged($pdo,fn()=>faculty_save_marks($pdo,1,101,'attendance',[3=>'Late'],$version,$policy,$now),'invalid attendance value');
unchanged($pdo,fn()=>faculty_save_marks($pdo,1,101,'attendance',[3=>'Present'],faculty_marks_version($section,'2026-11-18','attendance',$rows),$policy,$now),'date-bound form');
faculty_save_marks($pdo,1,101,'attendance',[3=>'Present',4=>'Absent'],$version,$policy,$now);
ok((int)$pdo->query('SELECT COUNT(*) FROM Attendance')->fetchColumn()===2,'attendance persisted');
unchanged($pdo,fn()=>faculty_save_marks($pdo,1,101,'attendance',[3=>'Absent'],$version,$policy,$now),'stale attendance');
$policy['Fall 2025']=['final_exams_start'=>'2025-12-10','final_exams_end'=>'2025-12-16','grading'=>[103=>['final_exam_ends_at'=>'2025-12-15T09:00:00-05:00','allowed_grades'=>['A','B','C','F']]]];
$past=faculty_section($pdo,1,103);$gradeClock=new DateTimeImmutable('2025-12-16T09:00:00-05:00');
$gradeRows=faculty_mark_rows($pdo,$past,'2025-12-16');$v=faculty_marks_version($past,'2025-12-16','grades',$gradeRows);
unchanged($pdo,fn()=>faculty_save_marks($pdo,1,103,'grades',[3=>'X'],$v,$policy,$gradeClock),'invalid grade');
faculty_save_marks($pdo,1,103,'grades',[3=>'A'],$v,$policy,$gradeClock);
ok($pdo->query('SELECT Grade FROM Enrollment WHERE CRN=103')->fetchColumn()==='A' && $pdo->query('SELECT Grade FROM Student_History WHERE CRN=103')->fetchColumn()==='A','grade/history consistent');
$v=faculty_marks_version($past,'2025-12-16','grades',faculty_mark_rows($pdo,$past,'2025-12-16'));
unchanged($pdo,fn()=>faculty_save_marks($pdo,1,103,'grades',[3=>'B'],$v,$policy,$gradeClock),'recorded grade protected');
ok(faculty_write_window('grades',$section,$meetings,$policy,$now)!==null,'current term final grades closed');
ok(faculty_write_window('grades',$past,[],$policy,new DateTimeImmutable('2025-12-17T09:00:00-05:00'))!==null,'grading close is exclusive');
ok(faculty_password_matches('legacy-example','legacy-example') && !faculty_password_matches('wrong','legacy-example'),'legacy login compatibility');
$calendar=faculty_window_config();
ok($calendar['Fall 2026']['starts_on']==='2026-08-24' && $calendar['Fall 2026']['ends_on']==='2026-12-16','approved Fall boundaries');
ok($calendar['Spring 2027']['starts_on']==='2027-01-25' && $calendar['Spring 2027']['ends_on']==='2027-05-18','approved Spring boundaries');
foreach (['2026-09-07','2026-10-12','2026-11-26','2026-12-08','2026-12-10'] as $closedDate) ok(!in_array($closedDate,$calendar['Fall 2026']['attendance_dates'],true),'Fall holiday/study/final excluded');
ok(in_array('2026-11-11',$calendar['Fall 2026']['attendance_dates'],true),'Veterans Day classes remain open');
foreach (['2027-02-15','2027-03-16','2027-05-10','2027-05-12'] as $closedDate) ok(!in_array($closedDate,$calendar['Spring 2027']['attendance_dates'],true),'Spring holiday/study/final excluded');
$spring=$section; $spring['semester_Name']='Spring 2027';
$afternoon=[['day_Of_Week'=>'Tuesday','start_Time'=>'16:00:00','end_Time'=>'18:00:00','start_Date'=>null,'end_Date'=>null]];
ok(faculty_write_window('attendance',$spring,$afternoon,$calendar,new DateTimeImmutable('2027-05-04T16:29:59-04:00'))===null,'before convocation closure');
ok(faculty_write_window('attendance',$spring,$afternoon,$calendar,new DateTimeImmutable('2027-05-04T16:30:00-04:00'))!==null,'convocation closure enforced');
foreach (['Fall 2026'=>['2026-12-16T12:00:00-05:00','2026-12-18T11:59:59-05:00','2026-12-18T12:00:00-05:00'], 'Spring 2027'=>['2027-05-18T12:00:00-04:00','2027-05-20T11:59:59-04:00','2027-05-20T12:00:00-04:00']] as $name=>$times) {
 $examSection=$section; $examSection['semester_Name']=$name;
 $calendar[$name]['grading'][$section['CRN']]=['final_exam_ends_at'=>$times[0],'allowed_grades'=>['A','F']];
 foreach ([$times[0],$times[1]] as $time) ok(faculty_write_window('grades',$examSection,[],$calendar,new DateTimeImmutable($time))===null,'exam end opens grading through last second of 48h');
 foreach ([(new DateTimeImmutable($times[0]))->modify('-1 second')->format('c'),$times[2]] as $time) ok(faculty_write_window('grades',$examSection,[],$calendar,new DateTimeImmutable($time))!==null,'before exam and deadline denied');
 foreach (['2027-02-30T12:00:00-05:00','2026-12-09T12:00:00-05:00','2027-05-18T12:00:00-05:00','2026-12-16T12:00:00'] as $bad) {
  $invalid=$calendar; $invalid[$name]['grading'][$section['CRN']]['final_exam_ends_at']=$bad;
  ok(faculty_write_window('grades',$examSection,[],$invalid,new DateTimeImmutable($times[0]))!==null,'invalid or outside exam schedule denied');
 }
}
if ($mysql) {
 foreach (['address','attendance'] as $operation) {
  $version=$operation==='address'?faculty_address_version(faculty_account($pdo,1)):faculty_marks_version($section,'2026-11-19','attendance',faculty_mark_rows($pdo,$section,'2026-11-19'));
  $children=[];
  foreach ([0,1] as $n) {
   $values=$operation==='address'?['street'=>'Concurrent Street '.$n,'city'=>'City','state'=>'NY','zip_Code'=>'10001']:($n===0?[3=>'Absent']:[4=>'Present']);
   $process=proc_open([PHP_BINARY,__DIR__.'/faculty-concurrent-worker.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
   if (!is_resource($process)) throw new RuntimeException('Cannot start concurrent test.');
   fwrite($pipes[0],json_encode(['database'=>$fixture,'mode'=>$operation,'version'=>$version,'values'=>$values]));fclose($pipes[0]);
   $children[]=[$process,$pipes];
  }
  $results=[];
  foreach ($children as [$process,$pipes]) {
   $results[]=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
   if(proc_close($process)!==0)throw new RuntimeException('Concurrent worker failed: '.$stderr);
  }
  sort($results);ok($results===['saved','stale'],'concurrent '.$operation.' rejects stale loser');
 }
}
echo "PASS: $checks faculty checks on ".($mysql?"isolated MySQL":"in-memory SQLite")."; concurrent address/attendance checked when using MySQL.\n";
