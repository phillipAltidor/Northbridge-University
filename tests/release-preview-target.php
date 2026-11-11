<?php
declare(strict_types=1);
/** Explicit paired local targets; permit direct release-copy tests without relabeling receipts. */
function test_release_preview_target(string $database,string $base): void
{
    if(in_array('--main-smoke',$GLOBALS['argv']??[],true)&&$database==='northbridge_ow_rebuild_2026_8'&&$base==='http://127.0.0.1:8000/'&&!getenv('NORTHBRIDGE_REHEARSAL_DB')){
        require_once dirname(__DIR__).'/includes/release-cutover.php';
        if(release_database_setting(file_get_contents(dirname(__DIR__).'/.env'))!==$database)throw new RuntimeException('Main connection does not select the release.');
        return;
    }
    $pairs=['northbridge_ow_rebuild_2026_7'=>'http://127.0.0.1:8003/','northbridge_ow_rebuild_2026_8'=>'http://127.0.0.1:8004/','northbridge_ow_rebuild_2026_9'=>'http://127.0.0.1:8005/'];
    if(getenv('NORTHBRIDGE_REHEARSAL_DB')!==$database || ($pairs[$database]??null)!==$base)throw new RuntimeException('Explicit isolated database and paired local preview required.');
}
