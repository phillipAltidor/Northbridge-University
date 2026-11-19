<?php
declare(strict_types=1);
require_once __DIR__.'/layout.php';
require_role(['Faculty']);
require_once __DIR__.'/faculty-features.php';
require_once __DIR__.'/schedule-columns.php';
require_once __DIR__.'/schedule.php';
require_once __DIR__.'/academic/degree-audit-view.php';
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
$featureTitles = [
 'password'=>'Update Password','address'=>'Update Address','master-schedule'=>'Master Schedule',
 'attendance'=>'Mark Attendance','grades'=>'Assign Grades','advisees'=>'Advisee List',
 'students'=>'Student Information','transcript'=>'Student Unofficial Transcript',
 'departments'=>'Departments','courses'=>'Courses Offered by Departments','majors'=>'Majors Offered by Departments',
 'minors'=>'Minors Offered by Departments','prerequisites'=>'Course Prerequisites',
 'major-requirements'=>'Major Requirements','major-courses'=>'Course Requirements for a Major',
];
$mode=$facultyFeature ?? '';
if (!isset($featureTitles[$mode])) { http_response_code(404); exit; }
$path='faculty/'.$mode.'.php'; $title=$featureTitles[$mode];
$writable=in_array($mode,['password','address','attendance','grades'],true);
if (!in_array($_SERVER['REQUEST_METHOD'], $writable?['GET','HEAD','POST']:['GET','HEAD'], true)) {
 http_response_code(405); header('Allow: '.($writable?'GET, HEAD, POST':'GET, HEAD')); exit('Method not allowed.');
}
$fid=(int)$_SESSION['user_id'];
try { $account=faculty_account($pdo,$fid); }
catch (DomainException $e) { http_response_code(403); exit('A faculty account is required.'); }
$_SESSION['faculty_form_token'] ??= bin2hex(random_bytes(32));
$error=''; $message=$_SESSION['faculty_success'] ?? ''; unset($_SESSION['faculty_success']);
$data=[]; $filters=[]; $values=[];
try {
 if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!is_string($_POST['token']??null) || !hash_equals($_SESSION['faculty_form_token'],$_POST['token'])) { http_response_code(403); throw new DomainException('Your form expired. Reload the page and try again.'); }
  $version=is_string($_POST['version']??null)?$_POST['version']:'';
  if ($mode==='address') {
   foreach (['street','city','state','zip_Code'] as $key) $values[$key]=is_string($_POST[$key]??null)?$_POST[$key]:'';
   faculty_save_address($pdo,$fid,$_POST,$version);
  } elseif ($mode==='password') {
   $attempt=$_SESSION['faculty_password_attempts']??['since'=>time(),'count'=>0];
   if (time()-$attempt['since']>=900) $attempt=['since'=>time(),'count'=>0];
   if ($attempt['count']>=5) throw new DomainException('Too many password attempts. Wait 15 minutes before trying again.');
   $attempt['count']++; $_SESSION['faculty_password_attempts']=$attempt;
   $_SESSION['auth_version']=faculty_save_password($pdo,$fid,$_POST);
   unset($_SESSION['faculty_password_attempts']); session_regenerate_id(true);
  } else {
   $crn=faculty_id($_GET['crn']??null,'course section');
   if (!is_array($_POST['marks']??null)) throw new DomainException('Select the marks to save.');
   faculty_save_marks($pdo,$fid,$crn,$mode,$_POST['marks'],$version,faculty_window_config());
  }
  $_SESSION['faculty_form_token']=bin2hex(random_bytes(32));
  $_SESSION['faculty_success']=$mode==='password'?'Your password has been changed. Other signed-in sessions will need to log in again.':'Your changes have been saved.';
  header('Location: '.app_url($path).(isset($crn)?'?crn='.$crn:''),true,303); exit;
 }
} catch (DomainException $e) { $error=$e->getMessage(); }
catch (PDOException $e) { http_response_code(409); $error='The change could not be saved. Reload and try again; contact your administrator if the problem continues.'; }

