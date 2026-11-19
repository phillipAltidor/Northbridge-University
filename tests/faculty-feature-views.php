<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
require dirname(__DIR__).'/includes/auth.php';
require dirname(__DIR__).'/includes/faculty-navigation.php';
require dirname(__DIR__).'/includes/schedule-columns.php';
require dirname(__DIR__).'/includes/academic/degree-audit-view.php';
$_SERVER['SCRIPT_NAME']='/home.php'; $_SESSION['faculty_form_token']='fixture-token';
$modes=['password'=>'Update Password','address'=>'Update Address','master-schedule'=>'Master Schedule','attendance'=>'Mark Attendance','grades'=>'Assign Grades','advisees'=>'Advisee List','students'=>'Student Information','transcript'=>'Student Unofficial Transcript','departments'=>'Departments','courses'=>'Courses Offered by Departments','majors'=>'Majors Offered by Departments','minors'=>'Minors Offered by Departments','prerequisites'=>'Course Prerequisites','major-requirements'=>'Major Requirements','major-courses'=>'Course Requirements for a Major'];
$count=0;
foreach ($modes as $mode=>$title) {
 $path='faculty/'.$mode.'.php';$values=['street'=>'1 Example Street','city'=>'Westbury','state'=>'NY','zip_Code'=>'11568']; $filters=['q'=>'','dept_ID'=>1,'semester_id'=>1,'course_id'=>'CS2510','major_id'=>1]; $error='';$message='';
 $student=['student_ID'=>100,'first_Name'=>'Jordan','last_Name'=>'Example','user_Email'=>'student@example.test','student_Type'=>'Undergraduate','student_Year'=>1,'major_Name'=>'Computer and Information Science, B.S.'];
 $data=['version'=>'fixture-version','departments'=>[['dept_ID'=>1,'dept_Name'=>'Computer Science']], 'semesters'=>[['semester_ID'=>1,'semester_Name'=>'Fall 2026']], 'students'=>[$student], 'courses'=>[['course_ID'=>'CS2510','course_Name'=>'Computer Programming I']], 'majors'=>[['major_ID'=>1,'major_Name'=>'Computer and Information Science, B.S.']]];
 if ($mode==='master-schedule') $data+=['page'=>['rows'=>[],'total'=>0,'start'=>0,'end'=>0,'page'=>1,'pages'=>1],'meetings'=>[]];
 if (in_array($mode,['attendance','grades'],true)) {
  $section=['CRN'=>101,'course_ID'=>'CS2510','course_Name'=>'Computer Programming I','section_No'=>1,'semester_Name'=>'Fall 2026'];
  $data+=['sections'=>[$section],'section'=>$section,'marks'=>[array_merge($student,['Grade'=>null,'Present_Absent'=>null])],'date'=>'2026-11-19','closed'=>$mode==='grades'?'The grading window has not been configured.':null,'options'=>$mode==='grades'?[]:['Present','Absent']];
 }
 if ($mode==='transcript') $data+=['person'=>$student,'attempts'=>[['semester_Name'=>'Fall 2026','course_ID'=>'CS2510','course_Name'=>'Computer Programming I','course_Credits'=>4,'Grade'=>null,'status'=>'In progress']]];
 if ($mode==='departments') $data['rows']=[['dept_ID'=>1,'dept_Name'=>'Computer Science','Email'=>'computing@example.test','Phone_No'=>'555-0100','dept_Manager'=>'Example Manager','first_Name'=>'Alex','last_Name'=>'Example','Bldg_Name'=>'Administration','room_Number'=>101]];
 if ($mode==='courses') $data['rows']=[['course_ID'=>'CS2510','course_Name'=>'Computer Programming I','course_Credits'=>4,'course_Type'=>'Undergraduate','course_DSC'=>'An introduction to programming.']];
 if ($mode==='majors') $data['rows']=$data['majors'];
 if ($mode==='minors') $data['rows']=[['minor_ID'=>1,'minor_Name'=>'Computer Science']];
 if ($mode==='prerequisites') $data+=['course'=>['course_ID'=>'CS2510','course_Name'=>'Computer Programming I','course_DSC'=>'An introduction to programming.'],'rule'=>['type'=>'and','rules'=>[['type'=>'course','courseCode'=>'MA1020','minimumGrade'=>'C'],['type'=>'proficiency','key'=>'ECI']]]];
 if (in_array($mode,['major-requirements','major-courses'],true)) $data+=['major'=>$data['majors'][0],'program'=>json_decode(file_get_contents(dirname(__DIR__).'/database/staging/academic-alignment/rules/curricula.json'),true)['programs'][0]];
 ob_start();require dirname(__DIR__).'/includes/faculty-feature-view.php';$html=ob_get_clean();
 $dom=new DOMDocument();@$dom->loadHTML($html);$xp=new DOMXPath($dom);
 if ($xp->query('//h1')->length!==1 || $xp->query('//script')->length!==0) throw new RuntimeException('Invalid view: '.$mode);
 foreach ($xp->query('//input[not(@type="hidden")]|//select') as $input) {
  $id=$input->getAttribute('id'); if ($id==='' || $xp->query('//label[@for="'.$id.'"]')->length!==1) throw new RuntimeException('Missing field label: '.$mode);
 }
 foreach ($xp->query('//form[@method="post"]') as $form) if ($xp->query('.//input[@name="token"]',$form)->length!==1) throw new RuntimeException('Missing token: '.$mode);
 if ($mode==='grades' && $xp->query('//fieldset[@disabled]')->length!==1) throw new RuntimeException('Closed grade form is enabled');
 if (!in_array($mode,['password','address','attendance','grades'],true) && $xp->query('//form[@method="post"]')->length) throw new RuntimeException('Read-only page has write form');
 $count++;
 if (isset($argv[1])) {
  $directory=$argv[1]; if (!is_dir($directory))mkdir($directory,0700,true);
  $css=file_get_contents(dirname(__DIR__).'/assets/css/style.css').file_get_contents(dirname(__DIR__).'/assets/css/faculty-features.css');
  ob_start();faculty_navigation($path);$nav=ob_get_clean();
  $html=preg_replace('/<link[^>]+>/','',$html);
  file_put_contents($directory.'/'.$mode.'.html','<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Faculty fixture preview</title><style>'.$css.'</style><header class="topbar"><div class="brand"><div class="seal">N</div><div><b>Northbridge University</b><small>Faculty Portal · Fictional preview</small></div></div></header><div class="shell"><aside class="sidebar admin-sidebar">'.$nav.'</aside><main class="content">'.$html.'</main></div></html>');
 }
}
echo "PASS: $count faculty views, field labels, CSRF fields, read-only methods and closed grading state.\n";
