<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') {http_response_code(404);exit;}
define('NORTHBRIDGE_AUDIT_ONLY',true);
require __DIR__.'/stage_fall_2022.php';
require dirname(__DIR__,2).'/config/database.php';
if($host!=='127.0.0.1' || $database!=='northbridge_university') throw new RuntimeException('Expected local database.');
$options=getopt('',['source:','terms:','stage-only','apply']);
if(array_key_exists('stage-only',$options) && array_key_exists('apply',$options)) throw new RuntimeException('Choose staging or application, not both.');
$stageOnly=!array_key_exists('apply',$options);
$sourcePath=$options['source']??'/private/tmp/northbridge-semesters-source.json';
if(!str_starts_with($sourcePath,'/private/tmp/')) throw new RuntimeException('Expected a private snapshot.');
$working=json_decode(file_get_contents($sourcePath),true,512,JSON_THROW_ON_ERROR);
$terms=isset($options['terms'])?array_map('intval',explode(',',$options['terms'])):[4,5,6,7,8,9,10,2];
foreach($terms as $term) if(!in_array($term,[4,5,6,7,8,9,10,2],true)) throw new RuntimeException('Fall 2026 and previously completed terms are protected.');
function normalized(array $rows): array {
    $encoded=array_map('json_encode',$rows);sort($encoded,SORT_STRING);return $encoded;
}
function export_rows(PDO $connection,string $table,array $rows): string {
    if(!$rows)return '';
    $columns=implode(',',array_map(static fn($column)=>"`$column`",array_keys($rows[0])));
    $sql="-- Generated semester data; Fall 2026 records preserved.\n\n";
    foreach(array_chunk($rows,250) as $batch) {
        $values=[];
        foreach($batch as $row) $values[]='('.implode(',',array_map(static fn($v)=>$v===null?'NULL':$connection->quote((string)$v),array_values($row))).')';
        $sql.="INSERT INTO `$table` ($columns) VALUES\n".implode(",\n",$values).";\n\n";
    }
    return rtrim($sql)."\n";
}
foreach($working as $table=>$rows) if(normalized($rows)!==normalized($pdo->query("SELECT * FROM `$table`")->fetchAll())) throw new RuntimeException("Active $table differs from planning snapshot.");
$name='northbridge_semesters_stage_'.date('Ymd_His');
$pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4");
$stage=new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",$username,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$manifestPath=dirname(__DIR__).'/data/historical_cohorts.json';
$manifest=json_decode(file_get_contents($manifestPath),true,512,JSON_THROW_ON_ERROR);
$stage->exec('SET FOREIGN_KEY_CHECKS=0');
foreach($working as $table=>$rows) {$stage->exec($pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1]);insert_rows($stage,$table,$rows);}
$stage->exec('SET FOREIGN_KEY_CHECKS=1');
$reports=[];
try {
    foreach($terms as $term) {
        $planPath="/private/tmp/northbridge-semesters-plan/".($stageOnly?'':'approved_')."term_$term.json";
        if(!is_file($planPath)) throw new RuntimeException('A reviewed staging plan is required before application.');
        $plan=json_decode(file_get_contents($planPath),true,512,JSON_THROW_ON_ERROR);
        if($plan['target_id']!==$term) throw new RuntimeException('Unexpected plan target.');
        $hashes=[];
        foreach($working['Login'] as $row) $hashes[$row['user_ID']]=$row['user_Password'];
        if(isset($plan['replacement_tables']['Login'])) foreach($plan['replacement_tables']['Login'] as &$row) {
            if($row['user_Password']===null) $row['user_Password']=$hashes[$row['user_ID']]??password_hash(bin2hex(random_bytes(24)),PASSWORD_BCRYPT,['cost'=>10]);
        }
        unset($row);
        $stage->beginTransaction();
        $stage->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach($plan['replacement_tables'] as $table=>$rows) {$stage->exec("DELETE FROM `$table`");insert_rows($stage,$table,$rows);}
        $stage->exec('SET FOREIGN_KEY_CHECKS=1');
        $report=audit($stage,$working,$plan);
        $files=[];
        foreach($plan['replacement_tables'] as $table=>$rows) {
            if($table==='Course_Section') {
                $files[dirname(__DIR__).'/data/historical_course_section.sql']=export_rows($pdo,$table,array_values(array_filter($rows,static fn($r)=>!in_array($r['semester_ID'],[1,2],true))));
                if($term===2) $files[dirname(__DIR__).'/data/course_section.sql']=export_rows($pdo,$table,array_values(array_filter($rows,static fn($r)=>in_array($r['semester_ID'],[1,2],true))));
            } else {
                $path=dirname(__DIR__).'/data/'.strtolower($table).'.sql';
                if(!is_file($path)) throw new RuntimeException("Unknown seed mapping: $table");
                $files[$path]=export_rows($pdo,$table,$rows);
            }
        }
        // Reload the exported SQL itself so teammates receive the audited dataset.
        foreach($plan['replacement_tables'] as $table=>$rows) {
            if($table==='Course_Section') {
                $stage->exec('SET FOREIGN_KEY_CHECKS=0');
                $stage->exec('DELETE FROM Course_Section WHERE semester_ID NOT IN(1,2)');
                $stage->exec($files[dirname(__DIR__).'/data/historical_course_section.sql']);
                if($term===2) {$stage->exec('DELETE FROM Course_Section WHERE semester_ID IN(1,2)');$stage->exec($files[dirname(__DIR__).'/data/course_section.sql']);}
            } else {
                $stage->exec('SET FOREIGN_KEY_CHECKS=0');
                $stage->exec("DELETE FROM `$table`");
                $stage->exec($files[dirname(__DIR__).'/data/'.strtolower($table).'.sql']);
            }
        }
        $stage->exec('SET FOREIGN_KEY_CHECKS=1');
        audit($stage,$working,$plan);
        $stage->commit();
        echo json_encode(['target'=>$plan['target_semester'],'stage'=>'PASS','summary'=>$report['summary']]),PHP_EOL;
        if($stageOnly) {
            foreach($plan['replacement_tables'] as $table=>$rows)$working[$table]=$rows;
            $report['seed_reload_audit']='PASS';
            $report['cohort_ids']=$plan['cohort_ids'];
            $reports[]=$report;
            file_put_contents("/private/tmp/northbridge-semesters-plan/approved_term_$term.json",json_encode($plan,JSON_THROW_ON_ERROR));
            chmod("/private/tmp/northbridge-semesters-plan/approved_term_$term.json",0600);
            unset($files,$hashes,$plan);
            continue;
        }
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        $pdo->beginTransaction();
        foreach($working as $table=>$rows) if(normalized($rows)!==normalized($pdo->query("SELECT * FROM `$table` FOR UPDATE")->fetchAll())) throw new RuntimeException("Active $table changed during staging.");
        $backup=dirname(__DIR__,3).'/Northbridge-database-backups/semester_'.$term.'_'.date('Ymd_His');
        if(!mkdir($backup,0700,true)) throw new RuntimeException('Cannot create backup.');
        $dump="SET FOREIGN_KEY_CHECKS=0;\n";
        foreach($working as $table=>$rows) $dump.="DROP TABLE IF EXISTS `$table`;\n".$pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1].";\n".export_rows($pdo,$table,$rows);
        if(file_put_contents($backup.'/database.sql',$dump."SET FOREIGN_KEY_CHECKS=1;\n")===false) throw new RuntimeException('Cannot write backup.');
        chmod($backup.'/database.sql',0600);unset($dump);
        $manifest['semesters'][$plan['target_semester']]=['population'=>1600,'student_IDs'=>$plan['cohort_ids'],'registered_students'=>$plan['registered_students']];
        $manifest['progression_profiles']=$plan['cohort_metadata'];
        $files[$manifestPath]=json_encode($manifest,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
        $originals=[];
        foreach($files as $path=>$contents) {$originals[$path]=file_get_contents($path);file_put_contents($backup.'/'.basename($path),$originals[$path]);}
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            foreach($plan['replacement_tables'] as $table=>$rows) {$pdo->exec("DELETE FROM `$table`");insert_rows($pdo,$table,$rows);}
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            $report=audit($pdo,$working,$plan);
            foreach($files as $path=>$contents) if(file_put_contents($path,$contents)===false) throw new RuntimeException("Cannot synchronize $path");
            $pdo->commit();
        } catch(Throwable $error) {
            if($pdo->inTransaction())$pdo->rollBack();
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            foreach($originals as $path=>$contents)file_put_contents($path,$contents);
            throw $error;
        }
        foreach($plan['replacement_tables'] as $table=>$rows)$working[$table]=$rows;
        $pdo->exec('SET TRANSACTION READ ONLY');$pdo->beginTransaction();
        $report=audit($pdo,$working,$plan);$pdo->rollBack();
        $report['backup']=$backup;
        $report['seed_reload_audit']='PASS';
        $report['cohort_ids']=$plan['cohort_ids'];
        $reports[]=$report;
        $path=dirname(__DIR__).'/reports/semester_'.$term.'_rebuild_audit.json';
        if(file_put_contents($path,json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n")===false)throw new RuntimeException('Cannot save audit report.');
        echo json_encode(['target'=>$plan['target_semester'],'committed'=>'PASS','summary'=>$report['summary'],'protected_fall2026'=>'UNCHANGED']),PHP_EOL;
        unset($files,$originals,$hashes,$plan);
    }
    $pdo->exec("DROP DATABASE `$name`");
    if($stageOnly) {
        $path='/private/tmp/northbridge-semesters-stage-audits.json';
        file_put_contents($path,json_encode($reports,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
        echo json_encode(['semesters_staged'=>count($reports),'active_database'=>'UNCHANGED','result'=>'PASS']),PHP_EOL;
    } else echo json_encode(['semesters_completed'=>count($reports),'result'=>'PASS']),PHP_EOL;
} catch(Throwable $error) {
    if($pdo->inTransaction())$pdo->rollBack();
    if($stage->inTransaction())$stage->rollBack();
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    $pdo->exec("DROP DATABASE `$name`");
    throw $error;
}
