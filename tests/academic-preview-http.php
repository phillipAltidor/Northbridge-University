<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
ob_start();
require dirname(__DIR__).'/config/database.php';
require dirname(__DIR__).'/includes/academic/preview.php';
require dirname(__DIR__).'/includes/academic/test-database.php';
if ($database!=='northbridge_university'||!in_array($host,['127.0.0.1','localhost'],true)) throw new RuntimeException('Local test environment required.');
$checks=0;$sessions=[];
function preview_check(bool $ok,string $label): void {global $checks;$checks++;if (!$ok) throw new RuntimeException('FAIL: '.$label);}
function preview_request(string $query='',string $sid='',string $method='GET'): array
{
    $options=['method'=>$method,'header'=>$sid?'Cookie: PHPSESSID='.$sid."\r\n":'','follow_location'=>0,'ignore_errors'=>true,'timeout'=>20];
    $html=file_get_contents('http://127.0.0.1:8000/admin/degree-preview.php'.$query,false,stream_context_create(['http'=>$options]));
    $headers=http_get_last_response_headers();preg_match('/HTTP\/\S+ (\d+)/',$headers[0],$m);
    if ($html===false||preg_match('/(?:Warning|Fatal error|Notice):/',$html)) throw new RuntimeException('Unexpected HTTP/PHP output.');
    return [(int)$m[1],$html,implode("\n",$headers)];
}
$before=academic_legacy_fingerprints($pdo);
$test=\Northbridge\Academic\preview_database();$package=\Northbridge\Academic\preview_package($test);
$copyBefore=academic_legacy_fingerprints($test);$tableBefore=[];
foreach (['NB_CatalogVersion','NB_CourseVersion','NB_ProgramVersion','NB_RequirementGroup','NB_Prerequisite','NB_GEApproval','NB_AcademicPolicy'] as $name) $tableBefore[$name]=(string)$test->query("CHECKSUM TABLE `$name` EXTENDED")->fetch()['Checksum'];
$rejected=false;
try {$test->exec('UPDATE NB_CatalogVersion SET edition=edition WHERE id=-1');}catch(PDOException $e) {$rejected=true;}
preview_check($rejected,'Database connection rejects SQL writes');
$test->rollBack();
try {
    preview_check(preview_request()[0]===302,'Logged-out request redirects');
    $adminSid='';
    foreach (['Admin','Student','Faculty','StatStaff'] as $role) {
        $q=$pdo->prepare('SELECT user_ID,user_Password FROM Login WHERE user_Type=? ORDER BY user_ID LIMIT 1');$q->execute([$role]);$a=$q->fetch();
        $sid=bin2hex(random_bytes(20));session_id($sid);session_start();$_SESSION=['user_id'=>(int)$a['user_ID'],'user_type'=>$role,'auth_version'=>hash('sha256',$a['user_Password'])];session_write_close();$sessions[]=$sid;
        [$status,$html,$headers]=preview_request('',$sid);
        preview_check($status===($role==='Admin'?200:403),$role.' preview role enforced');
        if ($role==='Admin') {
            $adminSid=$sid;
            preview_check(str_contains(strtolower($headers),'cache-control: no-store'),'Preview is not cached');
            preview_check(!str_contains($html,$a['user_Password']),'Credentials excluded from markup');
            $dom=new DOMDocument();@$dom->loadHTML($html);$xpath=new DOMXPath($dom);
            preview_check($xpath->query('//select[@name="program"]/optgroup/option')->length===24,'All 24 programs are available');
            preview_check($xpath->query('//form[@method="post"]')->length===0 && !str_contains($html,'name="student_id"'),'No save form or student selector');
            preview_check(str_contains($html,'Still required:')&&str_contains($html,'In progress'),'Default example displays remaining and pending work');
        } elseif ($role==='Student') {
            session_id($sid);session_start();$_SESSION['user_type']='Admin';session_write_close();
            preview_check(preview_request('',$sid)[0]===403,'Forged role cannot bypass database-backed Admin check');
        }
    }
    preview_check(preview_request('',$adminSid,'POST')[0]===405,'POST rejected');
    foreach (['?program[]=x','?case[]=x','?view[]=progress','?view=invalid','?program=invalid','?case=invalid','?student_id=100002','?user_id=100002','?catalog=1','?view=%3Cscript%3E'] as $query) preview_check(preview_request($query,$adminSid)[0]===400,'Invalid or unsupported input rejected');
    $cases=\Northbridge\Academic\preview_cases();$count=0;
    foreach ($cases as $case) foreach (['complete','progress','review'] as $view) {
        $query='?'.http_build_query(['program'=>$case['programKey'],'case'=>$case['caseKey'],'view'=>$view]);
        [$status,$html]=preview_request($query,$adminSid);$count++;
        preview_check($status===200,'Program/path/example renders');
        preview_check(str_contains($html,'Synthetic student')&&str_contains($html,'Synthetic course history'),'Synthetic-only scope shown');
        if ($view==='complete') preview_check(str_contains($html,'Example meets program requirements'),'Complete example agrees with evaluator');
        if ($view==='progress') preview_check(str_contains($html,'In progress'),'Pending courses visible');
        if ($view==='review') preview_check(str_contains($html,'equivalency needs review'),'Unapproved transfer visible');
        if ($case['name']==='Childhood Education 1-6, B.S.' && $view==='complete') preview_check(str_contains($html,'Building a Classroom Community for All Learners')&&str_contains($html,'2020-2022 version'),'Historical Education title/version preserved');
        if (in_array('--preview',$argv,true) && $case['caseKey']===$cases[0]['caseKey']) {
            $path='/private/tmp/northbridge-degree-preview-'.$view.'.html';file_put_contents($path,str_replace('<head>','<head><base href="http://127.0.0.1:8000/">',$html));chmod($path,0600);
        }
    }
    preview_check($count===111,'All 37 paths and three example modes checked');
    preview_check(academic_legacy_fingerprints($pdo)===$before,'All working legacy tables unchanged');
    $after=\Northbridge\Academic\preview_database();preview_check(academic_legacy_fingerprints($after)===$copyBefore,'All copied legacy tables unchanged');
    foreach ($tableBefore as $name=>$checksum) preview_check((string)$after->query("CHECKSUM TABLE `$name` EXTENDED")->fetch()['Checksum']===$checksum,'Academic table unchanged '.$name);
    $after->rollBack();
    echo "PASS: $checks preview HTTP/read-only checks across $count pages. No database records changed.\n";
} finally {
    foreach ($sessions as $sid) {session_id($sid);session_start();$_SESSION=[];session_destroy();}
}
