<?php
declare(strict_types=1);
/** Create a new, isolated snapshot for the academic migration. Never overwrites a DB. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/config/database.php';
umask(0077);
if (!in_array($host, ['127.0.0.1', 'localhost'], true)) throw new RuntimeException('Only a local source server is allowed.');
if ($database !== 'northbridge_university') throw new RuntimeException('Unexpected source database; refusing snapshot.');
$client = '/opt/homebrew/opt/mysql-client/bin/mysql';
$dumpClient = '/opt/homebrew/opt/mysql-client/bin/mysqldump';
if (!is_executable($client) || !is_executable($dumpClient)) throw new RuntimeException('Local MySQL client tools are required.');
$base = 'northbridge_ow_2026_test';
$target = $base;
$exists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?');
for ($suffix=0; ; $suffix++) {
    $target = $base . ($suffix ? '_' . $suffix : '');
    $exists->execute([$target]);
    if (!(int)$exists->fetchColumn()) break;
}
if (!preg_match('/^northbridge_ow_2026_test(?:_\d+)?$/', $target) || $target === $database) throw new RuntimeException('Unsafe target name.');
$private = '/private/tmp/northbridge-academic-test-' . bin2hex(random_bytes(6));
if (!mkdir($private, 0700)) throw new RuntimeException('Cannot create private snapshot directory.');
$option = static function (string $value): string {
    return '"' . str_replace(["\\", '"', "\n", "\r"], ["\\\\", '\\"', '\\n', '\\r'], $value) . '"';
};
$defaults = $private . '/client.cnf';
file_put_contents($defaults, "[client]\nhost=".$option($host)."\nport=".$option((string)$port)."\nuser=".$option($username)."\npassword=".$option($password)."\n");
chmod($defaults,0600);
$tables = $pdo->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=' . $pdo->quote($database) . " AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
if (!$tables) throw new RuntimeException('Source contains no tables.');
$fingerprint = static function (PDO $pdo, string $schema, array $tables): array {
    $result=[];
    foreach ($tables as $table) {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) throw new RuntimeException('Unexpected table name.');
        $qualified='`'.$schema.'`.`'.$table.'`';
        $count=(int)$pdo->query('SELECT COUNT(*) FROM '.$qualified)->fetchColumn();
        $checksum=$pdo->query('CHECKSUM TABLE '.$qualified.' EXTENDED')->fetch();
        if (!isset($checksum['Checksum'])) throw new RuntimeException('Table checksum unavailable: '.$table);
        $result[$table]=['rows'=>$count,'checksum'=>(string)$checksum['Checksum']];
    }
    return $result;
};
$before=$fingerprint($pdo,$database,$tables);
$dump=$private.'/source-snapshot.sql';
$log=$private.'/mysql-errors.log';
$run = static function (array $command, array $descriptors): void {
    $process=proc_open($command,$descriptors,$pipes);
    if (!is_resource($process)) throw new RuntimeException('Could not launch MySQL client.');
    $exit=proc_close($process);
    if ($exit !== 0) throw new RuntimeException('MySQL client failed; review the private error log. No credential output is printed.');
};
$created=false;
try {
    $run([$dumpClient,'--defaults-extra-file='.$defaults,'--single-transaction','--quick','--no-tablespaces','--column-statistics=0','--set-gtid-purged=OFF','--routines','--triggers','--skip-comments','--hex-blob',$database],
        [0=>['file','/dev/null','r'],1=>['file',$dump,'w'],2=>['file',$log,'a']]);
    chmod($dump,0600);
    $sql=file_get_contents($dump);
    if ($sql===false || strlen($sql)<100) throw new RuntimeException('Snapshot dump is empty.');
    $sourcePattern=preg_quote($database,'/');
    if (preg_match('/(?:`'.$sourcePattern.'`|\b'.$sourcePattern.')\s*\./i',$sql)
        || preg_match('/\bUSE\s+`?'.$sourcePattern.'`?\b/i',$sql)
        || preg_match('/^\s*(?:CREATE|DROP)\s+DATABASE\b/im',$sql)) {
        throw new RuntimeException('Dump contains database-qualified operations; refusing automatic import.');
    }
    unset($sql);
    if ($fingerprint($pdo,$database,$tables)!==$before) throw new RuntimeException('Source changed during dump; rerun with a fresh snapshot.');
    $definition=$pdo->query('SELECT DEFAULT_CHARACTER_SET_NAME,DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='.$pdo->quote($database))->fetch();
    foreach ($definition as $value) if (!preg_match('/^[a-zA-Z0-9_]+$/',(string)$value)) throw new RuntimeException('Unexpected charset/collation.');
    $pdo->exec('CREATE DATABASE `'.$target.'` CHARACTER SET '.$definition['DEFAULT_CHARACTER_SET_NAME'].' COLLATE '.$definition['DEFAULT_COLLATION_NAME']);
    $created=true;
    $run([$client,'--defaults-extra-file='.$defaults,'--default-character-set=utf8mb4',$target],
        [0=>['file',$dump,'r'],1=>['file',$private.'/import.log','w'],2=>['file',$log,'a']]);
    $after=$fingerprint($pdo,$target,$tables);
    if ($after!==$before) throw new RuntimeException('Target rows/checksums do not match source snapshot. Target retained for inspection.');
    if ($fingerprint($pdo,$database,$tables)!==$before) throw new RuntimeException('Source changed during verification; target retained for inspection.');
    $receipt=['status'=>'verified-isolated-source-snapshot','sourceDatabase'=>$database,'testDatabase'=>$target,
        'tablesVerified'=>count($tables),'tableFingerprints'=>$before,'privateSnapshotDirectory'=>$private,
        'snapshotSHA256'=>hash_file('sha256',$dump),'portalConfigurationChanged'=>false,
        'newCurriculumSchemaApplied'=>false,'backgroundEventsImported'=>false,'sourceDataUnchanged'=>true];
    file_put_contents($private.'/receipt.json',json_encode($receipt,JSON_PRETTY_PRINT)."\n");
    echo json_encode(['status'=>$receipt['status'],'testDatabase'=>$target,'tablesVerified'=>count($tables),'receipt'=>$private.'/receipt.json','sourceDataUnchanged'=>true,'portalConfigurationChanged'=>false,'newCurriculumSchemaApplied'=>false],JSON_PRETTY_PRINT),"\n";
} catch (Throwable $error) {
    file_put_contents($private.'/failure.json',json_encode(['error'=>$error->getMessage(),'target'=>$target,'targetCreated'=>$created,'sourceWritesPerformed'=>false],JSON_PRETTY_PRINT)."\n");
    throw $error;
} finally {
    if (file_exists($defaults)) unlink($defaults); // Remove only this run's temporary credential file.
}
