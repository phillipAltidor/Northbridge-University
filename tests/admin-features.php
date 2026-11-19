<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/includes/admin-features.php';
class FacultyFixturePDO extends PDO {
 public function prepare(string $query,array $options=[]): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE','',$query),$options); }
 public function query(string $query,?int $fetchMode=null,mixed ...$args): PDOStatement|false {
  if (str_contains($query,'information_schema.TABLES')) $query='SELECT 0';
  return $fetchMode===null?parent::query($query):parent::query($query,$fetchMode,...$args);
 }
}
$mysql = true;
if ($mysql) {
 $private=dirname(__DIR__).'/.local';
 $config=parse_ini_file($private.'/mysql-admin.cnf',true,INI_SCANNER_RAW)['client'];
 $pdo=new PDO('mysql:unix_socket='.$private.'/mysql.sock;charset=utf8mb4','root',$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 if ((int)$pdo->query('SELECT @@port')->fetchColumn()!==3307) throw new RuntimeException('Expected dedicated demo instance.');
 $fixture='northbridge_ruba_admin_fixture_'.bin2hex(random_bytes(5));
 $pdo->exec("CREATE DATABASE `$fixture` CHARACTER SET utf8mb4");
 register_shutdown_function(static function()use($pdo,$fixture){ if($pdo->inTransaction())$pdo->rollBack(); $pdo->exec("DROP DATABASE `$fixture`"); });
 $pdo->exec("USE `$fixture`");
} else $pdo=new FacultyFixturePDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
$pdo->exec(preg_replace('/^USE .*;$/m','',file_get_contents(dirname(__DIR__).'/database/schema.sql')));
if (!$mysql) $pdo->exec('PRAGMA foreign_keys=ON');
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/migrations/admin_features.sql'));
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/migrations/admin_70_master_schedule.sql'));
$_ENV['ADMIN_FEATURE_WRITER_IDS']='6';
foreach ([1=>'Faculty',2=>'Faculty',3=>'Student',4=>'Student',5=>'Student',6=>'Admin',7=>'Faculty',8=>'Student'] as $id=>$role) {
 af_query($pdo,'INSERT INTO User VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$id,'Example'.$id,null,'Person','Other','1990-01-01','1 Test Street','City','NY','10000',$role]);
}
$pdo->exec("INSERT INTO Admin VALUES (6); INSERT INTO Building VALUES ('ADM','Administration','Academic');
INSERT INTO Room VALUES ('ADM101','ADM',101,'Office'),('ADM102','ADM',102,'Lecture'),('ADM103','ADM',103,'Lecture');
INSERT INTO Office VALUES ('ADM101',5); INSERT INTO Lecture VALUES ('ADM102',20),('ADM103',20);
INSERT INTO Faculty VALUES (1,'ADM101','Computing','Professor','Full-Time'),(2,'ADM101','Computing','Lecturer','Part-Time'),(7,'ADM101','Computing','Professor','Full-Time');
INSERT INTO Full_Time_Faculty VALUES (1,0),(7,0); INSERT INTO Part_Time_Faculty VALUES (2,0,'2020-01-01');
INSERT INTO Department VALUES (1,'Computing','ADM101','computing@example.test','555-0100',1,'Manager');
INSERT INTO Faculty_Department VALUES (1,1,100,'2020-01-01'),(2,1,100,'2020-01-01'),(7,1,100,'2020-01-01');
INSERT INTO Major VALUES (1,'Computing',1); INSERT INTO Student VALUES (3,1,1,'Undergraduate'),(4,1,2,'Undergraduate'),(5,1,3,'Undergraduate'),(8,1,1,'Undergraduate');
INSERT INTO Undergraduate VALUES (3,1,'Full-Time'),(4,1,'Part-Time'),(5,1,'Full-Time'),(8,1,'Full-Time');
INSERT INTO Course VALUES ('CS101',1,'Computing',4,'Description','Undergraduate');
INSERT INTO Semester VALUES (1,'Fall 2026'),(2,'Fall 2025'),(3,'Spring 2027');
INSERT INTO Day VALUES (1,'Thursday'),(2,'Friday'); INSERT INTO Period VALUES (1,'09:00:00','11:00:00'),(2,'11:00:00','13:00:00');
INSERT INTO Time_Slot VALUES (1,'Thursday','09:00-11:00'),(2,'Thursday','11:00-13:00'),(3,'Friday','09:00-11:00');
INSERT INTO Time_Slot_Day VALUES (1,1),(2,1),(3,2); INSERT INTO Time_Slot_Period VALUES (1,1),(2,2),(3,1);");
foreach (require dirname(__DIR__).'/database/migrations/section_seat_triggers.php' as $sql) $pdo->exec($sql);
$checks=0;$now=new DateTimeImmutable('2026-11-19T12:00:00-05:00');
function ok(bool $v,string $label):void {global $checks;if (!$v) throw new RuntimeException($label);$checks++;}
function run_action(string $mode,array $in):string {global $pdo,$now;$s=af_snapshot($pdo,$mode,$in);if ($s && !isset($in['version'])) $in['version']=af_version($s);return af_save($pdo,$mode,$in,6,$now);}
function denied(string $mode,array $in,string $label):void {global $pdo,$checks; $tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);$snapshot=static function()use($pdo,$tables){$r=[];foreach ($tables as $t)$r[$t]=$pdo->query("SELECT * FROM `$t`")->fetchAll();return json_encode($r);};$before=$snapshot();try {run_action($mode,$in);}catch(DomainException|PDOException $e){ok(!$pdo->inTransaction()&&$snapshot()===$before,'rollback: '.$label);return;}throw new RuntimeException('Expected denial: '.$label);}
$course=['course_ID'=>'CS102','course_Name'=>'Algorithms','dept_ID'=>'1','course_Credits'=>'4','course_Type'=>'Undergraduate','course_DSC'=>'Study algorithms.'];
run_action('create-course',$course);ok(af_row($pdo,'Course','course_ID','CS102')['course_Name']==='Algorithms','course created');
$course['course_Name']='Algorithms II';run_action('update-course',$course);ok(af_row($pdo,'Course','course_ID','CS102')['course_Name']==='Algorithms II','course updated');
denied('update-course',$course+['version'=>'stale'],'stale course');
run_action('add-prerequisites',['course_ID'=>'CS102','prerequisite_course_ID'=>'CS101','min_Grade_Req'=>'C']);
denied('add-prerequisites',['course_ID'=>'CS101','prerequisite_course_ID'=>'CS102','min_Grade_Req'=>'C'],'prerequisite cycle');
denied('add-prerequisites',['course_ID'=>'CS101','prerequisite_course_ID'=>'CS101','min_Grade_Req'=>'C'],'self prerequisite');
foreach (['major','minor'] as $kind) {
 run_action('create-'.$kind,[$kind.'_Name'=>'New '.$kind,'dept_ID'=>'1']);$id=(string)$pdo->query('SELECT MAX('.$kind.'_ID) FROM '.ucfirst($kind))->fetchColumn();
 run_action('update-'.$kind,[$kind.'_ID'=>$id,$kind.'_Name'=>'Updated '.$kind,'dept_ID'=>'1']);
 run_action('create-'.$kind.'-requirements',[$kind.'_ID'=>$id,'course_ID'=>'CS101','min_Grade_Req'=>'C']);
 run_action('update-'.$kind.'-requirements',[$kind.'_ID'=>$id,'course_ID'=>'CS101','min_Grade_Req'=>'B']);
 ok($pdo->query('SELECT min_Grade_Req FROM '.ucfirst($kind).'_Requirements')->fetchColumn()==='B',$kind.' requirement updated');
}
run_action('create-building',['Bldg_ID'=>'SCI','Bldg_Name'=>'Science','building_Usage'=>'Teaching']);
run_action('update-building',['Bldg_ID'=>'SCI','Bldg_Name'=>'Sciences','building_Usage'=>'Teaching']);
foreach (['Lecture','Lab','Office'] as $i=>$type) run_action('create-room',['room_ID'=>'SCI'.($i+1),'Bldg_ID'=>'SCI','room_Number'=>(string)($i+1),'room_Type'=>$type,'capacity'=>'20']);
ok((int)$pdo->query("SELECT COUNT(*) FROM Room WHERE Bldg_ID='SCI'")->fetchColumn()===3,'room subtypes created');
denied('create-room',['room_ID'=>'SCI9','Bldg_ID'=>'SCI','room_Number'=>'1','room_Type'=>'Lecture','capacity'=>'20'],'duplicate room number');
$section=['course_ID'=>'CS101','semester_ID'=>'1','section_No'=>'1','faculty_ID'=>'1','time_Slot_ID'=>'1','room_ID'=>'ADM102'];
run_action('create-section',$section);$crn=(string)$pdo->query('SELECT MAX(CRN) FROM Course_Section')->fetchColumn();
$change=$section;$change['CRN']=$crn;$change['section_No']='2';run_action('update-section',$change);
$change['time_Slot_ID']='2';denied('update-section',$change,'time changes after start');
$second=$section;$second['section_No']='3';$second['faculty_ID']='7';denied('create-section',$second,'room conflict');
$second['room_ID']='ADM103';$second['faculty_ID']='1';denied('create-section',$second,'faculty conflict');
$second['time_Slot_ID']='2';run_action('create-section',$second);
$second['time_Slot_ID']='3';$second['section_No']='4';denied('create-section',$second,'full-time maximum');
run_action('add-faculty-section',['CRN'=>$crn,'faculty_ID'=>'7']);run_action('update-faculty-section',['CRN'=>$crn,'faculty_ID'=>'1']);
run_action('add-student-section',['student_ID'=>'3','CRN'=>$crn]);ok((int)af_row($pdo,'Course_Section','CRN',$crn)['available_Seats']===9,'add updates seats exactly once');
denied('add-student-section',['student_ID'=>'3','CRN'=>$crn],'duplicate registration');
run_action('drop-student-section',['student_ID'=>'3','CRN'=>$crn,'reason'=>'Student request']);ok((int)af_row($pdo,'Course_Section','CRN',$crn)['available_Seats']===10,'drop restores seat');
run_action('add-student-section',['student_ID'=>'3','CRN'=>$crn]);run_action('update-student-grade',['student_ID'=>'3','CRN'=>$crn,'Grade'=>'B','reason'=>'Instructor request']);
run_action('change-grade',['student_ID'=>'3','CRN'=>$crn,'Grade'=>'A','reason'=>'Approved correction']);ok($pdo->query('SELECT Grade FROM Enrollment')->fetchColumn()==='A','grade correction');
denied('drop-student-section',['student_ID'=>'3','CRN'=>$crn,'reason'=>'request'],'preserve graded records');
run_action('declare-major',['student_ID'=>'3','major_ID'=>'2']);run_action('add-minor',['student_ID'=>'3','minor_ID'=>'1']);run_action('assign-advisor',['student_ID'=>'3','faculty_ID'=>'1']);
denied('declare-major',['student_ID'=>'3','major_ID'=>'1'],'duplicate primary major');
denied('assign-advisor',['student_ID'=>'4','faculty_ID'=>'2'],'part-time advisor');
$pdo->exec("INSERT INTO Hold VALUES (1,'Test');INSERT INTO Student_Hold VALUES (1,4,'2026-11-19')");
denied('add-student-section',['student_ID'=>'4','CRN'=>$crn],'hold');
$now=new DateTimeImmutable('2027-01-01');denied('add-student-section',['student_ID'=>'5','CRN'=>$crn],'outside add/drop window');
$_ENV['ADMIN_FEATURE_WRITER_IDS']='';denied('create-course',$course,'read-only role');
ok((int)$pdo->query('SELECT COUNT(*) FROM Admin_Action_Log')->fetchColumn()>=25,'audit log persisted atomically');
echo "PASS: $checks Admin persistence, permission, validation and rollback assertions on isolated MySQL fixture.\n";
