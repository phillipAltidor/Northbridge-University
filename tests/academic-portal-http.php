<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit;
ob_start();
require_once __DIR__.'/rehearsal-receipts.php';
require dirname(__DIR__).'/config/database.php';
require dirname(__DIR__).'/includes/student-schedule.php';
if (!preg_match('/^northbridge_ow_rebuild_2026_[4-9][0-9]*$/D',$database)) throw new RuntimeException('Isolated rebuilt database required.');
$checks=0;$sessions=[];
$base=getenv('NORTHBRIDGE_HTTP_BASE')?:'http://127.0.0.1:8001/';
if (!in_array($base,['http://127.0.0.1:8000/','http://127.0.0.1:8001/','http://127.0.0.1:8002/','http://127.0.0.1:8003/','http://127.0.0.1:8004/','http://127.0.0.1:8005/'],true)) throw new RuntimeException('Local test server required.');
$check=static function(bool $ok,string $message)use(&$checks):void {if (!$ok) throw new RuntimeException($message);$checks++;};
$request=static function(string $path,string $sid='')use($base):array {
 $context=stream_context_create(['http'=>['header'=>$sid?"Cookie: PHPSESSID=$sid\r\n":'','follow_location'=>0,'ignore_errors'=>true,'timeout'=>30]]);
 $html=file_get_contents($base.$path,false,$context);$headers=http_get_last_response_headers();preg_match('/HTTP\/\S+ (\d+)/',$headers[0],$m);
 if ($html===false || preg_match('/(?:Warning|Fatal error|Notice|Deprecated):/',$html)) throw new RuntimeException('Unexpected PHP page output.');
 return [(int)$m[1],$html];
};
$session=static function(int $id)use($pdo,&$sessions):string {
 $q=$pdo->prepare('SELECT user_ID,user_Type,user_Password FROM Login WHERE user_ID=?');$q->execute([$id]);$account=$q->fetch();
 $sid=bin2hex(random_bytes(20));session_id($sid);session_start();$_SESSION=['user_id'=>(int)$account['user_ID'],'user_type'=>$account['user_Type'],'auth_version'=>hash('sha256',$account['user_Password'])];session_write_close();$sessions[]=$sid;return $sid;
};
try {
 foreach (['student/schedule.php','faculty/schedule.php','admin/master-schedule.php'] as $path) $check($request($path)[0]===302,'Anonymous access must redirect.');
 foreach (['Admin','Student','Faculty','StatStaff'] as $role) {
  $q=$pdo->prepare('SELECT user_ID FROM Login WHERE user_Type=? ORDER BY user_ID LIMIT 1');$q->execute([$role]);$sid=$session((int)$q->fetchColumn());
  foreach (['student/schedule.php'=>'Student','faculty/schedule.php'=>'Faculty','admin/master-schedule.php'=>'Admin'] as $path=>$allowed) $check($request($path,$sid)[0]===($role===$allowed?200:403),'Role boundary must be enforced.');
  $check($request('home.php',$sid)[0]===200,'Role home page must render.');
 }
 $students=$pdo->query("SELECT MIN(s.student_ID) id FROM Student s JOIN NB_AcademicStudent p ON p.student_ID=s.student_ID JOIN Enrollment e ON e.student_ID=s.student_ID AND e.semester_ID=1 WHERE s.student_Type='Undergraduate' GROUP BY s.major_ID")->fetchAll(PDO::FETCH_COLUMN);
 $check(count($students)===12,'All 12 majors must have an active portal example.');
 foreach ($students as $id) {
  $sid=$session((int)$id);
  foreach (['current'=>'Fall 2026','next'=>'Spring 2027'] as $term=>$name) {
   [$status,$html]=$request('student/schedule.php?term='.$term,$sid);$check($status===200,'Student schedule must render.');
   $expected=meeting_schedule_group_days(student_schedule_meetings($pdo,(int)$id,$name));
   $dom=new DOMDocument();@$dom->loadHTML($html);$xpath=new DOMXPath($dom);
   $check($xpath->query('//div[contains(@class,"student-schedule")]//tbody/tr')->length===count(array_unique(array_column($expected,'CRN'))),'Each enrollment must have exactly one visible section row.');
   $check($xpath->query('//div[contains(concat(" ",normalize-space(@class)," ")," student-section-meeting ")]')->length===count($expected),'Every exact meeting group must remain visible.');
   $check(!str_contains($html,' · Group '),'Internal roster labels must not appear as section identifiers.');
   $sectionNumbers=[];foreach($expected as$row)$sectionNumbers[(string)($row['display_CRN']??$row['CRN'])]=(string)(int)$row['section_No'];
   foreach($xpath->query('//div[contains(@class,"student-schedule")]//tbody/tr')as$tr){$cells=$xpath->query('./td',$tr);$check(trim($cells->item(3)->textContent)===($sectionNumbers[trim($cells->item(0)->textContent)]??null),'Student section must show the plain schema section number.');}
   $headers=[];foreach($xpath->query('//div[contains(@class,"student-schedule")]//thead/tr/th')as$th)$headers[]=trim($th->textContent);$check($headers===['CRN','Course','Course Name','Section','Professor','Days','Time','Building / Room'],'Student schedule columns must match the owner reference without seat counts.');
   foreach ($expected as $row) {
    foreach ([$row['display_CRN'],$row['display_course_ID'],$row['course_Name'],meeting_schedule_interval($row),$row['Bldg_Name'],$row['room_Number']] as $value) $check(str_contains($html,htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8')),'Schedule content and room location must match the registrar reader.');
   }
   [$tampered,$other]=$request('student/schedule.php?term='.$term.'&student_id=1&user_id=1',$sid);
   $check($tampered===200 && $other===$html,'Request parameters cannot switch the authenticated student.');
   $check(!str_contains($html,'<th scope="col">Grade</th>'),'Current/future schedule must not expose completed grades.');
   if(in_array('--preview',$argv,true)&&$id===$students[0]){ $preview=str_replace('<head>','<head><base href="'.$base.'">',$html);file_put_contents(test_receipt_path('schedule-preview-'.$term.'.json').'.html',$preview); }
  }
 }
 $summary=['status'=>'authenticated-rebuilt-portal-checks-passed','checks'=>$checks,'majorsChecked'=>count($students),'database'=>$database,'baseURL'=>$base,'studentIdentifiersPublished'=>false,'workingDatabaseChanged'=>false];
 $marker=$pdo->query('SELECT candidateSHA256,auditJSON FROM NB_AcademicRebuild WHERE singleton=1')->fetch();$summary['candidateFileSHA256']=$marker['candidateSHA256'];$audit=json_decode($marker['auditJSON'],true,512,JSON_THROW_ON_ERROR);if(isset($audit['fourCampusBuildings'])){$check((int)$pdo->query('SELECT COUNT(*) FROM Building')->fetchColumn()===4,'Entire campus must have four buildings.');$summary['fourBuildingPlanSHA256']=$audit['fourCampusBuildings']['planFileSHA256'];$summary['checks']=$checks;}
 $summary['runtimeSHA256']=test_runtime_hash();file_put_contents(test_receipt_path('portal-http-verification.json'),json_encode($summary,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
 echo json_encode($summary),"\n";
} finally {
 foreach ($sessions as $sid) {session_id($sid);session_start();$_SESSION=[];session_destroy();}
 ob_end_flush();
}
