<?php
declare(strict_types=1);
/** Fresh local development install only. Never overwrites an existing database or .env. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('memory_limit','512M');
require dirname(__DIR__).'/includes/release-cutover.php';
$root=dirname(__DIR__);$normalizedRoot=str_replace('\\','/',$root);$args=getopt('',['target:','create','compare-active','receipt:']);
$target=$args['target']??'';
if(!preg_match('/^northbridge_ow_rebuild_2026_[4-9][0-9]*$/D',$target)||in_array($target,['northbridge_ow_rebuild_2026_5','northbridge_ow_rebuild_2026_7','northbridge_ow_rebuild_2026_8'],true))throw new RuntimeException('Choose a new development database name; _5, _7 and _8 are reserved.');
$env=[];$envPath=$root.'/.env';
if(!is_file($envPath))throw new RuntimeException('Create your private .env from .env.example and set local MySQL credentials first.');
foreach(file($envPath,FILE_IGNORE_NEW_LINES) as $line){$line=trim($line);if($line===''||str_starts_with($line,'#')||!str_contains($line,'='))continue;[$key,$value]=explode('=',$line,2);$env[trim($key)]=trim(trim($value),"\"'");}
$host=$env['DB_HOST']??'127.0.0.1';$port=$env['DB_PORT']??'3306';
if(!in_array($host,['127.0.0.1','localhost'],true)||!ctype_digit((string)$port))throw new RuntimeException('Fresh installer supports local MySQL only.');
if(($env['DB_NAME']??null)===$target)throw new RuntimeException('Choose a new database different from your currently configured DB_NAME.');
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC];
$pdo=new PDO("mysql:host=$host;port=$port;charset=utf8mb4",$env['DB_USER']??'root',$env['DB_PASS']??'',$options);
$q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?');$q->execute([$target]);
if((int)$q->fetchColumn())throw new RuntimeException('Target database already exists; refusing to overwrite it.');
$manifestPath=$root.'/database/releases/v11/manifest.json';$manifest=json_decode(file_get_contents($manifestPath),true,512,JSON_THROW_ON_ERROR);
if(!in_array($manifest['status'],['v11-installation-bundle-prepared-awaiting-clean-install','v11-installation-bundle-clean-install-verified'],true)||count($manifest['seedSHA256'])!==25||count($manifest['academicTableFingerprints'])!==29)throw new RuntimeException('Complete V11 installation manifest required.');
$sqlFiles=[];
foreach($manifest['installationFileSHA256'] as $file=>$hash){
    $path=realpath($root.'/'.$file);
    if(!$path||!str_starts_with(str_replace('\\','/',$path),$normalizedRoot.'/database/')||!hash_equals($hash,hash_file('sha256',$path)))throw new RuntimeException('Installation input missing or changed: '.$file);
}
foreach($manifest['loadOrder'] as $file){
    if(!isset($manifest['installationFileSHA256'][$file]))throw new RuntimeException('Unverified load-order input.');
    $sql=file_get_contents($root.'/'.$file);
    if($file==='database/schema.sql'){$sql=preg_replace('/\AUSE northbridge_university;\s*/','',$sql,1,$removed);if($removed!==1)throw new RuntimeException('Unexpected base schema header.');}
    if(preg_match('/^\s*(?:USE\s|(?:CREATE|DROP)\s+DATABASE\b|SET\s+FOREIGN_KEY_CHECKS\s*=\s*0)/im',$sql))throw new RuntimeException('Cross-database or disabled-FK SQL is not allowed.');
    $sqlFiles[$file]=$sql;
}
$charset=$manifest['databaseDefaults']['DEFAULT_CHARACTER_SET_NAME'];$collation=$manifest['databaseDefaults']['DEFAULT_COLLATION_NAME'];
foreach([$charset,$collation] as $value)if(!preg_match('/^[A-Za-z0-9_]+$/D',$value))throw new RuntimeException('Invalid database defaults.');
$summary=['status'=>'fresh-v11-install-planned','targetDatabase'=>$target,'tablesExpected'=>53,'seedFiles'=>25,'academicTables'=>29,'sourceServerTested'=>$manifest['testedDatabaseServer'],'currentServer'=>$pdo->query('SELECT VERSION()')->fetchColumn(),'currentDatabaseConnectionUnchanged'=>true];
if(!isset($args['create'])){echo json_encode($summary,JSON_PRETTY_PRINT),"\nDry run only; use --create to install into this new database.\n";exit;}
$active=null;$activeBefore=null;$envHash=hash_file('sha256',$envPath);
if(isset($args['compare-active'])){
    if(($env['DB_NAME']??null)!=='northbridge_ow_rebuild_2026_8')throw new RuntimeException('Active comparison is restricted to the reviewed local _8 release.');
    $active=new PDO("mysql:host=$host;port=$port;dbname=northbridge_ow_rebuild_2026_8;charset=utf8mb4",$env['DB_USER']??'root',$env['DB_PASS']??'',$options);$activeBefore=release_fingerprints($active);
}
$pdo->exec("CREATE DATABASE `$target` CHARACTER SET $charset COLLATE $collation");$pdo->exec("USE `$target`");
foreach($sqlFiles as $file=>$sql){$pdo->exec($sql);if($pdo->query('SELECT DATABASE()')->fetchColumn()!==$target)throw new RuntimeException('Installer connection escaped target.');}
// Seed sections already contain final availability. Install triggers after rosters.
foreach(require $root.'/database/migrations/section_seat_triggers.php' as $sql)$pdo->exec($sql);
$fingerprints=release_fingerprints($pdo);$definitions=release_definition_hashes($pdo);
if(count($fingerprints)!==53||$definitions['schema']!==$manifest['expectedSchemaSHA256']||$definitions['triggers']!==$manifest['expectedTriggersSHA256'])throw new RuntimeException('Installed schema/triggers differ from the verified release; retain the new database for inspection.');
$mismatches=[];foreach($manifest['academicTableFingerprints'] as $table=>$expected)if(($fingerprints[$table]??null)!==$expected)$mismatches[]=$table;
if($mismatches)throw new RuntimeException('Installed academic data mismatch in: '.implode(', ',$mismatches));
$foreignKeys=$pdo->query('SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL')->fetchAll();
foreach($foreignKeys as $fk){foreach($fk as $identifier)if(!preg_match('/^[A-Za-z0-9_]+$/D',$identifier))throw new RuntimeException('Unexpected FK identifier.');$sql="SELECT COUNT(*) FROM `{$fk['TABLE_NAME']}` c WHERE c.`{$fk['COLUMN_NAME']}` IS NOT NULL AND NOT EXISTS(SELECT 1 FROM `{$fk['REFERENCED_TABLE_NAME']}` p WHERE p.`{$fk['REFERENCED_COLUMN_NAME']}`=c.`{$fk['COLUMN_NAME']}`)";if((int)$pdo->query($sql)->fetchColumn())throw new RuntimeException('Foreign-key coverage failed.');}
if(!hash_equals($envHash,hash_file('sha256',$envPath))||($active&&release_fingerprints($active)!==$activeBefore))throw new RuntimeException('Main environment or active data changed during installation.');
$summary=array_replace($summary,['status'=>'fresh-v11-install-verified','tablesVerified'=>53,'academicTablesMatched'=>29,'foreignKeysVerified'=>count($foreignKeys),'schemaSHA256'=>$definitions['schema'],'triggersSHA256'=>$definitions['triggers'],'candidateFileSHA256'=>$manifest['candidateFileSHA256'],'scheduleFileSHA256'=>$manifest['scheduleFileSHA256'],'activeDatabaseUnchanged'=>$active?true:null,'environmentUnchanged'=>true]);
if(isset($args['receipt'])){$path=$args['receipt'];$parent=realpath(dirname($path));$normalizedParent=$parent?str_replace('\\','/',$parent):'';if(!$parent||(!str_starts_with($normalizedParent,'/private/tmp/')&&$normalizedParent!==$normalizedRoot.'/database/releases/v11'))throw new RuntimeException('Use an approved private or release receipt directory.');file_put_contents($path,json_encode($summary,JSON_PRETTY_PRINT)."\n");}
echo json_encode($summary,JSON_PRETTY_PRINT),"\nPrivate .env unchanged; select this new database yourself after reviewing the result.\n";
