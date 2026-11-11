<?php
declare(strict_types=1);
/** Read-only catalog-level and pagination regression against the isolated portal. */
if(PHP_SAPI!=='cli')exit;
ob_start();require dirname(__DIR__).'/config/database.php';require dirname(__DIR__).'/includes/schedule.php';require __DIR__.'/rehearsal-receipts.php';
if($database!=='northbridge_ow_rebuild_2026_7'||getenv('NORTHBRIDGE_REHEARSAL_DB')!==$database)throw new RuntimeException('Explicit _7 rehearsal database required.');
$base=getenv('NORTHBRIDGE_HTTP_BASE')?:'http://127.0.0.1:8003/';if($base!=='http://127.0.0.1:8003/')throw new RuntimeException('Designated test preview required.');
$checks=0;$check=static function(bool$ok,string$message)use(&$checks):void{if(!$ok)throw new RuntimeException($message);$checks++;};
$fingerprint=static function()use($pdo):array{$out=[];foreach($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN)as$t)$out[$t]=$pdo->query("CHECKSUM TABLE `$t` EXTENDED")->fetch()['Checksum'];return$out;};$before=$fingerprint();
$catalog=[];foreach($pdo->query('SELECT course_ID,definition_JSON FROM NB_AcademicCourse')as$r)$catalog[$r['course_ID']]=json_decode($r['definition_JSON'],true,512,JSON_THROW_ON_ERROR);
$sectionCount=0;$undergraduateCount=0;$graduateCount=0;
foreach($pdo->query('SELECT DISTINCT semester_ID FROM Course_Section ORDER BY semester_ID')->fetchAll(PDO::FETCH_COLUMN)as$term){
 $rows=schedule_sections($pdo,(int)$term);$ug=schedule_filter_sections($rows,'',0,false,'Undergraduate');$grad=schedule_filter_sections($rows,'',0,false,'Graduate');
 $check(count($rows)===count($ug)+count($grad),'Level filters must partition all sections.');
 foreach($ug as$r){$d=$catalog[$r['course_ID']]??null;$check($d!==null&&$r['display_course_ID']===$d['courseCode']&&$r['course_Name']===$d['title']&&(float)$r['course_Credits']===(float)$d['credits'],'Every undergraduate master row must match its reviewed course definition.');}
 $sectionCount+=count($rows);$undergraduateCount+=count($ug);$graduateCount+=count($grad);
}
$account=$pdo->query("SELECT user_ID,user_Password FROM Login WHERE user_Type='Admin' ORDER BY user_ID LIMIT 1")->fetch();$sid=bin2hex(random_bytes(20));session_id($sid);session_start();$_SESSION=['user_id'=>(int)$account['user_ID'],'user_type'=>'Admin','auth_version'=>hash('sha256',$account['user_Password'])];session_write_close();
$request=static function(array$params)use($base,$sid):array{
 $context=stream_context_create(['http'=>['header'=>"Cookie: PHPSESSID=$sid\r\n",'ignore_errors'=>true,'timeout'=>30]]);$html=file_get_contents($base.'admin/master-schedule.php?'.http_build_query($params),false,$context);$headers=http_get_last_response_headers();
 if($html===false||!str_contains($headers[0],'200')||preg_match('/(?:Warning|Fatal error|Notice):/',$html))throw new RuntimeException('Unexpected master schedule response.');
 $dom=new DOMDocument();@$dom->loadHTML($html);return[$html,new DOMXPath($dom)];
};
try{
 foreach([1,2]as$term){$all=schedule_sections($pdo,$term);
  foreach(['Undergraduate','Graduate','all']as$level){$want=schedule_filter_sections($all,'',0,false,$level);$expected=[];foreach($want as$r)$expected[]=[$r['display_CRN']??(string)$r['CRN'],$r['display_course_ID']??$r['course_ID'],$r['course_Name'],(string)(int)$r['section_No'],(string)$r['maximum_Seats'],(string)$r['available_Seats']];$actual=[];
   for($page=1;$page<=max(1,(int)ceil(count($want)/50));$page++){$params=['semester_id'=>$term,'per_page'=>50,'page'=>$page];if($level!=='Undergraduate')$params['level']=$level;[$html,$xp]=$request($params);
    $check($xp->query('//select[@name="level"]/option[@selected]')->item(0)?->getAttribute('value')===$level,'Default and explicit levels must be selected correctly.');
    $headers=[];foreach($xp->query('//table[contains(@class,"master-schedule-table")]//thead/tr/th')as$th)$headers[]=trim($th->textContent);$wantedHeaders=['CRN','Course','Course Name','Section','Professor','Days','Time','Building / Room','Total Seats','Available Seats'];if($term===1)$wantedHeaders[]='Action';$check($headers===$wantedHeaders,'Master columns must include total seats and available seats before the action.');
    foreach($xp->query('//table[contains(@class,"master-schedule-table")]//tbody/tr')as$tr){$cells=$xp->query('./td',$tr);$actual[]=[trim($cells->item(0)->textContent),trim($cells->item(1)->textContent),trim($cells->item(2)->textContent),trim($cells->item(3)->textContent),trim($cells->item(8)->textContent),trim($cells->item(9)->textContent)];}
    foreach($xp->query('//nav[@aria-label="Schedule pages"]//a')as$link)$check(str_contains($link->getAttribute('href'),'level='.rawurlencode($level)),'Pagination must retain the selected catalog level.');
   }
   $check($actual===$expected,'Every paginated master row must match the selected catalog scope.');
  }
 }
 [$html,$xp]=$request(['semester_id'=>1,'q'=>'ART10501']);$check($xp->query('//table[contains(@class,"master-schedule-table")]//tbody/tr')->length===0,'Legacy graduate code must not leak into the undergraduate default.');
 [$html,$xp]=$request(['semester_id'=>1,'q'=>'ART10501','level'=>'Graduate']);$check($xp->query('//table[contains(@class,"master-schedule-table")]//tbody/tr')->length>0,'Preserved graduate offerings must remain searchable.');
 [$html,$xp]=$request(['semester_id'=>1,'level'=>'Graduate','q'=>'Media']);$clear=$xp->query('//a[text()="Clear Filters"]')->item(0);$check($clear!==null&&str_contains($clear->getAttribute('href'),'level=Graduate'),'Clearing other filters must preserve graduate scope.');
 [$html,$xp]=$request(['semester_id'=>1,'level'=>['Graduate']]);$check($xp->query('//select[@name="level"]/option[@selected]')->item(0)?->getAttribute('value')==='Undergraduate','Invalid level input must fall back safely.');
 $check($fingerprint()===$before,'Master catalog checks must not change any database tables.');
 $marker=$pdo->query('SELECT candidateSHA256 FROM NB_AcademicRebuild WHERE singleton=1')->fetchColumn();$summary=['status'=>'master-catalog-level-and-pagination-checks-passed','checks'=>$checks,'database'=>$database,'candidateFileSHA256'=>$marker,'allTermSectionsChecked'=>$sectionCount,'reviewedUndergraduateSections'=>$undergraduateCount,'preservedGraduateSections'=>$graduateCount,'databaseWrites'=>false];file_put_contents(test_receipt_path('master-catalog-verification.json'),json_encode($summary,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");echo json_encode($summary),"\n";
}finally{session_id($sid);session_start();$_SESSION=[];session_destroy();ob_end_flush();}