try {
 if ($mode==='address') { if (!$values) $values=$account; $data['version']=$_SERVER['REQUEST_METHOD']==='POST' ? (is_string($_POST['version']??null)?$_POST['version']:'') : faculty_address_version($account); }
 elseif (in_array($mode,['advisees','students','transcript'],true)) {
  $filters['q']=faculty_text($_GET['q']??'');
  $data['students']=($mode==='transcript' && $filters['q']==='')?[]:faculty_student_search($pdo,$fid,$filters['q'],$mode);
  if ($mode==='transcript' && isset($_GET['student_id'])) {
   $sid=faculty_id($_GET['student_id'],'student');
   $people=faculty_rows($pdo,'SELECT s.student_ID,s.student_Type,s.student_Year,u.first_Name,u.last_Name,m.major_Name FROM Student s JOIN User u ON u.user_ID=s.student_ID JOIN Major m ON m.major_ID=s.major_ID WHERE s.student_ID=?',[$sid]);
   if (!$people) throw new DomainException('Student not found.');
   $data['person']=$people[0]; $data['attempts']=faculty_transcript($pdo,$sid);
  }
 } elseif (in_array($mode,['attendance','grades'],true)) {
  $data['sections']=faculty_rows($pdo,'SELECT cs.CRN,c.course_Name,c.course_ID,cs.section_No,s.semester_Name FROM Course_Section cs JOIN Course c ON c.course_ID=cs.course_ID JOIN Semester s ON s.semester_ID=cs.semester_ID WHERE cs.faculty_ID=? ORDER BY s.semester_Name DESC,c.course_ID,cs.section_No',[$fid]);
  if (isset($_GET['crn']) && $_GET['crn']!=='') {
   $crn=faculty_id($_GET['crn'],'course section'); $data['section']=faculty_section($pdo,$fid,$crn);
   $now=academic_today(); $data['date']=$now->format('Y-m-d');
   $policy=faculty_window_config();
   $data['closed']=faculty_write_window($mode,$data['section'],faculty_meetings($pdo,$crn),$policy,$now);
   $data['marks']=faculty_mark_rows($pdo,$data['section'],$data['date']);
   $data['version']=faculty_marks_version($data['section'],$data['date'],$mode,$data['marks']);
   $data['options']=$mode==='attendance'?['Present','Absent']:($policy[$data['section']['semester_Name']]['grading'][$crn]['allowed_grades']??[]);
  }
 } elseif ($mode==='master-schedule') {
  $data['semesters']=faculty_rows($pdo,'SELECT semester_ID,semester_Name FROM Semester');
  usort($data['semesters'],static fn($a,$b)=>academic_semester_order($b['semester_Name'])<=>academic_semester_order($a['semester_Name']));
  $timeline=require __DIR__.'/../config/academic-timeline.php'; $default='';
  foreach ($data['semesters'] as $term) if ($term['semester_Name']===$timeline['current_semester']) $default=(string)$term['semester_ID'];
  $filters['semester_id']=faculty_id($_GET['semester_id']??$default,'semester');
  if (!in_array($filters['semester_id'],array_map('intval',array_column($data['semesters'],'semester_ID')),true)) throw new DomainException('Select an available semester.');
  foreach (['q','professor','day','start','end','building','room','level','dept_ID'] as $key) $filters[$key]=faculty_text($_GET[$key]??($key==='level' && academic_portal_enabled($pdo)?'Undergraduate':''));
  if (!in_array($filters['level'],['','Undergraduate','Graduate'],true)) throw new DomainException('Select a valid course level.');
  if ($filters['dept_ID']!=='') faculty_id($filters['dept_ID'],'department');
  if ($filters['day']!=='' && !in_array($filters['day'],['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'],true)) throw new DomainException('Select a valid day.');
  foreach (['start','end'] as $key) if ($filters[$key]!=='' && !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D',$filters[$key])) throw new DomainException('Enter a valid time.');
  if ($filters['start']!=='' && $filters['end']!=='' && $filters['start'] >= $filters['end']) throw new DomainException('End time must follow start time.');
  $data['departments']=faculty_rows($pdo,'SELECT dept_ID,dept_Name FROM Department ORDER BY dept_Name');
  $sections=schedule_filter_sections(schedule_sections($pdo,$filters['semester_id']),$filters['q'],(int)$filters['dept_ID'],false,$filters['level']?:'all');
  $raw=faculty_rows($pdo,'SELECT mt.* FROM '.academic_portal_meeting_relation($pdo).' mt JOIN Course_Section cs ON cs.CRN=mt.CRN WHERE cs.semester_ID=?',[$filters['semester_id']]);
  $byCRN=[]; foreach ($raw as $meeting) $byCRN[$meeting['CRN']][]=$meeting;
  $sections=array_values(array_filter($sections,static function($s) use($filters,$byCRN) {
   $contains=static fn($text,$query)=>$query==='' || str_contains(mb_strtolower((string)$text),mb_strtolower($query));
   if (!$contains(implode(' ',[$s['first_Name'],$s['middle_Name'],$s['last_Name']]),$filters['professor']) || !$contains($s['Bldg_Name'],$filters['building']) || !$contains($s['room_Number'],$filters['room'])) return false;
   if ($filters['day']==='' && $filters['start']==='' && $filters['end']==='') return true;
   foreach ($byCRN[$s['CRN']]??[] as $m) if (($filters['day']==='' || $m['day_Of_Week']===$filters['day']) && !empty($m['start_Time']) && !empty($m['end_Time']) && ($filters['start']==='' || $m['end_Time']>$filters['start'].':00') && ($filters['end']==='' || $m['start_Time']<$filters['end'].':00')) return true;
   return false;
  }));
  $page=isset($_GET['page'])?faculty_id($_GET['page'],'page'):1;
  $data['page']=schedule_page($sections,25,$page); $data['meetings']=schedule_columns_meetings($pdo,$data['page']['rows']);
 } elseif ($mode==='departments') {
  $data['rows']=faculty_rows($pdo,'SELECT d.dept_ID,d.dept_Name,d.Email,d.Phone_No,d.dept_Manager,u.first_Name,u.last_Name,b.Bldg_Name,r.room_Number FROM Department d LEFT JOIN User u ON u.user_ID=d.chair_ID LEFT JOIN Room r ON r.room_ID=d.office_ID LEFT JOIN Building b ON b.Bldg_ID=r.Bldg_ID ORDER BY d.dept_Name');
 } elseif (in_array($mode,['courses','majors','minors'],true)) {
  $data['departments']=faculty_rows($pdo,'SELECT dept_ID,dept_Name FROM Department ORDER BY dept_Name');
  if (isset($_GET['dept_ID']) && $_GET['dept_ID']!=='') {
   $filters['dept_ID']=faculty_id($_GET['dept_ID'],'department');
   if (!in_array($filters['dept_ID'],array_map('intval',array_column($data['departments'],'dept_ID')),true)) throw new DomainException('Department not found.');
   $sql=['courses'=>'SELECT course_ID,course_Name,course_Credits,course_DSC,course_Type FROM Course WHERE dept_ID=? ORDER BY course_ID','majors'=>'SELECT major_ID,major_Name FROM Major WHERE dept_ID=? ORDER BY major_Name','minors'=>'SELECT minor_ID,minor_Name FROM Minor WHERE dept_ID=? ORDER BY minor_Name'];
   $data['rows']=faculty_rows($pdo,$sql[$mode],[$filters['dept_ID']]);
  }
 } elseif ($mode==='prerequisites') {
  $filters['q']=faculty_text($_GET['q']??'');
  $data['courses']=faculty_rows($pdo,'SELECT course_ID,course_Name FROM Course ORDER BY course_ID');
  if (isset($_GET['course_id']) && $_GET['course_id']!=='') {
   $filters['course_id']=faculty_text($_GET['course_id'],20);
   $course=faculty_rows($pdo,'SELECT * FROM Course WHERE course_ID=?',[$filters['course_id']]);
   if (!$course) throw new DomainException('Course not found.'); $data['course']=$course[0];
   $data['rows']=faculty_rows($pdo,'SELECT p.prerequisite_course_ID,c.course_Name,p.min_Grade_Req FROM Course_Prerequisite p JOIN Course c ON c.course_ID=p.prerequisite_course_ID WHERE p.course_ID=? ORDER BY p.prerequisite_course_ID',[$filters['course_id']]);
   if (academic_portal_enabled($pdo)) {
    $rule=faculty_rows($pdo,'SELECT rule_JSON FROM NB_AcademicCourse WHERE course_ID=?',[$filters['course_id']]);
    if ($rule) $data['rule']=json_decode($rule[0]['rule_JSON'],true,512,JSON_THROW_ON_ERROR);
   }
  }
 } elseif (in_array($mode,['major-requirements','major-courses'],true)) {
  $data['majors']=faculty_rows($pdo,'SELECT major_ID,major_Name FROM Major ORDER BY major_Name');
  if (isset($_GET['major_id']) && $_GET['major_id']!=='') {
   $filters['major_id']=faculty_id($_GET['major_id'],'major');
   $major=faculty_rows($pdo,'SELECT major_ID,major_Name FROM Major WHERE major_ID=?',[$filters['major_id']]);
   if (!$major) throw new DomainException('Major not found.'); $data['major']=$major[0];
   if (academic_portal_enabled($pdo)) {
    $programs=json_decode(file_get_contents(__DIR__.'/../database/staging/academic-alignment/rules/curricula.json'),true,512,JSON_THROW_ON_ERROR)['programs'];
    foreach ($programs as $program) if ($program['kind']==='major' && $program['program']===$data['major']['major_Name']) $data['program']=$program;
   }
   $data['rows']=faculty_rows($pdo,'SELECT c.course_ID,c.course_Name,c.course_Credits,r.min_Grade_Req FROM Major_Requirements r JOIN Course c ON c.course_ID=r.course_ID WHERE r.major_ID=? ORDER BY c.course_ID',[$filters['major_id']]);
  }
 }
} catch (DomainException $e) { http_response_code(400); $error=$e->getMessage(); }
catch (Throwable $e) { http_response_code(503); $error='This information is temporarily unavailable. Ask your administrator to check the local database setup.'; }
page_start($title,'Faculty',$path,null,false);
require __DIR__.'/faculty-feature-view.php';
page_end();
