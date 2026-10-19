<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__,2).'/config/database.php';
if($host!=='127.0.0.1' || $database!=='northbridge_university') throw new RuntimeException('Expected local database.');
$path=$argv[1]??'/private/tmp/northbridge-source.json';
if(!str_starts_with($path,'/private/tmp/')) throw new RuntimeException('Snapshots must stay in private temporary storage.');
if(is_file($path)) throw new RuntimeException('A snapshot already exists. Archive it before creating another.');
$pdo->exec('SET TRANSACTION READ ONLY');
$pdo->beginTransaction();
try {
    $tables=[];
    foreach($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) $tables[$table]=$pdo->query("SELECT * FROM `$table`")->fetchAll();
    $pdo->commit();
    $handle=fopen($path,'x');
    if($handle===false) throw new RuntimeException('Cannot create private snapshot.');
    chmod($path,0600);
    fwrite($handle,json_encode($tables,JSON_THROW_ON_ERROR));
    fclose($handle);
    echo "Private snapshot created.\n";
} catch(Throwable $error) {if($pdo->inTransaction()) $pdo->rollBack();throw $error;}
