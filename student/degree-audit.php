<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/layout.php';
require_role(['Student']);
require_once __DIR__.'/../includes/academic/degree-audit.php';
require_once __DIR__.'/../includes/academic/degree-worksheet.php';
header('Cache-Control: no-store, private');header('X-Robots-Tag: noindex, nofollow');
if (!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD'],true)) {http_response_code(405);header('Allow: GET, HEAD');exit('Degree Audit is read-only.');}
$audit=null;$error='';
try {
    $pdo->exec('SET TRANSACTION READ ONLY');$pdo->beginTransaction();
    $service=new \Northbridge\Academic\DegreeAuditService($pdo);
    // The authenticated identity is the only accepted student selector.
    $audit=$service->audit((int)$_SESSION['user_id']);
    if ($audit===null) {http_response_code(404);$error='Your student record could not be found.';}
} catch (Throwable $e) {http_response_code(503);$error='Your degree audit is temporarily unavailable. Please contact the registrar if this continues.';}
finally {if ($pdo->inTransaction()) $pdo->rollBack();}
page_start('Degree Audit','Student','student/degree-audit.php');
?>
<link rel="stylesheet" href="<?=e(app_url('assets/css/degree-audit.css'))?>">
<?php if ($error):?><p class="alert error" role="alert"><?=e($error)?></p><?php elseif ($audit): \Northbridge\Academic\worksheet_render($audit,$service);endif;?>
<?php page_end(); ?>
