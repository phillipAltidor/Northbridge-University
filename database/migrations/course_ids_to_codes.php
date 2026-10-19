<?php
// Run from the project folder: php database/migrations/course_ids_to_codes.php
// Local migration only. MySQL DDL auto-commits; a full SQL backup is saved first.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/config/database.php';
if ($host !== '127.0.0.1' || $database !== 'northbridge_local') {
    throw new RuntimeException('This migration is restricted to northbridge_local at 127.0.0.1.');
}
$prefixes = require dirname(__DIR__, 2) . '/config/course-prefixes.php';
$q = $pdo->prepare("SELECT TABLE_NAME, COLUMN_NAME, IS_NULLABLE, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND COLUMN_NAME IN ('course_ID','prerequisite_course_ID') ORDER BY TABLE_NAME, ORDINAL_POSITION");
$q->execute([$database]);
$columns = $q->fetchAll();
if (!$columns) throw new RuntimeException('No course identifier columns found.');
$types = array_unique(array_column($columns, 'DATA_TYPE'));
if ($types === ['varchar']) { echo "Course IDs are already text; no changes made.\n"; exit; }
foreach ($types as $type) if ($type !== 'int') throw new RuntimeException('Unexpected/mixed course ID types; review before migrating.');
$mapping = [];
foreach ($pdo->query('SELECT c.course_ID, d.dept_Name FROM Course c JOIN Department d ON d.dept_ID=c.dept_ID') as $course) {
    $old = (string) $course['course_ID'];
    if (!isset($prefixes[$course['dept_Name']])) throw new RuntimeException('No prefix assigned for department ' . $course['dept_Name']);
    $mapping[$old] = $prefixes[$course['dept_Name']] . $old;
}
if (count($mapping) !== (int) $pdo->query('SELECT COUNT(*) FROM Course')->fetchColumn() || count(array_unique($mapping)) !== count($mapping)) throw new RuntimeException('Missing department or duplicate course code.');
$q = $pdo->prepare("SELECT k.TABLE_NAME,k.CONSTRAINT_NAME,k.COLUMN_NAME,k.REFERENCED_COLUMN_NAME,r.UPDATE_RULE,r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.TABLE_NAME=k.TABLE_NAME AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA=? AND k.REFERENCED_TABLE_NAME='Course' ORDER BY k.TABLE_NAME,k.CONSTRAINT_NAME");
$q->execute([$database]); $keys = $q->fetchAll();
$q = $pdo->prepare("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND DATA_TYPE='json'");
$q->execute([$database]); $jsonColumns=[]; foreach ($q as $c) $jsonColumns[$c['TABLE_NAME']][]=$c['COLUMN_NAME'];
function recode_value($value, array $mapping, ?string $key = null) {
    if (in_array($key, ['course_ID','prerequisite_course_ID'], true)) {
        $id=(string)$value;
        if (isset($mapping[$id])) return $mapping[$id];
        if (in_array($id,$mapping,true)) return $id;
        throw new RuntimeException('Unknown course ID in existing data or history: ' . $id);
    }
    if (is_array($value)) {
        foreach ($value as $k=>$v) $value[$k]=recode_value($v,$mapping,is_string($k)?$k:null);
        if (!array_is_list($value)) ksort($value);
    }
    return $value;
}
function table_digest(PDO $pdo, string $table, array $mapping, array $jsonColumns): string {
    $digests=[];
    foreach ($pdo->query("SELECT * FROM `$table`") as $row) {
        foreach ($row as $k=>$v) {
            if (in_array($k,$jsonColumns[$table]??[],true)) $row[$k]=recode_value(json_decode($v,true,512,JSON_THROW_ON_ERROR),$mapping);
            elseif (in_array($k,['course_ID','prerequisite_course_ID'],true)) $row[$k]=recode_value($v,$mapping,$k);
        }
        ksort($row); $digests[]=hash('sha256',json_encode($row,JSON_THROW_ON_ERROR));
    }
    sort($digests); return hash('sha256',implode('',$digests));
}
$tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
// Preflight all references and JSON history before any DDL.
$before=[]; foreach($tables as $t) $before[$t]=table_digest($pdo,$t,$mapping,$jsonColumns);
$backup=rtrim(sys_get_temp_dir(),'/').'/northbridge-before-course-codes-'.date('Ymd-His').'.sql';
$handle=fopen($backup,'x'); if(!$handle) throw new RuntimeException('Cannot create backup.');chmod($backup,0600);
fwrite($handle,"-- Full local backup before course-code migration\nSET FOREIGN_KEY_CHECKS=0;\n");
foreach($tables as $t) {
    $ddl=$pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM)[1];
    fwrite($handle,"DROP TABLE IF EXISTS `$t`;\n$ddl;\n");
    foreach($pdo->query("SELECT * FROM `$t`") as $row) {
        $names=implode(',',array_map(static fn($c)=>"`$c`",array_keys($row)));
        $values=implode(',',array_map(static fn($v)=>$v===null?'NULL':$pdo->quote((string)$v),array_values($row)));
        fwrite($handle,"INSERT INTO `$t` ($names) VALUES ($values);\n");
    }
}
fwrite($handle,"SET FOREIGN_KEY_CHECKS=1;\n");fclose($handle);
echo "Backup saved: $backup\n";
$plan=[];
foreach($keys as $k) $plan[]="ALTER TABLE `{$k['TABLE_NAME']}` DROP FOREIGN KEY `{$k['CONSTRAINT_NAME']}`";
// Use a single charset and collation on every FK pair.
$q=$pdo->prepare('SELECT DEFAULT_CHARACTER_SET_NAME,DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?');$q->execute([$database]);$charset=$q->fetch();
foreach($columns as $c) $plan[]="ALTER TABLE `{$c['TABLE_NAME']}` MODIFY COLUMN `{$c['COLUMN_NAME']}` VARCHAR(20) CHARACTER SET {$charset['DEFAULT_CHARACTER_SET_NAME']} COLLATE {$charset['DEFAULT_COLLATION_NAME']} ".($c['IS_NULLABLE']==='NO'?'NOT NULL':'NULL');
file_put_contents($backup.'.plan.json',json_encode(['mapping'=>$mapping,'foreign_keys'=>$keys,'columns'=>$columns,'ddl'=>$plan],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));chmod($backup.'.plan.json',0600);
foreach($plan as $sql) $pdo->exec($sql);
try {
    $pdo->beginTransaction();
    foreach($columns as $c) {
        $stmt=$pdo->prepare("UPDATE `{$c['TABLE_NAME']}` SET `{$c['COLUMN_NAME']}`=? WHERE `{$c['COLUMN_NAME']}`=?");
        foreach($mapping as $old=>$new) $stmt->execute([$new,(string)$old]);
    }
    foreach($jsonColumns as $t=>$fields) {
        $pk=$pdo->query("SHOW KEYS FROM `$t` WHERE Key_name='PRIMARY'")->fetchAll();
        $pkNames=array_column($pk,'Column_name');
        if(!$pkNames) throw new RuntimeException('JSON table needs a primary key: '.$t);
        foreach($pdo->query("SELECT * FROM `$t`")->fetchAll() as $row) {
            foreach($fields as $field) {
                $original=json_decode($row[$field],true,512,JSON_THROW_ON_ERROR);
                $converted=recode_value($original,$mapping);
                if($converted==$original) continue;
                $where=implode(' AND ',array_map(static fn($c)=>"`$c`=?",$pkNames));
                $stmt=$pdo->prepare("UPDATE `$t` SET `$field`=? WHERE $where");
                $params=[json_encode($converted,JSON_THROW_ON_ERROR)]; foreach($pkNames as $p) $params[]=$row[$p];$stmt->execute($params);
            }
        }
    }
    $pdo->commit();
} catch(Throwable $e) {if($pdo->inTransaction())$pdo->rollBack();throw $e;}
foreach($keys as $k) $pdo->exec("ALTER TABLE `{$k['TABLE_NAME']}` ADD CONSTRAINT `{$k['CONSTRAINT_NAME']}` FOREIGN KEY (`{$k['COLUMN_NAME']}`) REFERENCES Course (`{$k['REFERENCED_COLUMN_NAME']}`) ON UPDATE {$k['UPDATE_RULE']} ON DELETE {$k['DELETE_RULE']}");
foreach($tables as $t) if($before[$t]!==table_digest($pdo,$t,$mapping,$jsonColumns)) throw new RuntimeException('Data verification mismatch: '.$t);
echo 'Converted '.count($mapping).' course IDs and '.count($columns).' columns; restored '.count($keys)." foreign keys.\n";
echo "All table contents verified: only course identifiers and their JSON references changed.\n";
