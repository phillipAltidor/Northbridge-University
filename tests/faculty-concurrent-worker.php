<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
require dirname(__DIR__).'/includes/faculty-features.php';
$input=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
if (!preg_match('/^northbridge_ruba_faculty_fixture_[a-f0-9]{10}$/D',$input['database']??'')) throw new RuntimeException('Only disposable faculty fixtures are supported.');
$private=dirname(__DIR__).'/.local';$config=parse_ini_file($private.'/mysql-admin.cnf',true,INI_SCANNER_RAW)['client'];
$pdo=new PDO('mysql:unix_socket='.$private.'/mysql.sock;dbname='.$input['database'].';charset=utf8mb4','root',$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
if ((int)$pdo->query('SELECT @@port')->fetchColumn()!==3307) throw new RuntimeException('Wrong local server.');
try {
 if ($input['mode']==='address') faculty_save_address($pdo,1,$input['values'],$input['version']);
 elseif ($input['mode']==='attendance') faculty_save_marks($pdo,1,101,'attendance',$input['values'],$input['version'],['Fall 2026'=>['attendance_dates'=>['2026-11-19']]],new DateTimeImmutable('2026-11-19T09:15:00-05:00'));
 else throw new RuntimeException('Unsupported fixture operation.');
 echo 'saved';
} catch (DomainException $e) {
 if (!str_contains($e->getMessage(),'changed')) throw $e;
 echo 'stale';
}
