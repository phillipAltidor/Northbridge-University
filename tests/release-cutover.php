<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/release-cutover.php';
require __DIR__.'/release-preview-target.php';
$path=tempnam('/private/tmp','northbridge-env-test-');chmod($path,0600);$checks=0;
$assert=static function(bool $ok)use(&$checks):void{if(!$ok)throw new RuntimeException('Cutover guard regression.');$checks++;};
try {
    $original="DB_NAME=northbridge_ow_rebuild_2026_5\r\nOTHER_SETTING=preserve-this\r\n";file_put_contents($path,$original);
    $receipt=release_switch_environment($path,hash('sha256',$original),'northbridge_ow_rebuild_2026_5','northbridge_ow_rebuild_2026_8');
    $assert(release_database_setting(file_get_contents($path))==='northbridge_ow_rebuild_2026_8');
    $assert(file_get_contents($path)==="DB_NAME=northbridge_ow_rebuild_2026_8\r\nOTHER_SETTING=preserve-this\r\n");
    $assert((fileperms($path)&0777)===0600);
    release_switch_environment($path,$receipt['afterSHA256'],'northbridge_ow_rebuild_2026_8','northbridge_ow_rebuild_2026_5');
    $assert(file_get_contents($path)===$original);
    foreach([
        [str_repeat('0',64),'northbridge_ow_rebuild_2026_5','northbridge_ow_rebuild_2026_8'],
        [hash('sha256',$original),'northbridge_ow_rebuild_2026_7','northbridge_ow_rebuild_2026_8'],
        [hash('sha256',$original),'northbridge_ow_rebuild_2026_5','invalid;DROP DATABASE'],
    ] as $case){try{release_switch_environment($path,...$case);throw new LogicException('Expected rejection.');}catch(RuntimeException $e){$assert(file_get_contents($path)===$original);}}
    foreach(['DB_NAME=a\nDB_NAME=b',"DB_NAME=a\nDB_NAME=b\n",'NO_DATABASE=x'] as $invalid){try{release_database_setting($invalid);throw new LogicException('Expected rejection.');}catch(RuntimeException $e){$checks++;}}
    $old=getenv('NORTHBRIDGE_REHEARSAL_DB');
    foreach(['northbridge_ow_rebuild_2026_7'=>'http://127.0.0.1:8003/','northbridge_ow_rebuild_2026_8'=>'http://127.0.0.1:8004/'] as $db=>$url){putenv('NORTHBRIDGE_REHEARSAL_DB='.$db);test_release_preview_target($db,$url);$checks++;}
    foreach(['http://127.0.0.1:8003/','https://example.com/'] as $url){try{test_release_preview_target('northbridge_ow_rebuild_2026_8',$url);throw new LogicException('Expected target rejection.');}catch(RuntimeException $e){$checks++;}}
    putenv($old===false?'NORTHBRIDGE_REHEARSAL_DB':'NORTHBRIDGE_REHEARSAL_DB='.$old);
    echo "PASS: $checks environment switch, rollback, stale-state and invalid-setting checks; no real configuration changed.\n";
} finally { unlink($path); }
