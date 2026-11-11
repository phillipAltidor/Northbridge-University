<?php
declare(strict_types=1);
/** Read-only schema/relationship audit. Aggregate output only; no personal records. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/config/database.php';
require dirname(__DIR__).'/includes/schedule.php';
$timeline=require dirname(__DIR__).'/config/academic-timeline.php';
$failures=[];$checks=0;
$check=static function(string $label, int $violations) use (&$failures,&$checks):void {
    $checks++; if ($violations) $failures[$label]=$violations;
};
$count=static fn(string $sql):int=>(int)$pdo->query($sql)->fetchColumn();
$ident=static function(string $name):string {
    if (!preg_match('/^[A-Za-z0-9_]+$/D',$name)) throw new RuntimeException('Unexpected identifier.');
    return '`'.$name.'`';
};
$tables=$pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
$before=[];foreach ($tables as $table) $before[$table]=$pdo->query('CHECKSUM TABLE '.$ident($table).' EXTENDED')->fetch()['Checksum'];
$keys=$pdo->query('SELECT TABLE_NAME,CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION')->fetchAll();
$groups=[];foreach ($keys as $key) $groups[$key['TABLE_NAME'].'.'.$key['CONSTRAINT_NAME']][]=$key;
foreach ($groups as $name=>$columns) {
    $join=[];$present=[];foreach ($columns as $key) {
        $join[]='p.'.$ident($key['REFERENCED_COLUMN_NAME']).'=c.'.$ident($key['COLUMN_NAME']);
        $present[]='c.'.$ident($key['COLUMN_NAME']).' IS NOT NULL';
    }
    $check('foreign key '.$name,$count('SELECT COUNT(*) FROM '.$ident($columns[0]['TABLE_NAME']).' c WHERE '.implode(' AND ',$present).' AND NOT EXISTS(SELECT 1 FROM '.$ident($columns[0]['REFERENCED_TABLE_NAME']).' p WHERE '.implode(' AND ',$join).')'));
}
$queries=[
    'Every user has a login'=>'SELECT COUNT(*) FROM User u WHERE NOT EXISTS(SELECT 1 FROM Login l WHERE l.user_ID=u.user_ID)',
    'Login and User roles agree'=>'SELECT COUNT(*) FROM Login l JOIN User u ON u.user_ID=l.user_ID WHERE l.user_Type<>u.user_Type',
    'Student roles agree'=>"SELECT COUNT(*) FROM Student s JOIN User u ON u.user_ID=s.student_ID WHERE u.user_Type<>'Student'",
    'Faculty roles agree'=>"SELECT COUNT(*) FROM Faculty f JOIN User u ON u.user_ID=f.faculty_ID WHERE u.user_Type<>'Faculty'",
    'Student academic level subtype agrees'=>"SELECT COUNT(*) FROM Student s LEFT JOIN Undergraduate u ON u.student_ID=s.student_ID LEFT JOIN Graduate_Student g ON g.student_ID=s.student_ID WHERE NOT ((s.student_Type='Undergraduate' AND u.student_ID IS NOT NULL AND g.student_ID IS NULL) OR (s.student_Type='Graduate' AND g.student_ID IS NOT NULL AND u.student_ID IS NULL))",
    'Undergraduate attendance subtype agrees'=>"SELECT COUNT(*) FROM Undergraduate u LEFT JOIN Full_Time_Undergraduate ft ON ft.student_ID=u.student_ID LEFT JOIN Part_Time_Undergraduate pt ON pt.student_ID=u.student_ID WHERE NOT ((u.undergraduate_student_Type='Full-Time' AND ft.student_ID IS NOT NULL AND pt.student_ID IS NULL) OR (u.undergraduate_student_Type='Part-Time' AND pt.student_ID IS NOT NULL AND ft.student_ID IS NULL))",
    'Graduate attendance subtype agrees'=>"SELECT COUNT(*) FROM Graduate_Student g LEFT JOIN Full_Time_Graduate ft ON ft.student_ID=g.student_ID LEFT JOIN Part_Time_Graduate pt ON pt.student_ID=g.student_ID WHERE NOT ((g.graduate_Student_Type='Full-Time' AND ft.student_ID IS NOT NULL AND pt.student_ID IS NULL) OR (g.graduate_Student_Type='Part-Time' AND pt.student_ID IS NOT NULL AND ft.student_ID IS NULL))",
    'Faculty employment subtype agrees'=>"SELECT COUNT(*) FROM Faculty f LEFT JOIN Full_Time_Faculty ft ON ft.faculty_ID=f.faculty_ID LEFT JOIN Part_Time_Faculty pt ON pt.faculty_ID=f.faculty_ID WHERE NOT ((f.faculty_Type='Full-Time' AND ft.faculty_ID IS NOT NULL AND pt.faculty_ID IS NULL) OR (f.faculty_Type='Part-Time' AND pt.faculty_ID IS NOT NULL AND ft.faculty_ID IS NULL))",
    'Enrollment semester matches section'=>'SELECT COUNT(*) FROM Enrollment e JOIN Course_Section c ON c.CRN=e.CRN WHERE e.semester_ID<>c.semester_ID',
    'History course and semester match section'=>'SELECT COUNT(*) FROM Student_History h JOIN Course_Section c ON c.CRN=h.CRN WHERE h.semester_ID<>c.semester_ID OR h.course_ID<>c.course_ID',
    'History grades match enrollment'=>'SELECT COUNT(*) FROM Student_History h LEFT JOIN Enrollment e ON e.CRN=h.CRN AND e.student_ID=h.student_ID WHERE e.student_ID IS NULL OR NOT(e.Grade<=>h.Grade)',
    'Faculty history matches section'=>'SELECT COUNT(*) FROM Faculty_History h JOIN Course_Section c ON c.CRN=h.CRN WHERE h.semester_ID<>c.semester_ID OR h.course_ID<>c.course_ID OR h.faculty_ID<>c.faculty_ID',
    'Attendance course matches section'=>'SELECT COUNT(*) FROM Attendance a JOIN Course_Section c ON c.CRN=a.CRN WHERE a.course_ID<>c.course_ID',
    'Attendance belongs to enrollment'=>'SELECT COUNT(*) FROM Attendance a WHERE NOT EXISTS(SELECT 1 FROM Enrollment e WHERE e.CRN=a.CRN AND e.student_ID=a.student_ID)',
    'Faculty department limits'=>"SELECT COUNT(*) FROM (SELECT f.faculty_ID FROM Faculty f LEFT JOIN Faculty_Department d ON d.faculty_ID=f.faculty_ID GROUP BY f.faculty_ID,f.faculty_Type HAVING COUNT(d.dept_ID)>IF(f.faculty_Type='Full-Time',3,1)) x",
    'Faculty semester load limits'=>"SELECT COUNT(*) FROM (SELECT c.faculty_ID,c.semester_ID FROM Course_Section c JOIN Faculty f ON f.faculty_ID=c.faculty_ID GROUP BY c.faculty_ID,c.semester_ID,f.faculty_Type HAVING COUNT(*)>IF(f.faculty_Type='Full-Time',2,1)) x",
];
foreach ($queries as $label=>$sql) $check($label,$count($sql));
if (academic_portal_enabled($pdo)) {
    $check('Rebuilt course credits and level agree with Course',$count("SELECT COUNT(*) FROM NB_AcademicCourse a JOIN Course c ON c.course_ID=a.course_ID WHERE c.course_Credits<>CAST(a.definition_JSON->>'$.credits' AS DECIMAL(6,2)) OR c.course_Type<>'Undergraduate'"));
    $check('Every undergraduate section has source metadata',$count("SELECT COUNT(*) FROM Course_Section s JOIN Course c ON c.course_ID=s.course_ID LEFT JOIN NB_AcademicSection a ON a.CRN=s.CRN WHERE c.course_Type='Undergraduate' AND a.CRN IS NULL"));
    $check('Every undergraduate has registrar metadata',$count("SELECT COUNT(*) FROM Student s LEFT JOIN NB_AcademicStudent a ON a.student_ID=s.student_ID WHERE s.student_Type='Undergraduate' AND a.student_ID IS NULL"));
}
$q=$pdo->prepare('SELECT semester_ID FROM Semester WHERE semester_Name=?');$q->execute([$timeline['current_semester']]);$current=(int)$q->fetchColumn();
foreach (['Full_Time_Faculty','Part_Time_Faculty'] as $table) $check($table.' current class count',$count('SELECT COUNT(*) FROM '.$ident($table).' f WHERE f.no_of_Classes<>(SELECT COUNT(*) FROM Course_Section c WHERE c.faculty_ID=f.faculty_ID AND c.semester_ID='.$current.')'));
$relation=academic_portal_meeting_relation($pdo);
$check('Attendance falls on an exact meeting date',$count("SELECT COUNT(*) FROM Attendance a WHERE NOT EXISTS(SELECT 1 FROM $relation m WHERE m.CRN=a.CRN AND m.day_Of_Week=ELT(WEEKDAY(a.attendance_Date)+1,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') AND (m.start_Date IS NULL OR a.attendance_Date>=m.start_Date) AND (m.end_Date IS NULL OR a.attendance_Date<=m.end_Date))"));
$check('No future attendance',$count('SELECT COUNT(*) FROM Attendance WHERE attendance_Date>'.$pdo->quote($timeline['as_of'])));
$capacityErrors=0;$seatErrors=0;$sections=0;
$stored=$pdo->query('SELECT CRN,available_Seats FROM Course_Section')->fetchAll(PDO::FETCH_KEY_PAIR);
foreach ($pdo->query('SELECT semester_ID FROM Semester')->fetchAll(PDO::FETCH_COLUMN) as $term) foreach (schedule_sections($pdo,(int)$term) as $section) {
    $sections++;$cap=(int)$section['maximum_Seats'];
    if ($cap<1 || $cap>10 || $section['available_Seats']<0) $capacityErrors++;
    if ((int)$stored[$section['CRN']]!==$section['available_Seats']) $seatErrors++;
}
$check('Valid section capacities',$capacityErrors);$check('Stored seats equal capacity minus distinct roster',$seatErrors);
$check('Schedule joins preserve all sections',abs($sections-$count('SELECT COUNT(*) FROM Course_Section')));
$definitions=[];foreach ($tables as $table) $definitions[$table]=preg_replace('/ AUTO_INCREMENT=\d+/','',$pdo->query('SHOW CREATE TABLE '.$ident($table))->fetch(PDO::FETCH_NUM)[1]);ksort($definitions);
$schemaHash=hash('sha256',json_encode($definitions,JSON_THROW_ON_ERROR));
$triggers=[];foreach ($pdo->query('SHOW TRIGGERS') as $trigger) $triggers[$trigger['Trigger']]=[$trigger['Table'],$trigger['Timing'],$trigger['Event'],$trigger['Statement']];ksort($triggers);
$triggerHash=hash('sha256',json_encode($triggers,JSON_THROW_ON_ERROR));
$after=[];foreach ($tables as $table) $after[$table]=$pdo->query('CHECKSUM TABLE '.$ident($table).' EXTENDED')->fetch()['Checksum'];
$check('Audit leaves all tables unchanged',$before===$after?0:1);
echo json_encode(['database'=>$database,'tablesChecked'=>count($tables),'foreignKeysChecked'=>count($groups),'sectionsChecked'=>$sections,'checks'=>$checks,'failures'=>$failures,'schemaDefinitionSHA256'=>$schemaHash,'triggersSHA256'=>$triggerHash,'readOnly'=>$before===$after,'personalRecordsPublished'=>false],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
exit($failures?1:0);
