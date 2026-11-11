<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit;
ob_start();
require_once __DIR__.'/rehearsal-receipts.php';
require dirname(__DIR__).'/config/database.php';
$sessions=[];$checks=0;
$base=getenv('NORTHBRIDGE_HTTP_BASE')?:'http://127.0.0.1:8000/';
if(!in_array($base,['http://127.0.0.1:8000/','http://127.0.0.1:8001/','http://127.0.0.1:8002/','http://127.0.0.1:8003/','http://127.0.0.1:8004/','http://127.0.0.1:8005/'],true))throw new RuntimeException('Local test server required.');
$check=static function(bool $ok,string $label)use(&$checks):void {if (!$ok) throw new RuntimeException($label);$checks++;};
$request=static function(string $path,string $sid='',string $method='GET')use($base):array {
    $context=stream_context_create(['http'=>['method'=>$method,'header'=>$sid?"Cookie: PHPSESSID=$sid\r\n":'','follow_location'=>0,'ignore_errors'=>true,'timeout'=>30]]);
    $html=file_get_contents($base.$path,false,$context);$headers=http_get_last_response_headers();preg_match('/HTTP\/\S+ (\d+)/',$headers[0],$m);
    if ($html===false || preg_match('/(?:Warning|Fatal error|Notice|Deprecated):/',$html)) throw new RuntimeException('Unexpected PHP response output.');
    return [(int)$m[1],$html,$headers];
};
$makeSession=static function(int $id)use($pdo,&$sessions):string {
    $q=$pdo->prepare('SELECT user_ID,user_Type,user_Password FROM Login WHERE user_ID=?');$q->execute([$id]);$row=$q->fetch();
    $sid=bin2hex(random_bytes(20));session_id($sid);session_start();$_SESSION=['user_id'=>(int)$row['user_ID'],'user_type'=>$row['user_Type'],'auth_version'=>hash('sha256',$row['user_Password'])];session_write_close();$sessions[]=$sid;return $sid;
};
$fingerprint=static function()use($pdo):array {$out=[];foreach (['User','Login','Student','Enrollment','Student_History','Student_Major','Student_Minor','NB_AcademicStudent'] as $table) $out[$table]=$pdo->query("CHECKSUM TABLE `$table` EXTENDED")->fetch()['Checksum'];return $out;};
$before=$fingerprint();
try {
    foreach (['student/degree-audit.php','faculty/degree-audit.php'] as $path) $check($request($path)[0]===302,'Anonymous audit requests redirect.');
    $roleSessions=[];
    foreach (['Student','Faculty','Admin','StatStaff'] as $role) {
        $q=$pdo->prepare('SELECT user_ID FROM Login WHERE user_Type=? ORDER BY user_ID LIMIT 1');$q->execute([$role]);$roleSessions[$role]=$makeSession((int)$q->fetchColumn());
        foreach (['student/degree-audit.php'=>'Student','faculty/degree-audit.php'=>'Faculty'] as $path=>$allowed) $check($request($path,$roleSessions[$role])[0]===($role===$allowed?200:403),'Audit role boundary enforced.');
    }
    $ids=$pdo->query("SELECT MIN(s.student_ID) id FROM Student s JOIN NB_AcademicStudent n ON n.student_ID=s.student_ID WHERE s.student_Type='Undergraduate' GROUP BY s.major_ID")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        $sid=$makeSession((int)$id);[$status,$html,$headers]=$request('student/degree-audit.php',$sid);
        $check($status===200 && str_contains($html,'data-audit-student="'.$id.'"'),'Own undergraduate audit renders.');
        $check((bool)array_filter($headers,static fn($h)=>stripos($h,'Cache-Control:')===0 && str_contains($h,'no-store')),'Audit is not cached.');
        [$tampered,$other]=$request('student/degree-audit.php?student_id=1&user_id=1',$sid);
        $check($tampered===200 && $other===$html,'URL cannot switch the authenticated student audit.');
        $dom=new DOMDocument();@$dom->loadHTML($html);$xpath=new DOMXPath($dom);
        $check($xpath->query('//div[contains(@class,"degree-worksheet")]//form | //div[contains(@class,"degree-worksheet")]//input')->length===0,'Student audit has no editing fields.');
        $check(str_contains($html,'General Education Requirements') && str_contains($html,'In progress') && str_contains($html,'Preregistered'),'Completion and pending phases are visible.');
        [$facultyStatus,$faculty]=$request('faculty/degree-audit.php?student_id='.$id,$roleSessions['Faculty']);
        $check($facultyStatus===200 && str_contains($faculty,'data-audit-student="'.$id.'"'),'Faculty can select an undergraduate audit.');
    }
    $id=(int)$ids[0];
    $check($request('faculty/degree-audit.php?q='.$id,$roleSessions['Faculty'])[0]===200,'Faculty student search works.');
    $check($request('faculty/degree-audit.php?student_id=999999999',$roleSessions['Faculty'])[0]===404,'Unknown student returns not found.');
    foreach (['student_id[]=1','student_id=1%20OR%201=1','q[]=test','q='.str_repeat('a',101)] as $query) $check($request('faculty/degree-audit.php?'.$query,$roleSessions['Faculty'])[0]===400,'Invalid faculty selector rejected.');
    [$status,$html]=$request('faculty/degree-audit.php?q=%3Cscript%3Ealert(1)%3C%2Fscript%3E',$roleSessions['Faculty']);
    $check($status===200 && !str_contains($html,'<script>alert(1)</script>'),'Faculty search text is escaped.');
    foreach (['student/degree-audit.php'=>'Student','faculty/degree-audit.php?student_id='.$id=>'Faculty'] as $path=>$role) $check($request($path,$roleSessions[$role],'POST')[0]===405,'Audit endpoints reject writes.');
    $graduate=(int)$pdo->query("SELECT student_ID FROM Student WHERE student_Type='Graduate' LIMIT 1")->fetchColumn();
    [$status,$html]=$request('student/degree-audit.php',$makeSession($graduate));$check($status===200 && str_contains($html,'Graduate audit requirements have not been configured'),'Graduate scope is stated without fabricated results.');
    $check($request('faculty/degree-audit.php?student_id='.$graduate,$roleSessions['Faculty'])[0]===404,'Faculty selector is restricted to undergraduate audits.');
    if (in_array('--preview',$argv,true)) {
        $freshman=(int)$pdo->query("SELECT s.student_ID FROM Student s JOIN Major m ON m.major_ID=s.major_ID JOIN Enrollment e ON e.student_ID=s.student_ID AND e.semester_ID=1 WHERE s.student_Year=1 AND m.major_Name='Computer and Information Science, B.S.' ORDER BY s.student_ID LIMIT 1")->fetchColumn();
        $sid=$makeSession($freshman);$html=$request('student/degree-audit.php',$sid)[1];$file='/private/tmp/northbridge-live-degree-audit.html';file_put_contents($file,str_replace('<head>','<head><base href="http://127.0.0.1:8000/">',$html));chmod($file,0600);
        $private='/private/tmp/northbridge-degree-audit-preview-session.json';file_put_contents($private,json_encode(['sid'=>$sid,'url'=>'http://127.0.0.1:8000/student/degree-audit.php']));chmod($private,0600);
        // This one temporary preview session is cleaned up by the visual check.
        $sessions=array_values(array_filter($sessions,static fn($s)=>$s!==$sid));
    }
    $check($fingerprint()===$before,'Audit requests do not modify academic or account data.');
    $summary=['status'=>'degree-audit-authenticated-http-checks-passed','checks'=>$checks,'majorsChecked'=>count($ids),'studentIsolationVerified'=>true,'facultySelectionVerified'=>true,'graduateAuditsIncluded'=>false,'databaseWrites'=>false];
    $summary['runtimeSHA256']=test_runtime_hash();$summary['database']=$database;$summary['baseURL']=$base;file_put_contents(test_receipt_path('student-degree-audit-http-verification.json'),json_encode($summary,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");echo json_encode($summary),"\n";
} finally {
    foreach ($sessions as $sid) {session_id($sid);session_start();$_SESSION=[];session_destroy();}
    ob_end_flush();
}
