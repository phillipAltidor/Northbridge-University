<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('memory_limit','512M');
function insert_rows(PDO $connection, string $table, array $rows): void {
    if (!$rows) return;
    $columns = array_keys($rows[0]);
    $prefix = "INSERT INTO `$table` (" . implode(',',array_map(static fn($column)=>"`$column`",$columns)) . ') VALUES ';
    $placeholders = '(' . implode(',',array_fill(0,count($columns),'?')) . ')';
    foreach (array_chunk($rows,250) as $batch) {
        $values=[];
        foreach($batch as $row) array_push($values,...array_values($row));
        $stmt=$connection->prepare($prefix.implode(',',array_fill(0,count($batch),$placeholders)));
        $stmt->execute($values);
    }
}
function audit(PDO $connection, array $source, array $plan): array {
    $target=(int)($plan['target_id']??3);
    $range=implode(',',array_unique([11,12,13,14,3,$target]));
    $termName=$connection->query("SELECT semester_Name FROM Semester WHERE semester_ID=$target")->fetchColumn();
    $registrations='(SELECT student_ID,CRN,semester_ID FROM Student_History UNION SELECT student_ID,CRN,semester_ID FROM Enrollment)';
    $start=substr($termName,-4).(str_starts_with($termName,'Fall')?'-09-01':'-02-01');
    $checks = [
        'foreign_key_orphans'=>0,
        'history_section_mismatches'=>(int)$connection->query('SELECT COUNT(*) FROM Student_History h JOIN Course_Section c ON c.CRN=h.CRN WHERE h.course_ID<>c.course_ID OR h.semester_ID<>c.semester_ID')->fetchColumn(),
        'faculty_history_mismatches'=>(int)$connection->query('SELECT COUNT(*) FROM Faculty_History h JOIN Course_Section c ON c.CRN=h.CRN WHERE h.course_ID<>c.course_ID OR h.semester_ID<>c.semester_ID OR h.faculty_ID<>c.faculty_ID')->fetchColumn(),
        'repeated_completed_courses'=>(int)$connection->query('SELECT COUNT(*) FROM (SELECT student_ID,course_ID FROM Student_History GROUP BY student_ID,course_ID HAVING COUNT(*)>1) x')->fetchColumn(),
        'roster_or_seat_errors'=>(int)$connection->query("SELECT COUNT(*) FROM (SELECT c.CRN,c.available_Seats,COUNT(h.student_ID) roster FROM Course_Section c LEFT JOIN $registrations h ON h.CRN=c.CRN WHERE c.semester_ID IN($range) GROUP BY c.CRN,c.available_Seats HAVING roster<5 OR roster>10 OR c.available_Seats<>10-roster) x")->fetchColumn(),
        'faculty_load_errors'=>(int)$connection->query("SELECT COUNT(*) FROM (SELECT c.faculty_ID,c.semester_ID,f.faculty_Type,COUNT(*) n FROM Course_Section c JOIN Faculty f ON f.faculty_ID=c.faculty_ID WHERE c.semester_ID IN($range) GROUP BY c.faculty_ID,c.semester_ID,f.faculty_Type HAVING n>CASE WHEN faculty_Type='Full-Time' THEN 2 ELSE 1 END) x")->fetchColumn(),
        'faculty_department_errors'=>(int)$connection->query('SELECT COUNT(*) FROM Course_Section cs JOIN Course c ON c.course_ID=cs.course_ID LEFT JOIN Faculty_Department fd ON fd.faculty_ID=cs.faculty_ID AND fd.dept_ID=c.dept_ID WHERE fd.faculty_ID IS NULL')->fetchColumn(),
        'fall2022_type_errors'=>(int)$connection->query('SELECT COUNT(*) FROM Student_History h JOIN Student s ON s.student_ID=h.student_ID JOIN Course c ON c.course_ID=h.course_ID WHERE h.semester_ID=3 AND s.student_Type<>c.course_Type')->fetchColumn(),
        'original_account_changes'=>0,
        'preserved_course_changes'=>0,
        'preserved_section_changes'=>0,
        'protected_fall2026_changes'=>0,
        'cohort_profile_errors'=>0,
        'generated_age_errors'=>(int)$connection->query("SELECT COUNT(*) FROM Student_History h JOIN User u ON u.user_ID=h.student_ID JOIN Semester s ON s.semester_ID=h.semester_ID WHERE h.student_ID>=700001 AND DATE_ADD(u.DOB,INTERVAL 18 YEAR)>STR_TO_DATE(CONCAT(SUBSTRING_INDEX(s.semester_Name,' ',-1),CASE WHEN s.semester_Name LIKE 'Fall%' THEN '-09-01' ELSE '-02-01' END),'%Y-%m-%d')")->fetchColumn(),
        'target_appointment_errors'=>(int)$connection->query("SELECT COUNT(*) FROM Course_Section cs JOIN Course c ON c.course_ID=cs.course_ID JOIN Faculty_Department fd ON fd.faculty_ID=cs.faculty_ID AND fd.dept_ID=c.dept_ID LEFT JOIN Part_Time_Faculty p ON p.faculty_ID=cs.faculty_ID WHERE cs.semester_ID=$target AND (fd.date_Of_Appointment>'$start' OR p.DOA>'$start')")->fetchColumn(),
        'target_room_duration_errors'=>(int)$connection->query("SELECT COUNT(*) FROM Course_Section c JOIN Room r ON r.room_ID=c.room_ID JOIN Course x ON x.course_ID=c.course_ID JOIN Time_Slot_Period t ON t.time_Slot_ID=c.time_Slot_ID JOIN Period p ON p.period_ID=t.period_ID WHERE c.semester_ID=$target AND (TIME_TO_SEC(p.end_Time)-TIME_TO_SEC(p.start_Time))/60<>CASE WHEN x.course_Credits=3 THEN 90 WHEN r.room_Type='Lab' THEN 180 ELSE 120 END")->fetchColumn(),
        'generated_credit_errors'=>(int)$connection->query("SELECT COUNT(*) FROM (SELECT student_ID,credits_Earned FROM Full_Time_Undergraduate UNION ALL SELECT student_ID,credits_Earned FROM Part_Time_Undergraduate UNION ALL SELECT student_ID,credits_Earned FROM Full_Time_Graduate UNION ALL SELECT student_ID,credits_Earned FROM Part_Time_Graduate) g LEFT JOIN (SELECT h.student_ID,SUM(c.course_Credits) credits FROM Student_History h JOIN Course c ON c.course_ID=h.course_ID WHERE h.Grade IN('A+','A','A-','B+','B','B-','C+','C') GROUP BY h.student_ID) x ON x.student_ID=g.student_ID WHERE g.student_ID>=700001 AND g.credits_Earned<>COALESCE(x.credits,0)")->fetchColumn(),
    ];
    $groups = [];
    foreach ($connection->query('SELECT TABLE_NAME,CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION') as $fk) $groups[$fk['TABLE_NAME'].'.'.$fk['CONSTRAINT_NAME']][]=$fk;
    foreach ($groups as $parts) {
        $conditions=$nonnull=[];
        foreach($parts as $fk) {$conditions[]="c.`{$fk['COLUMN_NAME']}`=p.`{$fk['REFERENCED_COLUMN_NAME']}`";$nonnull[]="c.`{$fk['COLUMN_NAME']}` IS NOT NULL";}
        $fk=$parts[0];
        $checks['foreign_key_orphans']+=(int)$connection->query("SELECT COUNT(*) FROM `{$fk['TABLE_NAME']}` c LEFT JOIN `{$fk['REFERENCED_TABLE_NAME']}` p ON ".implode(' AND ',$conditions).' WHERE '.implode(' AND ',$nonnull)." AND p.`{$fk['REFERENCED_COLUMN_NAME']}` IS NULL")->fetchColumn();
    }
    foreach(['User'=>'user_ID','Login'=>'user_ID'] as $table=>$key) {
        $rows=[];foreach($connection->query("SELECT * FROM `$table`") as $row)$rows[$row[$key]]=$row;
        foreach($source[$table] as $row) if(($rows[$row[$key]]??null)!==$row)$checks['original_account_changes']++;
    }
    $preserved=[];
    foreach($connection->query('SELECT student_ID,course_ID,Grade FROM Student_History WHERE semester_ID=3') as $row) $preserved[$row['student_ID'].':'.$row['course_ID']]=$row['Grade'];
    foreach($source['Student_History'] as $row) if($row['semester_ID']===3 && ($preserved[$row['student_ID'].':'.$row['course_ID']]??null)!==$row['Grade']) $checks['preserved_course_changes']++;
    $sections=[];
    foreach($connection->query('SELECT * FROM Course_Section') as $row) $sections[$row['CRN']]=$row;
    foreach($source['Course_Section'] as $row) if($row['semester_ID']!==$target && ($sections[$row['CRN']]??null)!==$row) $checks['preserved_section_changes']++;
    $protectedCrns=array_column(array_filter($source['Course_Section'],static fn($row)=>$row['semester_ID']===1),'CRN');
    foreach(['Course_Section','Enrollment','Student_History','Faculty_History','Attendance','Master_Schedule','Course_Section_Update_Log'] as $table) {
        $filter=static fn($row)=>isset($row['semester_ID'])?$row['semester_ID']===1:in_array($row['CRN']??null,$protectedCrns,true);
        $before=array_map('json_encode',array_values(array_filter($source[$table],$filter)));
        $after=array_map('json_encode',array_values(array_filter($connection->query("SELECT * FROM `$table`")->fetchAll(),$filter)));
        sort($before);sort($after);
        if($before!==$after) $checks['protected_fall2026_changes']++;
    }
    if(isset($plan['cohort_ids'])) {
        $ids=implode(',',array_map('intval',$plan['cohort_ids']));
        $checks['cohort_profile_errors']=(int)$connection->query("SELECT COUNT(*) FROM Student s LEFT JOIN Undergraduate u ON u.student_ID=s.student_ID LEFT JOIN Graduate_Student g ON g.student_ID=s.student_ID LEFT JOIN Full_Time_Undergraduate f ON f.student_ID=s.student_ID LEFT JOIN Part_Time_Undergraduate p ON p.student_ID=s.student_ID LEFT JOIN Full_Time_Graduate h ON h.student_ID=s.student_ID LEFT JOIN Part_Time_Graduate q ON q.student_ID=s.student_ID WHERE s.student_ID IN($ids) AND ((u.student_ID IS NOT NULL)+(g.student_ID IS NOT NULL)<>1 OR (f.student_ID IS NOT NULL)+(p.student_ID IS NOT NULL)+(h.student_ID IS NOT NULL)+(q.student_ID IS NOT NULL)<>1)")->fetchColumn();
        if((int)$connection->query("SELECT COUNT(*) FROM Student WHERE student_ID IN($ids)")->fetchColumn()!==1600) $checks['cohort_profile_errors']++;
    }
    $records="WITH records AS (SELECT h.student_ID,h.course_ID,h.Grade,CAST(SUBSTRING_INDEX(s.semester_Name,' ',-1) AS UNSIGNED)*2+CASE WHEN s.semester_Name LIKE 'Fall%' THEN 1 ELSE 0 END term FROM Student_History h JOIN Semester s ON s.semester_ID=h.semester_ID UNION ALL SELECT e.student_ID,c.course_ID,e.Grade,CAST(SUBSTRING_INDEX(s.semester_Name,' ',-1) AS UNSIGNED)*2+CASE WHEN s.semester_Name LIKE 'Fall%' THEN 1 ELSE 0 END FROM Enrollment e JOIN Course_Section c ON c.CRN=e.CRN JOIN Semester s ON s.semester_ID=e.semester_ID) ";
    $checks['prerequisite_errors']=(int)$connection->query($records."SELECT COUNT(*) FROM records r JOIN Course_Prerequisite p ON p.course_ID=r.course_ID WHERE NOT EXISTS(SELECT 1 FROM records prior WHERE prior.student_ID=r.student_ID AND prior.course_ID=p.prerequisite_course_ID AND prior.term<r.term AND prior.Grade IN('A+','A','A-','B+','B','B-','C+','C'))")->fetchColumn();
    $meetings='WITH meetings AS (SELECT c.CRN,c.semester_ID,c.faculty_ID,c.room_ID,d.day_ID,p.start_Time,p.end_Time FROM Course_Section c JOIN Time_Slot_Day d ON d.time_Slot_ID=c.time_Slot_ID JOIN Time_Slot_Period t ON t.time_Slot_ID=c.time_Slot_ID JOIN Period p ON p.period_ID=t.period_ID) ';
    foreach(['faculty'=>'faculty_ID','room'=>'room_ID'] as $label=>$column) $checks[$label.'_conflicts']=(int)$connection->query($meetings."SELECT COUNT(*) FROM meetings a JOIN meetings b ON a.CRN<b.CRN AND a.semester_ID=b.semester_ID AND a.`$column`=b.`$column` AND a.day_ID=b.day_ID AND a.start_Time<b.end_Time AND b.start_Time<a.end_Time")->fetchColumn();
    $checks['student_conflicts']=(int)$connection->query('WITH registrations AS (SELECT student_ID,CRN FROM Student_History UNION SELECT student_ID,CRN FROM Enrollment), meetings AS (SELECT r.student_ID,c.CRN,c.semester_ID,d.day_ID,p.start_Time,p.end_Time FROM registrations r JOIN Course_Section c ON c.CRN=r.CRN JOIN Time_Slot_Day d ON d.time_Slot_ID=c.time_Slot_ID JOIN Time_Slot_Period t ON t.time_Slot_ID=c.time_Slot_ID JOIN Period p ON p.period_ID=t.period_ID) SELECT COUNT(*) FROM meetings a JOIN meetings b ON a.student_ID=b.student_ID AND a.CRN<b.CRN AND a.semester_ID=b.semester_ID AND a.day_ID=b.day_ID AND a.start_Time<b.end_Time AND b.start_Time<a.end_Time')->fetchColumn();
    $checks['target_credit_load_errors']=(int)$connection->query("WITH loads AS (SELECT h.student_ID,SUM(c.course_Credits) credits FROM $registrations h JOIN Course_Section cs ON cs.CRN=h.CRN JOIN Course c ON c.course_ID=cs.course_ID WHERE h.semester_ID=$target GROUP BY h.student_ID) SELECT COUNT(*) FROM loads l LEFT JOIN Full_Time_Undergraduate u ON u.student_ID=l.student_ID LEFT JOIN Part_Time_Undergraduate p ON p.student_ID=l.student_ID LEFT JOIN Full_Time_Graduate g ON g.student_ID=l.student_ID LEFT JOIN Part_Time_Graduate q ON q.student_ID=l.student_ID WHERE (u.student_ID IS NOT NULL AND l.credits NOT BETWEEN u.min_Credits AND u.max_Credits) OR(p.student_ID IS NOT NULL AND l.credits NOT BETWEEN p.min_Credits AND p.max_Credits) OR(g.student_ID IS NOT NULL AND l.credits<>12) OR(q.student_ID IS NOT NULL AND l.credits NOT BETWEEN 4 AND 8)")->fetchColumn();
    $checks['target_type_errors']=(int)$connection->query("SELECT COUNT(*) FROM $registrations h JOIN Student s ON s.student_ID=h.student_ID JOIN Course_Section cs ON cs.CRN=h.CRN JOIN Course c ON c.course_ID=cs.course_ID WHERE h.semester_ID=$target AND s.student_Type<>c.course_Type")->fetchColumn();
    foreach($checks as $key=>$value) if($value!==0) throw new RuntimeException("Audit failed: $key = $value");
    $summary=$connection->query("SELECT COUNT(DISTINCT c.CRN) sections,COUNT(DISTINCT c.course_ID) courses,COUNT(DISTINCT h.student_ID) registered_students,COUNT(h.student_ID) registrations FROM Course_Section c LEFT JOIN $registrations h ON h.CRN=c.CRN WHERE c.semester_ID=$target")->fetch();
    $maximum=isset($plan['target_id'])?600:430;
    if((int)$summary['registered_students']!==$plan['registered_students'] || (int)$summary['sections']<330 || (int)$summary['sections']>$maximum || (int)$summary['courses']<100 || (int)$summary['courses']>240) throw new RuntimeException('Expanded schedule is not comparable to the baseline.');
    return ['target'=>$termName,'population'=>$plan['population'],'summary'=>$summary,'foreign_keys_checked'=>count($groups),'checks'=>$checks,'result'=>'PASS'];
}
if (defined('NORTHBRIDGE_AUDIT_ONLY')) return;
require dirname(__DIR__, 2) . '/config/database.php';
if ($host !== '127.0.0.1' || $database !== 'northbridge_university') throw new RuntimeException('Expected local database.');
$source = json_decode(file_get_contents('/private/tmp/northbridge-source.json'), true, 512, JSON_THROW_ON_ERROR);
$plan = json_decode(file_get_contents('/private/tmp/northbridge-fall2022-plan.json'), true, 512, JSON_THROW_ON_ERROR);
$hashes=[];
if(in_array('--reuse-hashes',$argv,true) && is_file('/private/tmp/northbridge-fall2022-approved-plan.json')) {
    $previous=json_decode(file_get_contents('/private/tmp/northbridge-fall2022-approved-plan.json'),true,512,JSON_THROW_ON_ERROR);
    if($previous['source_digest']!==$plan['source_digest']) throw new RuntimeException('Cannot reuse hashes from a different source snapshot.');
    foreach($previous['replacement_tables']['Login'] as $row) if($row['lock_var']===1 && password_get_info($row['user_Password'])['algo']!==null) $hashes[$row['user_ID']]=$row['user_Password'];
    unset($previous);
}
$name = 'northbridge_fall2022_stage_' . date('Ymd_His');
$pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4");
$stage = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
try {
    $stage->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach($source as $table=>$rows) {$ddl=$pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];$stage->exec($ddl);insert_rows($stage,$table,$rows);}
    foreach($plan['replacement_tables'] as $table=>&$rows) {
        if($table==='Login') foreach($rows as &$row) if($row['user_Password']===null) $row['user_Password']=$hashes[$row['user_ID']]??password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT);
        unset($row);
        $stage->exec("DELETE FROM `$table`");insert_rows($stage,$table,$rows);
    }
    unset($rows);
    $stage->exec('SET FOREIGN_KEY_CHECKS=1');
    $report=audit($stage,$source,$plan);
    file_put_contents('/private/tmp/northbridge-fall2022-approved-plan.json',json_encode($plan,JSON_THROW_ON_ERROR));chmod('/private/tmp/northbridge-fall2022-approved-plan.json',0600);
    file_put_contents('/private/tmp/northbridge-fall2022-stage.json',json_encode(['database'=>$name,'report'=>$report],JSON_THROW_ON_ERROR));
    echo json_encode(['staging_database'=>$name,'audit'=>$report],JSON_PRETTY_PRINT),PHP_EOL;
} catch(Throwable $error) {
    $pdo->exec("DROP DATABASE `$name`");
    throw $error;
}
