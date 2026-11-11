<?php
declare(strict_types=1);
/** CLI-owned alternate receipt directory keeps isolated checks separate. */
function test_receipt_path(string $name): string {
    if(!preg_match('/^[a-z0-9-]+\.json$/D',$name))throw new RuntimeException('Unexpected receipt name.');
    $directory=getenv('NORTHBRIDGE_RECEIPT_DIR');
    if($directory!==false&&$directory!==''){$real=realpath($directory);if(!$real||!str_starts_with($real,'/private/tmp/'))throw new RuntimeException('Private rehearsal receipt directory required.');return $real.'/'.$name;}
    return dirname(__DIR__).'/database/staging/academic-alignment/'.$name;
}
