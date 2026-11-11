<?php
declare(strict_types=1);
/** Exercise derived-count repair and rollback without committing fixture changes. */
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/config/database.php';
require dirname(__DIR__).'/includes/schedule.php';
if($database!=='northbridge_ow_rebuild_2026_7')throw new RuntimeException('Isolated test clone required.');
$before=$pdo->query('CHECKSUM TABLE Full_Time_Faculty EXTENDED')->fetch()['Checksum'];
try {
 $pdo->beginTransaction();
 if(schedule_sync_faculty_counts($pdo)!==0)throw new RuntimeException('Expected consistent starting counters.');
 $pdo->exec('UPDATE Full_Time_Faculty SET no_of_Classes=no_of_Classes+1 ORDER BY faculty_ID LIMIT 1');
 if(schedule_sync_faculty_counts($pdo)!==1)throw new RuntimeException('Expected exactly one corrected fixture counter.');
 if(schedule_sync_faculty_counts($pdo)!==0)throw new RuntimeException('Synchronization must be idempotent.');
 $pdo->rollBack();
} catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack();throw $e; }
if($pdo->query('CHECKSUM TABLE Full_Time_Faculty EXTENDED')->fetch()['Checksum']!==$before)throw new RuntimeException('Fixture rollback changed faculty counts.');
echo "PASS: stale faculty count corrected, idempotence verified, fixture rolled back.\n";
