<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/config/database.php';
require dirname(__DIR__, 2) . '/includes/academic-timeline.php';
if ($host !== '127.0.0.1' || $database !== 'northbridge_university') throw new RuntimeException('Expected local project database.');
$timeline = require dirname(__DIR__, 2) . '/config/academic-timeline.php';
$apply = in_array('--apply', $argv, true);
$affected = ['Enrollment' => 'enrollment.sql', 'Student_History' => 'student_history.sql', 'Faculty_History' => 'faculty_history.sql', 'Attendance' => 'attendance.sql'];

require_once __DIR__ . '/data_helpers.php';

$originalFiles = [];
$backup = null;
$report = ['as_of' => $timeline['as_of'], 'current_semester' => $timeline['current_semester'], 'next_semester' => $timeline['next_semester']];
try {
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->beginTransaction();
    // Lock affected terms before taking the recoverable snapshot and changing rows.
    $pdo->query('SELECT semester_ID FROM Semester ORDER BY semester_ID FOR UPDATE')->fetchAll();
    $before = [];
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) $before[$table] = timeline_rows($pdo, $table);
    $termStatus = [];
    foreach ($before['Semester'] as $term) $termStatus[$term['semester_ID']] = academic_semester_status($term['semester_Name']);
    $sections = array_column($before['Course_Section'], null, 'CRN');
    foreach (['Enrollment', 'Student_History', 'Faculty_History'] as $table) {
        foreach ($before[$table] as $row) {
            if (!isset($sections[$row['CRN']]) || $sections[$row['CRN']]['semester_ID'] !== $row['semester_ID']) throw new RuntimeException('Inconsistent semester reference in ' . $table);
        }
    }
    $expected = [];
    foreach ($affected as $table => $file) {
        $rows = [];
        foreach ($before[$table] as $row) {
            $semesterID = $table === 'Attendance' ? ($sections[$row['CRN']]['semester_ID'] ?? null) : $row['semester_ID'];
            $status = $termStatus[$semesterID] ?? throw new RuntimeException('Missing semester.');
            if ($table === 'Enrollment' && $status !== 'Completed') $row['Grade'] = null;
            if (in_array($table, ['Student_History', 'Faculty_History'], true) && $status !== 'Completed') continue;
            if ($table === 'Attendance' && ($status === 'Upcoming' || ($status === 'In progress' && $row['attendance_Date'] > $timeline['as_of']))) continue;
            $rows[] = $row;
        }
        $expected[$table] = $rows;
        $report[$table] = ['before' => count($before[$table]), 'after' => count($rows)];
    }
    $report['cleared_final_grades'] = count(array_filter($before['Enrollment'], static fn($r) => $termStatus[$r['semester_ID']] !== 'Completed' && $r['Grade'] !== null));
    if (!$apply) {
        $pdo->rollBack();
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\nAudit only; use --apply to back up, update, and synchronize seeds.\n";
        exit;
    }
    $backup = '/private/tmp/northbridge-timeline-backup-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    if (!mkdir($backup, 0700)) throw new RuntimeException('Cannot create backup directory.');
    $handle = fopen($backup . '/database.sql', 'x');
    if (!$handle || !chmod($backup . '/database.sql', 0600)) throw new RuntimeException('Cannot create private database backup.');
    fwrite($handle, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
    foreach ($before as $table => $rows) {
        $ddl = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];
        if (fwrite($handle, "DROP TABLE IF EXISTS `$table`;\n$ddl;\n" . timeline_sql($pdo, $table, $rows)) === false) throw new RuntimeException('Backup write failed.');
    }
    fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n"); fclose($handle);
    foreach ($affected as $table => $file) {
        $path = dirname(__DIR__) . '/data/' . $file;
        $originalFiles[$path] = file_get_contents($path);
        if (file_put_contents($backup . '/' . $file, $originalFiles[$path]) === false) throw new RuntimeException('Seed backup failed.');
        chmod($backup . '/' . $file, 0600);
    }
    $incomplete = array_keys(array_filter($termStatus, static fn($s) => $s !== 'Completed'));
    $future = array_keys(array_filter($termStatus, static fn($s) => $s === 'Upcoming'));
    foreach ($incomplete as $id) {
        $pdo->prepare('UPDATE Enrollment SET Grade = NULL WHERE semester_ID = ? AND Grade IS NOT NULL')->execute([$id]);
        $pdo->prepare('DELETE FROM Student_History WHERE semester_ID = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM Faculty_History WHERE semester_ID = ?')->execute([$id]);
        $statement = $pdo->prepare('DELETE a FROM Attendance a JOIN Course_Section cs ON cs.CRN = a.CRN WHERE cs.semester_ID = ?' . (in_array($id, $future, true) ? '' : ' AND a.attendance_Date > ?'));
        $statement->execute(in_array($id, $future, true) ? [$id] : [$id, $timeline['as_of']]);
    }
    foreach ($before as $table => $rows) {
        if (timeline_rows($pdo, $table) !== ($expected[$table] ?? $rows)) throw new RuntimeException('Unexpected database changes: ' . $table);
    }
    // Temporary tables validate the exported SQL without committing active changes.
    foreach ($affected as $table => $file) {
        $path = dirname(__DIR__) . '/data/' . $file;
        $exportRows = timeline_export_order($pdo, $table, $expected[$table], $originalFiles[$path]);
        $export = timeline_sql($pdo, $table, $exportRows);
        $temp = 'timeline_verify_' . $table;
        $pdo->exec("CREATE TEMPORARY TABLE `$temp` LIKE `$table`");
        $pdo->exec(str_replace("INSERT INTO `$table`", "INSERT INTO `$temp`", $export));
        if (timeline_rows($pdo, $temp) !== $expected[$table]) throw new RuntimeException('Export reload mismatch: ' . $table);
        $pdo->exec("DROP TEMPORARY TABLE `$temp`");
        if (file_put_contents($path, '-- Academic snapshot: ' . $timeline['as_of'] . "; past records retained; current/future final grades excluded.\n\n" . $export) === false) throw new RuntimeException('Seed synchronization failed.');
    }
    $report['protected_tables_and_past_records'] = 'PASS';
    $report['seed_reload'] = 'PASS';
    if (file_put_contents($backup . '/audit.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) throw new RuntimeException('Audit write failed.');
    $pdo->commit();
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\nBackup: $backup\nApplied successfully; accounts, schedules, enrollment registrations, and past results preserved.\n";
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    foreach ($originalFiles as $path => $contents) file_put_contents($path, $contents);
    throw $error;
}
