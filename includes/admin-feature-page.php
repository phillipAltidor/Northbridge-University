<?php
declare(strict_types=1);
require_once __DIR__.'/layout.php';
require_role(['Admin']);
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/admin.php';require_admin($pdo);
require_once __DIR__.'/admin-features.php';require_once __DIR__.'/admin-feature-definitions.php';
header('Cache-Control: no-store, private');
if (!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD','POST'],true)) {http_response_code(405);header('Allow: GET, HEAD, POST');exit;}
$def=af_definitions()[$adminFeature??'']??null;if (!$def) {http_response_code(404);exit;}
[$title,$selectors,$fields]=$def;$path='admin/'.$adminFeature.'.php';
$_SESSION['admin_feature_token']??=bin2hex(random_bytes(32));
$canWrite=af_can_write((int)$_SESSION['user_id']);$error='';$snapshot=[];$values=[];$version='';$selected=[];
$notice=$_SESSION['admin_feature_notice']??'';unset($_SESSION['admin_feature_notice']);
foreach ($selectors as $key) $selected[$key]=is_string($_GET[$key]??null)?trim($_GET[$key]):'';
$ready=!in_array('',$selected,true);
try {
 if ($ready && $selectors) {$snapshot=af_snapshot($pdo,$adminFeature,$selected);$values=$snapshot;$version=af_version($snapshot);}
 if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!$canWrite || !is_string($_POST['form_token']??null) || !hash_equals($_SESSION['admin_feature_token'],$_POST['form_token'])) {http_response_code(403);throw new DomainException('Saving is not authorized. Reload the page and check your access.');}
  if (!$ready) throw new DomainException('Select and load the record first.');
  $input=$selected;foreach (array_merge($fields,['version']) as $k) $input[$k]=is_string($_POST[$k]??null)?trim($_POST[$k]):'';
  $values=$input;$version=$input['version'];
  $_SESSION['admin_feature_notice']=af_save($pdo,$adminFeature,$input,(int)$_SESSION['user_id']);
  header('Location: '.app_url($path).($selected?'?'.http_build_query($selected):''),true,303);exit;
 }
} catch (DomainException $e) {if (http_response_code()!==403) http_response_code(400);$error=$e->getMessage();}
catch (PDOException $e) {http_response_code(409);$error='The change could not be saved. Check for a duplicate record or missing setup, then load the record again. No partial changes were saved.';}
require_once __DIR__.'/admin-feature-ui.php';
page_start($title,'Admin',$path);
require __DIR__.'/admin-feature-view.php';
page_end();
