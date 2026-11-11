<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/config/database.php';
$arguments=getopt('', ['source:', 'target:', 'receipt-name:', 'receipt-directory:']);$source=$arguments['source']??'northbridge_ow_2026_test';$target=$arguments['target']??'northbridge_ow_rebuild_2026';
if(!in_array($host,['127.0.0.1','localhost'],true)||($database!=='northbridge_university'&&!preg_match('/^northbridge_ow_rebuild_2026_[4-9][0-9]*$/D',$database)))throw new RuntimeException('Expected local portal configuration.');
if((!in_array($source,['northbridge_ow_2026_test','northbridge_university'],true)&&!preg_match('/^northbridge_ow_rebuild_2026(?:_\d+)?$/D',$source))||!preg_match('/^northbridge_ow_(?:rebuild|restore)_2026(?:_\d+)?$/',$target)||$source===$target)throw new RuntimeException('Unsafe source/target.');
$receiptDirectory=dirname(__DIR__).'/database/staging/academic-alignment';
if(isset($arguments['receipt-directory'])){$real=realpath($arguments['receipt-directory']);if(!$real||!str_starts_with($real,'/private/tmp/'))throw new RuntimeException('Existing private receipt directory required.');$receiptDirectory=$real;}
$receiptName=$arguments['receipt-name']??($target==='northbridge_ow_rebuild_2026'?'rebuild-snapshot-receipt.json':'restore-rehearsal-receipt.json');
if(!in_array($receiptName,['rebuild-snapshot-receipt.json','restore-rehearsal-receipt.json','two-term-snapshot-receipt.json'],true))throw new RuntimeException('Unexpected receipt filename.');
$q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?');$q->execute([$target]);if((int)$q->fetchColumn())throw new RuntimeException('Target exists; refusing to overwrite.');
umask(0077);$dir='/private/tmp/northbridge-rebuild-snapshot-'.bin2hex(random_bytes(6));mkdir($dir,0700);
$quote=static fn($s)=>'"'.str_replace(["\\",'"',"\n","\r"],["\\\\",'\\"','\\n','\\r'],(string)$s).'"';$cnf=$dir.'/client.cnf';file_put_contents($cnf,"[client]\nhost=".$quote($host)."\nport=".$quote($port)."\nuser=".$quote($username)."\npassword=".$quote($password)."\n");
$tables=$pdo->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='.$pdo->quote($source)." AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
$fingerprint=static function(string$schema)use($pdo,$tables):array{$out=[];foreach($tables as$t){if(!preg_match('/^[A-Za-z0-9_]+$/',$t))throw new RuntimeException('Unexpected table identifier.');$name='`'.$schema.'`.`'.$t.'`';$out[$t]=[(int)$pdo->query('SELECT COUNT(*) FROM '.$name)->fetchColumn(),(string)$pdo->query('CHECKSUM TABLE '.$name.' EXTENDED')->fetch()['Checksum']];}return$out;};
$run=static function(array$cmd,array$fd):void{$p=proc_open($cmd,$fd,$pipes);if(!is_resource($p)||proc_close($p)!==0)throw new RuntimeException('Snapshot client failed; inspect its private error log.');};
try{
 $before=$fingerprint($source);$dump=$dir.'/database.sql';$errors=$dir.'/errors.log';$bin='/opt/homebrew/opt/mysql-client/bin/';
 $run([$bin.'mysqldump','--defaults-extra-file='.$cnf,'--single-transaction','--quick','--no-tablespaces','--column-statistics=0','--set-gtid-purged=OFF','--routines','--triggers','--skip-comments','--hex-blob',$source],[0=>['file','/dev/null','r'],1=>['file',$dump,'w'],2=>['file',$errors,'a']]);
 $sql=file_get_contents($dump);if(!$sql||preg_match('/(?:`'.preg_quote($source,'/').'`|\b'.preg_quote($source,'/').')\s*\./i',$sql)||preg_match('/^\s*(?:CREATE|DROP)\s+DATABASE\b/im',$sql))throw new RuntimeException('Unsafe/empty snapshot SQL.');unset($sql);
 if($fingerprint($source)!==$before)throw new RuntimeException('Source changed during snapshot.');
 $collation=$pdo->query('SELECT DEFAULT_CHARACTER_SET_NAME,DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='.$pdo->quote($source))->fetch();foreach($collation as$v)if(!preg_match('/^[A-Za-z0-9_]+$/',$v))throw new RuntimeException('Invalid charset/collation.');
 $pdo->exec('CREATE DATABASE `'.$target.'` CHARACTER SET '.$collation['DEFAULT_CHARACTER_SET_NAME'].' COLLATE '.$collation['DEFAULT_COLLATION_NAME']);
 $run([$bin.'mysql','--defaults-extra-file='.$cnf,'--default-character-set=utf8mb4',$target],[0=>['file',$dump,'r'],1=>['file',$dir.'/import.log','w'],2=>['file',$errors,'a']]);
 if($fingerprint($target)!==$before||$fingerprint($source)!==$before)throw new RuntimeException('Restore fingerprint verification failed.');
 $receipt=['status'=>'snapshot-and-restore-verified','source'=>$source,'restoredTarget'=>$target,'tablesVerified'=>count($tables),'privateDirectory'=>$dir,'snapshotSHA256'=>hash_file('sha256',$dump),'tableFingerprints'=>$before,'sourceUnchanged'=>true,'portalConfigurationChanged'=>false];file_put_contents($dir.'/receipt.json',json_encode($receipt,JSON_PRETTY_PRINT)."\n");
 $public=$receipt;unset($public['tableFingerprints']);file_put_contents($receiptDirectory.'/'.$receiptName,json_encode($public,JSON_PRETTY_PRINT)."\n");echo json_encode($public,JSON_PRETTY_PRINT),"\n";
}finally{if(file_exists($cnf))unlink($cnf);}
