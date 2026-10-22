<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/config/database.php';
require dirname(__DIR__, 2) . '/includes/schedule.php';
require __DIR__ . '/data_helpers.php';
if ($host !== '127.0.0.1' || $database !== 'northbridge_university') throw new RuntimeException('Expected local project database.');
$timeline = require dirname(__DIR__, 2) . '/config/academic-timeline.php';
$apply = in_array('--apply', $argv, true);
$originals = [];
$report = ['as_of' => $timeline['as_of']];
try {
    $pdo->beginTransaction();
    $pdo->query('SELECT semester_ID FROM Semester ORDER BY semester_ID FOR UPDATE')->fetchAll();
    $before = [];
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) $before[$table] = timeline_rows($pdo, $table);
    $sections = array_column($before['Course_Section'], null, 'CRN');
    $terms = array_column($before['Semester'], 'semester_Name', 'semester_ID');
    $days = [];
    foreach ($before['Time_Slot_Day'] as $row) $days[$row['time_Slot_ID']][] = (int)$row['day_ID'];
    foreach ($days as &$values) sort($values); unset($values);
    $registrations = [];
    foreach ($before['Enrollment'] as $row) $registrations[$row['student_ID'] . ':' . $row['CRN']] = $row;
    $added = [];
    foreach ($before['Student_History'] as $row) {
        if (academic_semester_status($terms[$row['semester_ID']]) !== 'Completed') throw new RuntimeException('Align timeline before applying this migration.');
        $key = $row['student_ID'] . ':' . $row['CRN'];
        $enrollment = array_intersect_key($row, array_flip(['student_ID', 'CRN', 'semester_ID', 'Grade']));
        if (isset($registrations[$key])) {
            if ($registrations[$key]['semester_ID'] !== $row['semester_ID'] || $registrations[$key]['Grade'] !== $row['Grade']) throw new RuntimeException('Historical enrollment/grade conflict; manual review required.');
        } else { $added[] = $enrollment; $registrations[$key] = $enrollment; }
    }
    $attendance = [];
    $retimed = 0; $excluded = 0;
    foreach ($before['Attendance'] as $row) {
        $section = $sections[$row['CRN']];
        $status = academic_semester_status($terms[$section['semester_ID']]);
        $date = new DateTimeImmutable($row['attendance_Date']);
        $day = (int)$date->format('N');
        $meetingDays = $days[$section['time_Slot_ID']] ?? [];
        if (!$meetingDays) throw new RuntimeException('Missing meeting-day mapping.');
        if (!in_array($day, $meetingDays, true)) {
            if ($status !== 'In progress') throw new RuntimeException('Historical attendance needs manual calendar review.');
            $laterDays = array_values(array_filter($meetingDays, static fn($d) => $d >= $day));
            if (!$laterDays) throw new RuntimeException('Cannot map generated attendance to a later meeting in the same week.');
            $row['attendance_Date'] = $date->modify('+' . ($laterDays[0] - $day) . ' days')->format('Y-m-d');
            $retimed++;
        }
        if ($status === 'Upcoming' || $row['attendance_Date'] > $timeline['as_of']) { $excluded++; continue; }
        $key = $row['student_ID'] . ':' . $row['CRN'] . ':' . $row['attendance_Date'];
        // Legacy Monday/Wednesday entries for a Friday section collapse to one meeting.
        if (!isset($attendance[$key]) || $row['Present_Absent'] === 'Absent') $attendance[$key] = $row;
    }
    $report += ['historical_enrollments_added' => count($added), 'generated_attendance_retimed' => $retimed, 'future_meeting_entries_excluded' => $excluded, 'attendance_before' => count($before['Attendance']), 'attendance_after' => count($attendance)];
    if (!$apply) { $pdo->rollBack(); echo json_encode($report, JSON_PRETTY_PRINT), "\nAudit only; use --apply.\n"; exit; }
    $backup = '/private/tmp/northbridge-schedule-backup-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    if (!mkdir($backup, 0700)) throw new RuntimeException('Cannot create backup directory.');
    $handle = fopen($backup . '/database.sql', 'x');
    if (!$handle) throw new RuntimeException('Cannot create backup.');
    chmod($backup . '/database.sql', 0600);
    fwrite($handle, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
    foreach ($before as $table => $rows) {
        $ddl = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];
        if (fwrite($handle, "DROP TABLE IF EXISTS `$table`;\n$ddl;\n" . timeline_sql($pdo, $table, $rows)) === false) throw new RuntimeException('Backup write failed.');
    }
    foreach ($pdo->query('SHOW TRIGGERS')->fetchAll() as $trigger) {
        $ddl = $pdo->query('SHOW CREATE TRIGGER `' . $trigger['Trigger'] . '`')->fetch();
        fwrite($handle, "DELIMITER $$\n" . $ddl['SQL Original Statement'] . "$$\nDELIMITER ;\n");
    }
    fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n"); fclose($handle);
    $statement = $pdo->prepare('INSERT INTO Enrollment (student_ID, CRN, semester_ID, Grade) VALUES (?, ?, ?, ?)');
    // Existing completed rosters already determine seats; insert before installing triggers.
    if ($added && $pdo->query("SHOW TRIGGERS WHERE `Trigger` LIKE 'northbridge_enrollment_%'")->fetch()) throw new RuntimeException('Seat triggers already installed; review before importing missing historical enrollment.');
    foreach ($added as $row) $statement->execute([$row['student_ID'], $row['CRN'], $row['semester_ID'], $row['Grade']]);
    $pdo->exec('DELETE FROM Attendance');
    $statement = $pdo->prepare('INSERT INTO Attendance (student_ID,CRN,course_ID,attendance_Date,Present_Absent) VALUES (?,?,?,?,?)');
    foreach ($attendance as $row) $statement->execute(array_values($row));
    foreach ($before as $table => $rows) {
        if (in_array($table, ['Enrollment','Attendance'], true)) continue;
        if (timeline_rows($pdo, $table) !== $rows) throw new RuntimeException('Protected table changed: ' . $table);
    }
    foreach (['Enrollment' => 'enrollment.sql', 'Attendance' => 'attendance.sql'] as $table => $file) {
        $path = dirname(__DIR__) . '/data/' . $file;
        $originals[$path] = file_get_contents($path);
        file_put_contents($backup . '/' . $file, $originals[$path]); chmod($backup . '/' . $file, 0600);
        $rows = timeline_rows($pdo, $table);
        $export = timeline_sql($pdo, $table, timeline_export_order($pdo, $table, $rows, $originals[$path]));
        $temp = 'verify_' . $table;
        $pdo->exec("CREATE TEMPORARY TABLE `$temp` LIKE `$table`");
        $pdo->exec(str_replace("INSERT INTO `$table`", "INSERT INTO `$temp`", $export));
        if (timeline_rows($pdo, $temp) !== $rows) throw new RuntimeException('Seed reload mismatch.');
        $pdo->exec("DROP TEMPORARY TABLE `$temp`");
        if (file_put_contents($path, '-- Academic snapshot: ' . $timeline['as_of'] . "; meeting-aligned attendance and retained historical enrollment.\n\n" . $export) === false) throw new RuntimeException('Seed write failed.');
    }
    $bad = (int)$pdo->query('SELECT COUNT(*) FROM Attendance a JOIN Course_Section cs ON cs.CRN=a.CRN WHERE NOT EXISTS (SELECT 1 FROM Time_Slot_Day d WHERE d.time_Slot_ID=cs.time_Slot_ID AND d.day_ID=WEEKDAY(a.attendance_Date)+1)')->fetchColumn();
    if ($bad) throw new RuntimeException('Attendance still falls on non-meeting days.');
    foreach ($before['Semester'] as $semester) {
        foreach (schedule_sections($pdo, (int)$semester['semester_ID']) as $row) {
            if ($row['available_Seats'] < 0 || $row['available_Seats'] > 10 || $row['available_Seats'] !== (int)$sections[$row['CRN']]['available_Seats']) throw new RuntimeException('Roster/seat inconsistency.');
        }
    }
    $report['protected_tables_and_seed_reload'] = 'PASS';
    file_put_contents($backup . '/audit.json', json_encode($report, JSON_PRETTY_PRINT));
    $pdo->commit();
    // Trigger DDL commits independently; only install after the data transaction passes.
    foreach (require __DIR__ . '/section_seat_triggers.php' as $sql) $pdo->exec($sql);
    echo json_encode($report, JSON_PRETTY_PRINT), "\nBackup: $backup\nSeat synchronization triggers installed.\n";
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        foreach ($originals as $path => $contents) file_put_contents($path, $contents);
    }
    throw $error;
}
