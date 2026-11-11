<?php
declare(strict_types=1);

/** Replace only DB_NAME; never print or rewrite unrelated private settings. */
function release_database_setting(string $contents): string
{
    if (preg_match_all('/^DB_NAME=(.*)$/m',$contents,$matches)!==1) throw new RuntimeException('Expected exactly one DB_NAME setting.');
    $value=trim($matches[1][0]," \t\r\"'");
    if (!preg_match('/^[A-Za-z0-9_]+$/D',$value)) throw new RuntimeException('Invalid database setting.');
    return $value;
}

function release_switch_environment(string $path,string $expectedHash,string $from,string $to): array
{
    if (!is_file($path) || is_link($path)) throw new RuntimeException('Regular environment file required.');
    $original=file_get_contents($path);
    if (!hash_equals($expectedHash,hash('sha256',$original)) || release_database_setting($original)!==$from) throw new RuntimeException('Environment changed since preflight.');
    if (!preg_match('/^northbridge_ow_rebuild_2026_[4-9][0-9]*$/D',$to)) throw new RuntimeException('Invalid release database.');
    $changed=preg_replace_callback('/^DB_NAME=.*$/m',static fn($m)=>'DB_NAME='.$to.(str_ends_with($m[0],"\r")?"\r":''),$original);
    $temporary=$path.'.release-'.bin2hex(random_bytes(6));
    try {
        if (file_put_contents($temporary,$changed,LOCK_EX)!==strlen($changed)) throw new RuntimeException('Unable to prepare environment.');
        chmod($temporary,fileperms($path)&0777);
        if (!hash_equals($expectedHash,hash_file('sha256',$path))) throw new RuntimeException('Environment changed during switch.');
        if (!rename($temporary,$path)) throw new RuntimeException('Unable to replace environment.');
    } finally { if (is_file($temporary)) unlink($temporary); }
    return ['beforeSHA256'=>$expectedHash,'afterSHA256'=>hash('sha256',$changed),'from'=>$from,'to'=>$to];
}

function release_fingerprints(PDO $db): array
{
    $out=[];
    foreach ($db->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as $row) {
        $table=$row[0];if(!preg_match('/^[A-Za-z0-9_]+$/D',$table))throw new RuntimeException('Invalid table identifier.');
        $out[$table]=[(int)$db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn(),(string)$db->query("CHECKSUM TABLE `$table` EXTENDED")->fetch()['Checksum']];
    }ksort($out);return $out;
}

function release_definition_hashes(PDO $db): array
{
    $tables=[];foreach(array_keys(release_fingerprints($db)) as $table)$tables[$table]=preg_replace('/ AUTO_INCREMENT=\d+/','',$db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1]);ksort($tables);
    $triggers=[];foreach($db->query('SHOW TRIGGERS') as $t)$triggers[$t['Trigger']]=[$t['Table'],$t['Timing'],$t['Event'],$t['Statement']];ksort($triggers);
    return ['schema'=>hash('sha256',json_encode($tables,JSON_THROW_ON_ERROR)),'triggers'=>hash('sha256',json_encode($triggers,JSON_THROW_ON_ERROR))];
}

function release_runtime_hash(string $root): string
{
    $files=[];foreach(['admin','assets','config','faculty','includes','student'] as $dir)foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir,FilesystemIterator::SKIP_DOTS)) as $file)if($file->isFile()&&in_array($file->getExtension(),['php','css','js'],true))$files[$file->getPathname()]=hash_file('sha256',$file->getPathname());
    foreach(glob($root.'/*.php') as $file)$files[$file]=hash_file('sha256',$file);ksort($files);
    return hash('sha256',json_encode($files,JSON_THROW_ON_ERROR));
}
