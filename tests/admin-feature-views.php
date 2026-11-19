<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
require dirname(__DIR__).'/includes/auth.php';
require dirname(__DIR__).'/includes/admin-features.php';
require dirname(__DIR__).'/includes/admin-feature-definitions.php';
require dirname(__DIR__).'/includes/admin-feature-ui.php';
require dirname(__DIR__).'/includes/admin-navigation.php';
$pdo=new Pdo\Sqlite('sqlite::memory:');$pdo->createFunction('CONCAT',static fn(...$args)=>implode('',$args));
$pdo->exec(preg_replace('/^USE .*;$/m','',file_get_contents(dirname(__DIR__).'/database/schema.sql')));
$_SERVER['SCRIPT_NAME']='/home.php';$_SESSION['admin_feature_token']='fixture-token';$count=0;
foreach (af_definitions() as $adminFeature=>$def) foreach ([true,false] as $canWrite) {
 [$title,$selectors,$fields]=$def;$path='admin/'.$adminFeature.'.php';$selected=array_fill_keys($selectors,'1');$ready=true;$snapshot=$selectors?['Example'=>'Selected record','requirements'=>[]]:[];$values=[];$version='fixture-version';$notice='';$error='';
 ob_start();require dirname(__DIR__).'/includes/admin-feature-view.php';$html=ob_get_clean();
 $dom=new DOMDocument();@$dom->loadHTML($html);$xp=new DOMXPath($dom);
 if ($xp->query('//form[@method="post"]')->length!==1 || $xp->query('//input[@name="form_token"]')->length!==1) throw new RuntimeException('Missing save/CSRF: '.$adminFeature);
 foreach ($xp->query('//input[not(@type="hidden")]|//select|//textarea') as $el) {
  $id=$el->getAttribute('id');if (!$id||$xp->query('//label[@for="'.$id.'"]')->length!==1) throw new RuntimeException('Missing/duplicate label: '.$adminFeature);
 }
 if ($xp->query('//form[@method="post"]//button[@disabled]')->length!==($canWrite?0:1)) throw new RuntimeException('Read-only save state');
 $count++;
 if (isset($argv[1])&&$canWrite) {
  if (!is_dir($argv[1])) mkdir($argv[1],0700,true);
  $css=file_get_contents(dirname(__DIR__).'/assets/css/style.css').file_get_contents(dirname(__DIR__).'/assets/css/admin-features.css');
  file_put_contents($argv[1].'/'.$adminFeature.'.html','<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>'.$css.'</style><main style="padding:24px"><h1>'.e($title).'</h1>'.$html.'</main>');
 }
}
echo "PASS: $count Admin form states, labels, CSRF and read-only controls.\n";
