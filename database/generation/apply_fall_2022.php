<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('NORTHBRIDGE_AUDIT_ONLY', true);
require __DIR__ . '/stage_fall_2022.php';
require dirname(__DIR__, 2) . '/config/database.php';
if ($host !== '127.0.0.1' || $database !== 'northbridge_university') throw new RuntimeException('Expected local database.');
$source=json_decode(file_get_contents('/private/tmp/northbridge-source.json'),true,512,JSON_THROW_ON_ERROR);
$plan=json_decode(file_get_contents('/private/tmp/northbridge-fall2022-approved-plan.json'),true,512,JSON_THROW_ON_ERROR);
$staging=json_decode(file_get_contents('/private/tmp/northbridge-fall2022-stage.json'),true,512,JSON_THROW_ON_ERROR);
if (($staging['report']['result']??null)!=='PASS') throw new RuntimeException('Staging approval is required.');
function canonical_rows(array $rows): array {
    $encoded=array_map(static fn($row)=>json_encode($row,JSON_THROW_ON_ERROR),$rows);
    sort($encoded,SORT_STRING); return $encoded;
}
function sql_rows(PDO $connection,string $table,array $rows): string {
    if (!$rows) return '';
    $columns=implode(',',array_map(static fn($column)=>"`$column`",array_keys($rows[0])));
    $chunks=[];
    foreach(array_chunk($rows,250) as $batch) {
        $values=[];
        foreach($batch as $row) $values[]='('.implode(',',array_map(static fn($value)=>$value===null?'NULL':$connection->quote((string)$value),array_values($row))).')';
        $chunks[]="INSERT INTO `$table` ($columns) VALUES\n".implode(",\n",$values).";\n";
    }
    return implode("\n",$chunks);
}
$pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
$pdo->beginTransaction();
foreach($source as $table=>$rows) if(canonical_rows($rows)!==canonical_rows($pdo->query("SELECT * FROM `$table` FOR UPDATE")->fetchAll())) throw new RuntimeException("Active $table changed since planning. Regenerate before applying.");
$backup=dirname(__DIR__,3).'/Northbridge-database-backups/fall2022_'.date('Ymd_His');
if (!mkdir($backup,0700,true)) throw new RuntimeException('Cannot create backup directory.');
$dump="SET FOREIGN_KEY_CHECKS=0;\n";
foreach($source as $table=>$rows) $dump.="DROP TABLE IF EXISTS `$table`;\n".$pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1].";\n".sql_rows($pdo,$table,$rows);
$dump.="SET FOREIGN_KEY_CHECKS=1;\n";
if(file_put_contents($backup.'/database.sql',$dump)===false) throw new RuntimeException('Cannot write backup.');
chmod($backup.'/database.sql',0600);
$files=[];
foreach($plan['replacement_tables'] as $table=>$rows) {
    $filename=strtolower($table).'.sql';
    if($table==='Course_Section') {
        $filename='historical_course_section.sql';
        $rows=array_values(array_filter($rows,static fn($row)=>!in_array($row['semester_ID'],[1,2],true)));
    }
    $path=dirname(__DIR__).'/data/'.$filename;
    if(!is_file($path)) throw new RuntimeException("Unknown seed mapping: $table");
    $files[$path]="-- Generated Fall 2022 cohort rebuild; existing accounts preserved.\n\n".sql_rows($pdo,$table,$rows);
}
$manifest=dirname(__DIR__).'/data/historical_cohorts.json';
$stageName=$staging['database'];
if(!preg_match('/^northbridge_fall2022_stage_[0-9_]+$/',$stageName)) throw new RuntimeException('Invalid staging database name.');
$seedStage=new PDO("mysql:host=$host;port=$port;dbname=$stageName;charset=utf8mb4",$username,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$seedStage->exec('SET FOREIGN_KEY_CHECKS=0');
foreach($plan['replacement_tables'] as $table=>$rows) {
    $filename=$table==='Course_Section'?'historical_course_section.sql':strtolower($table).'.sql';
    $seedStage->exec("DELETE FROM `$table`".($table==='Course_Section'?' WHERE semester_ID NOT IN(1,2)':''));
    $seedStage->exec($files[dirname(__DIR__).'/data/'.$filename]);
}
$seedStage->exec('SET FOREIGN_KEY_CHECKS=1');
audit($seedStage,$source,$plan);
$files[$manifest]=json_encode(['semester'=>'Fall 2022','population'=>$plan['population'],'continuing_students'=>$plan['continuing_students'],'generated_profiles'=>$plan['cohort']],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
$originals=[];
foreach($files as $path=>$contents) {
    $originals[$path]=is_file($path)?file_get_contents($path):null;
    if($originals[$path]!==null) file_put_contents($backup.'/'.basename($path),$originals[$path]);
}
try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach($plan['replacement_tables'] as $table=>$rows) { $pdo->exec("DELETE FROM `$table`"); insert_rows($pdo,$table,$rows); }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    $report=audit($pdo,$source,$plan);
    foreach($files as $path=>$contents) if(file_put_contents($path,$contents)===false) throw new RuntimeException("Cannot synchronize $path");
    $pdo->commit();
} catch(Throwable $error) {
    if($pdo->inTransaction()) $pdo->rollBack();
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    foreach($originals as $path=>$contents) { if($contents===null) {if(is_file($path)) unlink($path);} else file_put_contents($path,$contents); }
    throw $error;
}
$report['backup']=$backup;
$report['supporting_semesters']=$plan['summaries'];
$report['existing_accounts_preserved']=count($source['User']);
$report['generated_profiles']=count($plan['cohort']);
$appointments=[];
foreach($source['Faculty_Department'] as $row) $appointments[$row['faculty_ID'].':'.$row['dept_ID']]=$row['date_Of_Appointment'];
$report['historical_appointment_dates_adjusted']=0;
foreach($plan['replacement_tables']['Faculty_Department']??[] as $row) if($appointments[$row['faculty_ID'].':'.$row['dept_ID']]!==$row['date_Of_Appointment']) $report['historical_appointment_dates_adjusted']++;
$report['seed_reload_audit']='PASS';
$directory=dirname(__DIR__).'/reports';
if(!is_dir($directory)) mkdir($directory,0755,true);
file_put_contents($directory.'/fall_2022_rebuild_audit.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
if(preg_match('/^northbridge_fall2022_stage_[0-9_]+$/',$staging['database'])) $pdo->exec("DROP DATABASE `{$staging['database']}`");
echo json_encode($report,JSON_PRETTY_PRINT),PHP_EOL;
