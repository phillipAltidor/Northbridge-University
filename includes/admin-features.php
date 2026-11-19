<?php
declare(strict_types=1);
require_once __DIR__.'/department-management.php';
require_once __DIR__.'/schedule.php';
require_once __DIR__.'/academic/evaluator.php';

function af_query(PDO $db,string $sql,array $args=[]): PDOStatement { $q=$db->prepare($sql);$q->execute($args);return $q; }
function af_row(PDO $db,string $table,string $key,mixed $id,bool $lock=false): array {
 $r=af_query($db,"SELECT * FROM `$table` WHERE `$key`=?".($lock?' FOR UPDATE':''),[$id])->fetch(PDO::FETCH_ASSOC);
 if (!$r) throw new DomainException('The selected record no longer exists.'); return $r;
}
function af_version(array $rows): string { return hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR)); }
function af_check_version(array $rows,array $input): void {
 if (!is_string($input['version']??null) || !hash_equals(af_version($rows),$input['version'])) throw new DomainException('This record changed. Load it again before saving.');
}
function af_id(array $in,string $key): int {return department_positive_id($in[$key]??null,str_replace('_',' ',$key));}
function af_text(array $in,string $key,int $max=100): string {return department_text($in,$key,str_replace('_',' ',$key),$max);}
function af_grade(array $in,string $key='Grade'): string {
 $g=af_text($in,$key,5); if (!array_key_exists($g,Northbridge\Academic\Transcript::POINTS)) throw new DomainException('Select a letter grade from the list.');return $g;
}
function af_can_write(int $id): bool {
 $raw=$_ENV['ADMIN_FEATURE_WRITER_IDS']??getenv('ADMIN_FEATURE_WRITER_IDS');
 if (!is_string($raw)||!preg_match('/^[1-9][0-9]*(?:\s*,\s*[1-9][0-9]*)*$/D',trim($raw))) return false;
 return in_array((string)$id,array_map('trim',explode(',',$raw)),true);
}
function af_window(string $term,DateTimeImmutable $now): void {
 $windows=require __DIR__.'/../config/admin-windows.php';$w=$windows[$term]??null;
 $date=$now->setTimezone(new DateTimeZone('America/New_York'))->format('Y-m-d');
 if (!$w || $date<$w['start'] || $date>$w['end']) throw new DomainException('Add/drop is closed for this semester. Approved dates are shown below.');
}
function af_snapshot(PDO $db,string $mode,array $in,bool $lock=false): array {
 $map=['update-course'=>['Course','course_ID'],'update-major'=>['Major','major_ID'],'update-minor'=>['Minor','minor_ID'],'update-building'=>['Building','Bldg_ID'],
 'update-section'=>['Course_Section','CRN'],'add-faculty-section'=>['Course_Section','CRN'],'update-faculty-section'=>['Course_Section','CRN']];
 if (isset($map[$mode])) {[$t,$k]=$map[$mode];return af_row($db,$t,$k,$in[$k]??'', $lock);}
 if (str_contains($mode,'requirements')) {
  $kind=str_contains($mode,'major')?'Major':'Minor';$key=strtolower($kind).'_ID';
  $parent=af_row($db,$kind,$key,$in[$key]??'', $lock);
  return ['program'=>$parent,'requirements'=>af_query($db,"SELECT * FROM {$kind}_Requirements WHERE `$key`=? ORDER BY course_ID",[$parent[$key]])->fetchAll(PDO::FETCH_ASSOC)];
 }
 if ($mode==='add-prerequisites') { $course=af_row($db,'Course','course_ID',$in['course_ID']??'', $lock);return ['course'=>$course,'prerequisites'=>af_query($db,'SELECT * FROM Course_Prerequisite WHERE course_ID=? ORDER BY prerequisite_course_ID',[$course['course_ID']])->fetchAll(PDO::FETCH_ASSOC)]; }
 if (in_array($mode,['add-student-section','drop-student-section','update-student-grade','change-grade','declare-major','add-minor','assign-advisor'],true)) {
  $s=af_row($db,'Student','student_ID',$in['student_ID']??'', $lock);$id=$s['student_ID'];
  if (isset($in['CRN'])) return ['student'=>$s,'section'=>af_row($db,'Course_Section','CRN',$in['CRN'],$lock),'enrollment'=>af_query($db,'SELECT * FROM Enrollment WHERE student_ID=? AND CRN=?',[$id,$in['CRN']])->fetchAll(PDO::FETCH_ASSOC),'history'=>af_query($db,'SELECT * FROM Student_History WHERE student_ID=? AND CRN=?',[$id,$in['CRN']])->fetchAll(PDO::FETCH_ASSOC)];
  return ['student'=>$s,'majors'=>af_query($db,'SELECT * FROM Student_Major WHERE student_ID=? ORDER BY major_ID',[$id])->fetchAll(PDO::FETCH_ASSOC),'minors'=>af_query($db,'SELECT * FROM Student_Minor WHERE student_ID=? ORDER BY minor_ID',[$id])->fetchAll(PDO::FETCH_ASSOC),'advisors'=>af_query($db,'SELECT * FROM Advisor WHERE student_ID=? ORDER BY faculty_ID',[$id])->fetchAll(PDO::FETCH_ASSOC)];
 }
 return [];
}
function af_managed_course(PDO $db,string $course): void {
 if (academic_portal_enabled($db)&&af_query($db,'SELECT 1 FROM NB_AcademicCourse WHERE course_ID=?',[$course])->fetchColumn()) throw new DomainException('This course uses a reviewed, versioned academic definition. Edit that definition through the academic review workflow before changing its catalog or prerequisites.');
}
function af_managed_program(PDO $db,array $program,string $kind): void {
 if (!academic_portal_enabled($db)) return;
 // Reviewed curricula are versioned independently of legacy requirement rows.
 $rules=json_decode(file_get_contents(__DIR__.'/../database/staging/academic-alignment/rules/curricula.json'),true,512,JSON_THROW_ON_ERROR);
 foreach ($rules['programs'] as $p) if ($p['program']===($program[strtolower($kind).'_Name']??null)) throw new DomainException('This program uses reviewed curriculum rules. Its versioned requirements must be updated through academic review so degree audits stay consistent.');
}
function af_meetings(PDO $db,array $s,bool $existing=false): array {
 if ($existing) { $rel=academic_portal_meeting_relation($db);return af_query($db,"SELECT * FROM $rel m WHERE CRN=?",[$s['CRN']])->fetchAll(PDO::FETCH_ASSOC); }
 return af_query($db,'SELECT d.day_Of_Week,p.start_Time,p.end_Time,NULL AS start_Date,NULL AS end_Date FROM Time_Slot_Day td JOIN Day d ON d.day_ID=td.day_ID JOIN Time_Slot_Period tp ON tp.time_Slot_ID=td.time_Slot_ID JOIN Period p ON p.period_ID=tp.period_ID WHERE td.time_Slot_ID=?',[$s['time_Slot_ID']])->fetchAll(PDO::FETCH_ASSOC);
}
function af_validate_section(PDO $db,array $s,?array $before,DateTimeImmutable $now): void {
 $term=af_row($db,'Semester','semester_ID',$s['semester_ID']);$windows=require __DIR__.'/../config/admin-windows.php';$w=$windows[$term['semester_Name']]??null;
 $date=$now->setTimezone(new DateTimeZone('America/New_York'))->format('Y-m-d');
 if (!$w || $date>$w['end']) throw new DomainException('Sections can only be managed in a configured current or upcoming semester.');
 if ($before && $date >= $w['start'] && $s['time_Slot_ID']!=$before['time_Slot_ID']) throw new DomainException('A section time slot cannot change after the semester starts.');
 $course=af_row($db,'Course','course_ID',$s['course_ID']);$faculty=af_row($db,'Faculty','faculty_ID',$s['faculty_ID'],true);
 if (!af_query($db,'SELECT 1 FROM Faculty_Department WHERE faculty_ID=? AND dept_ID=?',[$s['faculty_ID'],$course['dept_ID']])->fetchColumn()) throw new DomainException('Faculty must belong to this course’s department.');
 $limit=['Full-Time'=>2,'Part-Time'=>1][$faculty['faculty_Type']]??0;
 if (!$limit || (int)af_query($db,'SELECT COUNT(*) FROM Course_Section WHERE faculty_ID=? AND semester_ID=? AND CRN<>?',[$s['faculty_ID'],$s['semester_ID'],$s['CRN']])->fetchColumn()>=$limit) throw new DomainException('Faculty teaching load exceeded (full-time: two; part-time: one per semester).');
 if ($before && $before['faculty_ID']!=$s['faculty_ID']) {
  $old=af_row($db,'Faculty','faculty_ID',$before['faculty_ID'],true);
  if ($old['faculty_Type']==='Part-Time' && $date >= $w['start']) throw new DomainException('Replacing this instructor would leave part-time faculty without their required current-semester section.');
 }
 $room=af_row($db,'Room','room_ID',$s['room_ID'],true);
 if (!in_array($room['room_Type'],['Lecture','Lab'],true)) throw new DomainException('Sections require a Lecture or Lab room.');
 $cap=(int)af_query($db,$room['room_Type']==='Lab'?'SELECT no_Of_Workstations FROM Lab WHERE lab_ID=?':'SELECT avail_Seats FROM Lecture WHERE lecture_ID=?',[$s['room_ID']])->fetchColumn();
 $needed=$before?schedule_section_capacity($db,(int)$s['CRN']):10;
 if ($cap<$needed) throw new DomainException('The room must accommodate the section capacity of '.$needed.'.');
 if (af_query($db,'SELECT 1 FROM Course_Section WHERE course_ID=? AND semester_ID=? AND section_No=? AND CRN<>?',[$s['course_ID'],$s['semester_ID'],$s['section_No'],$s['CRN']])->fetchColumn()) throw new DomainException('That section number already exists for this course and semester.');
 $managed=$before?academic_portal_validate_assignment($db,$before,$s):false;
 $pattern=af_meetings($db,$s,$managed);
 if (!$pattern) throw new DomainException('Select a time slot with day and period mappings.');
 if (!$managed) {
  $minutes=(int)$course['course_Credits']===3?90:($room['room_Type']==='Lab'?180:120);
  foreach ($pattern as $m) if ((strtotime($m['end_Time'])-strtotime($m['start_Time']))/60!==$minutes) throw new DomainException('This course/room requires '.$minutes.'-minute meeting periods.');
 }
 $rel=academic_portal_meeting_relation($db);
 $other=af_query($db,"SELECT m.* FROM Course_Section s JOIN $rel m ON m.CRN=s.CRN WHERE s.semester_ID=? AND s.CRN<>? AND (s.faculty_ID=? OR s.room_ID=?)",[$s['semester_ID'],$s['CRN'],$s['faculty_ID'],$s['room_ID']])->fetchAll(PDO::FETCH_ASSOC);
 if (academic_portal_meetings_overlap($pattern,$other)) throw new DomainException('Faculty or room has an overlapping section.');
 if ($before && $s['course_ID']!==$before['course_ID']) foreach (['Enrollment','Attendance','Student_History','Faculty_History'] as $t) if (af_query($db,"SELECT 1 FROM `$t` WHERE CRN=? LIMIT 1",[$s['CRN']])->fetchColumn()) throw new DomainException('A section with academic records cannot change its course.');
 if ($before && $s['time_Slot_ID']!=$before['time_Slot_ID']) {
  $other=af_query($db,"SELECT m.* FROM Enrollment mine JOIN Enrollment other ON other.student_ID=mine.student_ID AND other.CRN<>mine.CRN JOIN Course_Section s ON s.CRN=other.CRN JOIN $rel m ON m.CRN=s.CRN WHERE mine.CRN=? AND s.semester_ID=?",[$s['CRN'],$s['semester_ID']])->fetchAll(PDO::FETCH_ASSOC);
  if (academic_portal_meetings_overlap($pattern,$other)) throw new DomainException('An enrolled student has an overlapping section.');
 }
}
function af_registration_check(PDO $db,array $s,array $student): void {
 $id=$student['student_ID'];$course=af_row($db,'Course','course_ID',$s['course_ID']);
 if (af_query($db,'SELECT 1 FROM Student_Hold WHERE student_ID=? LIMIT 1',[$id])->fetchColumn()) throw new DomainException('The student has an account hold.');
 if ((int)$s['available_Seats']<=0 || schedule_enrolled_count($db,(int)$s['semester_ID'],(int)$s['CRN'])>=schedule_section_capacity($db,(int)$s['CRN'])) throw new DomainException('This section is full.');
 $ug=af_query($db,'SELECT * FROM Undergraduate WHERE student_ID=?',[$id])->fetch(PDO::FETCH_ASSOC);$grad=af_query($db,'SELECT * FROM Graduate_Student WHERE student_ID=?',[$id])->fetch(PDO::FETCH_ASSOC);
 $type=$ug?'Undergraduate':($grad?'Graduate':'');
 if (!$type || $course['course_Type']!==$type) throw new DomainException('Student and course academic levels must match.');
 $load=$ug['undergraduate_student_Type']??$grad['graduate_Student_Type']??'';$max=['Full-Time'=>16,'Part-Time'=>8][$load]??0;
 $credits=(int)af_query($db,'SELECT COALESCE(SUM(c.course_Credits),0) FROM Enrollment e JOIN Course_Section s ON s.CRN=e.CRN JOIN Course c ON c.course_ID=s.course_ID WHERE e.student_ID=? AND s.semester_ID=?',[$id,$s['semester_ID']])->fetchColumn();
 if (!$max || $credits+(int)$course['course_Credits']>$max) throw new DomainException('Registration exceeds the student credit limit (full-time 16, part-time 8).');
 if (af_query($db,'SELECT 1 FROM Enrollment e JOIN Course_Section s ON s.CRN=e.CRN WHERE e.student_ID=? AND s.semester_ID=? AND s.course_ID=?',[$id,$s['semester_ID'],$s['course_ID']])->fetchColumn()) throw new DomainException('The student is already enrolled in this course for the semester.');
 $history=af_query($db,'SELECT h.course_ID,h.Grade,h.CRN,t.semester_Name FROM Student_History h JOIN Semester t ON t.semester_ID=h.semester_ID WHERE h.student_ID=? UNION SELECT s.course_ID,e.Grade,e.CRN,t.semester_Name FROM Enrollment e JOIN Course_Section s ON s.CRN=e.CRN JOIN Semester t ON t.semester_ID=e.semester_ID WHERE e.student_ID=? AND e.Grade IS NOT NULL',[$id,$id])->fetchAll(PDO::FETCH_ASSOC);
 $points=Northbridge\Academic\Transcript::POINTS;$passed=[];$latest=[];
 $term=af_row($db,'Semester','semester_ID',$s['semester_ID'])['semester_Name'];
 usort($history,static fn($a,$b)=>[academic_semester_order($a['semester_Name']),$a['CRN']]<=>[academic_semester_order($b['semester_Name']),$b['CRN']]);
 foreach ($history as $h) if (academic_semester_order($h['semester_Name'])<academic_semester_order($term) && isset($points[$h['Grade']??''])) $latest[$h['course_ID']]=$points[$h['Grade']];
 foreach ($latest as $code=>$score) if ($score>0) $passed[$code]=$score;
 if (isset($passed[$s['course_ID']])) throw new DomainException('The student has already passed this course.');
 $managed=academic_portal_enabled($db)&&af_query($db,'SELECT 1 FROM NB_AcademicCourse WHERE course_ID=?',[$s['course_ID']])->fetchColumn();
 if ($managed) {
  require_once __DIR__.'/academic/rebuild-registrar.php';
  [$season,$year]=explode(' ',$term);$target=(int)($year.($season==='Fall'?'09':'02'));
  try {$eligibility=(new Northbridge\Academic\RebuildRegistrar($db))->prerequisite((int)$id,$s['course_ID'],$target);}
  catch (RuntimeException $e) {throw new DomainException('Reviewed student/catalog data needs reconciliation before registration.');}
  if ($eligibility['status']!=='satisfied') throw new DomainException('Prerequisites need completion or review: '.$eligibility['reason']);
 } else foreach (af_query($db,'SELECT * FROM Course_Prerequisite WHERE course_ID=?',[$s['course_ID']]) as $p) if (!isset($points[$p['min_Grade_Req']]) || ($passed[$p['prerequisite_course_ID']]??-1)<$points[$p['min_Grade_Req']]) throw new DomainException('Prerequisite not satisfied: '.$p['prerequisite_course_ID'].' at '.$p['min_Grade_Req'].' or higher.');
 $rel=academic_portal_meeting_relation($db);$other=af_query($db,"SELECT m.* FROM Enrollment e JOIN Course_Section s ON s.CRN=e.CRN JOIN $rel m ON m.CRN=s.CRN WHERE e.student_ID=? AND s.semester_ID=?",[$id,$s['semester_ID']])->fetchAll(PDO::FETCH_ASSOC);
 $pattern=af_meetings($db,$s,true);if (!$pattern) throw new DomainException('Section meeting times need review before registration.');
 if (academic_portal_meetings_overlap($pattern,$other)) throw new DomainException('The student has a time conflict with another section.');
}
function af_save(PDO $db,string $mode,array $in,int $admin,?DateTimeImmutable $now=null): string {
 if (!af_can_write($admin)) throw new DomainException('Your account has read-only access to these Admin features.');
 $now??=new DateTimeImmutable('now',new DateTimeZone('America/New_York'));
 $db->beginTransaction();
 try {
  // One dedicated mutex serializes cross-record academic changes and ID allocation.
  if (!af_query($db,"SELECT lock_ID FROM Admin_Feature_Lock WHERE lock_ID=1 FOR UPDATE")->fetchColumn()) throw new DomainException('Admin feature storage has not been initialized.');
  $actor=af_row($db,'Admin','admin_ID',$admin);$before=af_snapshot($db,$mode,$in,true);
  if ($before) af_check_version($before,$in);
  $after=[];$message='Changes saved successfully.';
  if (in_array($mode,['create-course','update-course'],true)) {
   $id=af_text($in,'course_ID',20);if (!preg_match('/^[A-Z][A-Z0-9-]*$/D',$id)) throw new DomainException('Use an uppercase course code with letters, numbers or hyphens.');
   $dept=af_id($in,'dept_ID');af_row($db,'Department','dept_ID',$dept);
   $credits=af_id($in,'course_Credits');if ($credits>16) throw new DomainException('Course credits must be between 1 and 16.');
   $type=af_text($in,'course_Type',20);if (!in_array($type,['Undergraduate','Graduate'],true)) throw new DomainException('Choose the academic level.');
   $v=[af_text($in,'course_Name',255),$dept,$credits,af_text($in,'course_DSC',500),$type,$id];
   if ($mode==='update-course') {
    af_managed_course($db,$id);
    if (($dept!=$before['dept_ID']||$credits!=$before['course_Credits']||$type!==$before['course_Type'])&&af_query($db,'SELECT 1 FROM Course_Section WHERE course_ID=? LIMIT 1',[$id])->fetchColumn()) throw new DomainException('Department, credits and level cannot change after sections exist.');
    af_query($db,'UPDATE Course SET course_Name=?,dept_ID=?,course_Credits=?,course_DSC=?,course_Type=? WHERE course_ID=?',$v);
   } else af_query($db,'INSERT INTO Course(course_Name,dept_ID,course_Credits,course_DSC,course_Type,course_ID) VALUES (?,?,?,?,?,?)',$v);
   $after=af_row($db,'Course','course_ID',$id);
  } elseif ($mode==='add-prerequisites') {
   $id=$before['course']['course_ID'];af_managed_course($db,$id);$pre=af_text($in,'prerequisite_course_ID',20);af_row($db,'Course','course_ID',$pre);
   $pending=[$pre];$seen=[];
   while ($pending) { $node=array_pop($pending);if ($node===$id) throw new DomainException('Prerequisites cannot reference themselves or create a cycle.');if (isset($seen[$node])) continue;$seen[$node]=true;foreach (af_query($db,'SELECT prerequisite_course_ID FROM Course_Prerequisite WHERE course_ID=?',[$node]) as $r) $pending[]=$r['prerequisite_course_ID']; }
   af_query($db,'INSERT INTO Course_Prerequisite(course_ID,prerequisite_course_ID,min_Grade_Req) VALUES (?,?,?)',[$id,$pre,af_grade($in,'min_Grade_Req')]);
  } elseif (in_array($mode,['create-major','update-major','create-minor','update-minor'],true)) {
   $kind=str_contains($mode,'major')?'Major':'Minor';$key=strtolower($kind).'_ID';$name=strtolower($kind).'_Name';$dept=af_id($in,'dept_ID');af_row($db,'Department','dept_ID',$dept);$label=af_text($in,$name);
   if (str_starts_with($mode,'update')) {af_managed_program($db,$before,$kind);$id=$before[$key];af_query($db,"UPDATE `$kind` SET `$name`=?,dept_ID=? WHERE `$key`=?",[$label,$dept,$id]);}
   else {$id=(int)$db->query("SELECT COALESCE(MAX(`$key`),0)+1 FROM `$kind`")->fetchColumn();af_query($db,"INSERT INTO `$kind` (`$key`,`$name`,dept_ID) VALUES (?,?,?)",[$id,$label,$dept]);}
   $after=af_row($db,$kind,$key,$id);$message=$kind.' saved (ID '.$id.').';
  } elseif (str_contains($mode,'requirements')) {
   $kind=str_contains($mode,'major')?'Major':'Minor';$key=strtolower($kind).'_ID';af_managed_program($db,$before['program'],$kind);$id=$before['program'][$key];$course=af_text($in,'course_ID',20);af_row($db,'Course','course_ID',$course);$grade=af_grade($in,'min_Grade_Req');
   $exists=af_query($db,"SELECT 1 FROM {$kind}_Requirements WHERE `$key`=? AND course_ID=?",[$id,$course])->fetchColumn();
   if (str_starts_with($mode,'create')) {if ($exists) throw new DomainException('This requirement already exists. Use Update Requirements.');af_query($db,"INSERT INTO {$kind}_Requirements (`$key`,course_ID,min_Grade_Req) VALUES (?,?,?)",[$id,$course,$grade]);}
   else {if (!$exists) throw new DomainException('Choose an existing requirement to update.');af_query($db,"UPDATE {$kind}_Requirements SET min_Grade_Req=? WHERE `$key`=? AND course_ID=?",[$grade,$id,$course]);}
  } elseif (in_array($mode,['create-building','update-building'],true)) {
   $id=af_text($in,'Bldg_ID',8);if (!preg_match('/^[A-Z0-9-]+$/D',$id)) throw new DomainException('Building ID must use uppercase letters, numbers or hyphens.');
   $name=af_text($in,'Bldg_Name',255);$usage=af_text($in,'building_Usage',255);
   if ($before) af_query($db,'UPDATE Building SET Bldg_Name=?,building_Usage=? WHERE Bldg_ID=?',[$name,$usage,$id]);
   else af_query($db,'INSERT INTO Building(Bldg_ID,Bldg_Name,building_Usage) VALUES (?,?,?)',[$id,$name,$usage]);
   $after=af_row($db,'Building','Bldg_ID',$id);
  } elseif ($mode==='create-room') {
   $id=af_text($in,'room_ID',20);$building=af_text($in,'Bldg_ID',8);af_row($db,'Building','Bldg_ID',$building);$number=af_id($in,'room_Number');$type=af_text($in,'room_Type',20);$capacity=af_id($in,'capacity');
   if (!preg_match('/^[A-Z0-9-]+$/D',$id)||!in_array($type,['Lecture','Lab','Office'],true)||$capacity>10000) throw new DomainException('Check the room ID, type and capacity (1–10,000).');
   if (af_query($db,'SELECT 1 FROM Room WHERE Bldg_ID=? AND room_Number=?',[$building,$number])->fetchColumn()) throw new DomainException('This building already has that room number.');
   af_query($db,'INSERT INTO Room(room_ID,Bldg_ID,room_Number,room_Type) VALUES (?,?,?,?)',[$id,$building,$number,$type]);
   [$table,$key,$count]=['Lecture'=>['Lecture','lecture_ID','avail_Seats'],'Lab'=>['Lab','lab_ID','no_Of_Workstations'],'Office'=>['Office','office_ID','No_Of_Desks']][$type];
   af_query($db,"INSERT INTO `$table` (`$key`,`$count`) VALUES (?,?)",[$id,$capacity]);$after=af_row($db,'Room','room_ID',$id);
  } elseif (in_array($mode,['create-section','update-section','add-faculty-section','update-faculty-section'],true)) {
   $s=$before?:['CRN'=>(int)$db->query('SELECT COALESCE(MAX(CRN),0)+1 FROM Course_Section')->fetchColumn(),'available_Seats'=>10];
   if (str_contains($mode,'faculty-section')) $s['faculty_ID']=af_id($in,'faculty_ID');
   else {foreach (['section_No','faculty_ID','time_Slot_ID'] as $k) $s[$k]=af_id($in,$k);foreach (['course_ID','room_ID'] as $k) $s[$k]=af_text($in,$k,20);if (!$before) $s['semester_ID']=af_id($in,'semester_ID');}
   af_validate_section($db,$s,$before?:null,$now);
   if ($before) {af_query($db,'UPDATE Course_Section SET course_ID=?,section_No=?,faculty_ID=?,time_Slot_ID=?,room_ID=? WHERE CRN=?',[$s['course_ID'],$s['section_No'],$s['faculty_ID'],$s['time_Slot_ID'],$s['room_ID'],$s['CRN']]);}
   else af_query($db,'INSERT INTO Course_Section(CRN,course_ID,section_No,faculty_ID,time_Slot_ID,room_ID,semester_ID,available_Seats) VALUES (?,?,?,?,?,?,?,10)',[$s['CRN'],$s['course_ID'],$s['section_No'],$s['faculty_ID'],$s['time_Slot_ID'],$s['room_ID'],$s['semester_ID']]);
   schedule_sync_faculty_counts($db);$after=$s;$message='Section saved (CRN '.$s['CRN'].').';
   af_query($db,'UPDATE Master_Schedule SET admin_ID=?, saved_At=CURRENT_TIMESTAMP(6),schedule_Data=? WHERE semester_ID=?',[$admin,json_encode(['semester_Name'=>af_row($db,'Semester','semester_ID',$s['semester_ID'])['semester_Name'],'sections'=>schedule_sections($db,(int)$s['semester_ID'],true)],JSON_THROW_ON_ERROR),$s['semester_ID']]);
  } elseif (in_array($mode,['add-student-section','drop-student-section','update-student-grade','change-grade'],true)) {
   $student=$before['student'];$s=$before['section'];$sid=$student['student_ID'];$crn=$s['CRN'];$term=af_row($db,'Semester','semester_ID',$s['semester_ID']);
   if (str_contains($mode,'student-section')) {
    af_window($term['semester_Name'],$now);
    if ($mode==='add-student-section') {if ($before['enrollment']||$before['history']) throw new DomainException('The student already has a record for this section.');af_registration_check($db,$s,$student);af_query($db,'INSERT INTO Enrollment(student_ID,CRN,semester_ID) VALUES (?,?,?)',[$sid,$crn,$s['semester_ID']]);}
    else {
     af_text($in,'reason',500);
     if (!$before['enrollment']) throw new DomainException('The student is not enrolled in this section.');
     if ($before['history'] || $before['enrollment'][0]['Grade']!==null || af_query($db,'SELECT 1 FROM Attendance WHERE student_ID=? AND CRN=? LIMIT 1',[$sid,$crn])->fetchColumn()) throw new DomainException('This enrollment has academic records. Use the registrar withdrawal process to preserve them.');
     af_query($db,'DELETE FROM Enrollment WHERE student_ID=? AND CRN=?',[$sid,$crn]);
    }
    // Correctly synchronize seats whether the deployment has the legacy triggers or not.
    $seats=schedule_section_capacity($db,(int)$crn)-schedule_enrolled_count($db,(int)$s['semester_ID'],(int)$crn);
    af_query($db,'UPDATE Course_Section SET available_Seats=? WHERE CRN=?',[$seats,$crn]);
   } else {
    if (!$before['enrollment']&&!$before['history']) throw new DomainException('No academic record exists for this student and section.');
    $g=af_grade($in);af_text($in,'reason',500);
    $old=$before['enrollment'][0]['Grade']??$before['history'][0]['Grade']??null;
    if ($mode==='change-grade' && $old===null) throw new DomainException('No previous grade exists. Use Update Student Grade.');
    af_query($db,'UPDATE Enrollment SET Grade=? WHERE student_ID=? AND CRN=?',[$g,$sid,$crn]);af_query($db,'UPDATE Student_History SET Grade=? WHERE student_ID=? AND CRN=?',[$g,$sid,$crn]);
   }
  } elseif (in_array($mode,['declare-major','add-minor','assign-advisor'],true)) {
   $sid=$before['student']['student_ID'];$today=$now->format('Y-m-d');
   if ($mode==='assign-advisor') {
    $fid=af_id($in,'faculty_ID');$f=af_row($db,'Faculty','faculty_ID',$fid,true);
    if ($f['faculty_Type']!=='Full-Time') throw new DomainException('Only full-time faculty may advise students.');
    if ((int)af_query($db,'SELECT COUNT(*) FROM Advisor WHERE faculty_ID=?',[$fid])->fetchColumn()>=15) throw new DomainException('This advisor already has 15 advisees.');
    af_query($db,'INSERT INTO Advisor(faculty_ID,student_ID,date_Of_Appnt) VALUES (?,?,?)',[$fid,$sid,$today]);
   } else {
    $kind=$mode==='declare-major'?'Major':'Minor';$key=strtolower($kind).'_ID';$id=af_id($in,$key);af_row($db,$kind,$key,$id);
    if ($kind==='Major' && ($before['student']['major_ID']==$id || af_query($db,'SELECT 1 FROM Student_Major WHERE student_ID=? AND major_ID=?',[$sid,$id])->fetchColumn())) throw new DomainException('This major is already declared.');
    af_query($db,"INSERT INTO Student_$kind (student_ID,`$key`,date_Of_Choice) VALUES (?,?,?)",[$sid,$id,$today]);
   }
  } else throw new DomainException('Unknown Admin action.');
  if (!$after) $after=af_snapshot($db,$mode,$in);
  af_query($db,'INSERT INTO Admin_Action_Log(admin_ID,action_Name,before_Data,after_Data,reason) VALUES (?,?,?,?,?)',[$admin,$mode,json_encode($before,JSON_THROW_ON_ERROR),json_encode($after,JSON_THROW_ON_ERROR),is_string($in['reason']??null)?trim($in['reason']):null]);
  $db->commit();return $message;
 } catch (Throwable $e) {if ($db->inTransaction()) $db->rollBack();throw $e;}
}
