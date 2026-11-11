<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/layout.php';
require_role(['Faculty']);
require_once __DIR__.'/../includes/academic/degree-audit.php';
require_once __DIR__.'/../includes/academic/degree-worksheet.php';
header('Cache-Control: no-store, private');header('X-Robots-Tag: noindex, nofollow');
if (!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD'],true)) {http_response_code(405);header('Allow: GET, HEAD');exit('Degree Audit is read-only.');}
$error='';$audit=null;$matches=[];$query=$_GET['q']??'';$id=$_GET['student_id']??'';
if (!is_string($query) || mb_strlen($query)>100 || !is_string($id) || ($id!=='' && !preg_match('/^[1-9][0-9]{0,9}$/D',$id))) {http_response_code(400);$error='Enter a valid student name or student ID.';$query='';$id='';}
try {
    if (!$error) {
        $pdo->exec('SET TRANSACTION READ ONLY');$pdo->beginTransaction();
        $service=new \Northbridge\Academic\DegreeAuditService($pdo);
        if ($id!=='') {$audit=$service->audit((int)$id);if (!$audit || ($audit['availability']??'')==='graduate-not-configured') {http_response_code(404);$audit=null;$error='An undergraduate degree audit could not be found for that student.';}}
        elseif (trim($query)!=='') $matches=$service->search(trim($query));
    }
} catch (Throwable $e) {http_response_code(503);$error='Degree audits are temporarily unavailable. Please try again later.';}
finally {if ($pdo->inTransaction()) $pdo->rollBack();}
page_start('Student Degree Audit','Faculty','faculty/degree-audit.php');
?>
<link rel="stylesheet" href="<?=e(app_url('assets/css/degree-audit.css'))?>">
<form class="audit-search" method="get" action="<?=e(app_url('faculty/degree-audit.php'))?>"><label for="audit-student-search">Find an undergraduate student</label><div><input id="audit-student-search" name="q" value="<?=e($query)?>" maxlength="100" placeholder="Student ID or name" required><button class="btn" type="submit">Search</button></div></form>
<?php if ($error):?><p class="alert error" role="alert"><?=e($error)?></p><?php elseif ($audit): \Northbridge\Academic\worksheet_render($audit,$service);elseif (trim($query)!==''):?>
<?php if (!$matches):?><p>No matching undergraduate students found.</p><?php else:?><p>Showing up to 25 matches. Select a student to view their read-only audit.</p><div class="audit-table-scroll"><table><thead><tr><th scope="col">Student ID</th><th scope="col">Student</th><th scope="col">Primary major</th><th scope="col">Action</th></tr></thead><tbody><?php foreach ($matches as $person):?><tr><td><?=e($person['student_ID'])?></td><td><?=e(implode(' ',array_filter([$person['first_Name'],$person['middle_Name'],$person['last_Name']])))?></td><td><?=e($person['major_Name'])?></td><td><a href="<?=e(app_url('faculty/degree-audit.php').'?'.http_build_query(['student_id'=>$person['student_ID']]))?>">View audit</a></td></tr><?php endforeach;?></tbody></table></div><?php endif;endif;?>
<?php page_end(); ?>
