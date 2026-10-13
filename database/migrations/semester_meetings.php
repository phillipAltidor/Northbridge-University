<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__, 2) . '/config/database.php';
if ($host !== '127.0.0.1' || $database !== 'northbridge_university') {
    throw new RuntimeException('Expected the local northbridge_university database.');
}
$apply = in_array('--apply', $argv, true);
$options = getopt('', ['semester:', 'apply']);
$semesterName = $options['semester'] ?? 'Fall 2022';
if (!in_array($semesterName, ['Fall 2022', 'Spring 2023'], true)) {
    throw new RuntimeException('Only Fall 2022 and Spring 2023 have approved meeting updates.');
}
function ensure(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
function overlap(array $a, array $b): bool
{
    return array_intersect($a['days'], $b['days']) && $a['start'] < $b['end'] && $b['start'] < $a['end'];
}
function table_digest(PDO $pdo, string $table): string
{
    $columns = $pdo->query("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'")->fetchAll();
    usort($columns, static fn($a, $b) => $a['Seq_in_index'] <=> $b['Seq_in_index']);
    $order = implode(',', array_map(static fn($column) => '`' . $column['Column_name'] . '`', $columns));
    ensure($order !== '', 'Table without a primary key: ' . $table);
    $hash = hash_init('sha256');
    foreach ($pdo->query("SELECT * FROM `$table` ORDER BY $order") as $row) hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR) . "\n");
    return hash_final($hash);
}
$pdo->beginTransaction();
$seedPath = dirname(__DIR__) . '/data/historical_course_section.sql';
$seedBefore = file_get_contents($seedPath);
$seedWritten = false;
try {
    $stmt = $pdo->prepare('SELECT semester_ID FROM Semester WHERE semester_Name = ? FOR UPDATE');
    $stmt->execute([$semesterName]);
    $semesterIDs = $stmt->fetchAll(PDO::FETCH_COLUMN);
    ensure(count($semesterIDs) === 1, 'Expected one ' . $semesterName . ' semester.');
    $semesterID = (int)$semesterIDs[0];
    $sections = $pdo->query("SELECT cs.*, c.course_Credits, r.room_Type FROM Course_Section cs JOIN Course c ON c.course_ID=cs.course_ID JOIN Room r ON r.room_ID=cs.room_ID WHERE cs.semester_ID=$semesterID ORDER BY cs.CRN FOR UPDATE")->fetchAll();
    ensure(count($sections) === 48, 'Expected 48 ' . $semesterName . ' sections.');
    $slots = [];
    foreach ($pdo->query('SELECT ts.*, p.start_Time, p.end_Time, TIMESTAMPDIFF(MINUTE,p.start_Time,p.end_Time) minutes FROM Time_Slot ts JOIN Time_Slot_Period tp ON tp.time_Slot_ID=ts.time_Slot_ID JOIN Period p ON p.period_ID=tp.period_ID ORDER BY ts.time_Slot_ID') as $row) {
        if (!in_array($row['time_Slot_Day'], ['Monday, Wednesday','Tuesday, Thursday','Friday'], true) || !in_array((int)$row['minutes'], [90,120,180], true)) continue;
        $id = (int)$row['time_Slot_ID'];
        ensure(!isset($slots[$id]), 'Expected single-period candidate slots.');
        $stmt = $pdo->prepare('SELECT d.day_ID,d.day_Of_Week FROM Time_Slot_Day td JOIN Day d ON d.day_ID=td.day_ID WHERE td.time_Slot_ID=? ORDER BY d.day_ID');
        $stmt->execute([$id]);
        $days = $stmt->fetchAll();
        ensure(implode(', ', array_column($days, 'day_Of_Week')) === $row['time_Slot_Day'], 'Day label/mapping mismatch.');
        ensure($row['start_Time'] . '-' . $row['end_Time'] === $row['time_Slot_Period'], 'Period label/mapping mismatch.');
        $slots[$id] = ['id'=>$id, 'label'=>$row['time_Slot_Day'], 'days'=>array_column($days,'day_ID'), 'start'=>$row['start_Time'], 'end'=>$row['end_Time'], 'minutes'=>(int)$row['minutes']];
    }
    ensure(count($slots) === 36, 'Expected 36 duration-compatible candidate slots.');
    $reference = [];
    if ($semesterName === 'Spring 2023') {
        $reference = $pdo->query("SELECT cs.* FROM Course_Section cs JOIN Semester s ON s.semester_ID=cs.semester_ID WHERE s.semester_Name='Fall 2022' ORDER BY cs.CRN")->fetchAll();
        ensure(count($reference) === 48, 'Expected the audited Fall 2022 reference schedule.');
    }
    $resources = [];
    $byCRN = [];
    foreach ($sections as $section) {
        $crn = (int)$section['CRN'];
        $byCRN[$crn] = $section;
        $resources[$crn] = ['faculty:' . $section['faculty_ID'], 'room:' . $section['room_ID']];
        ensure(in_array($section['room_Type'], ['Lecture','Lab'], true), 'Invalid teaching room.');
        ensure(in_array((int)$section['course_Credits'], [3,4], true), 'Unsupported course credits.');
    }
    $registrations = $pdo->query("SELECT student_ID, CRN FROM Enrollment WHERE semester_ID=$semesterID UNION SELECT student_ID,CRN FROM Student_History WHERE semester_ID=$semesterID")->fetchAll();
    foreach ($registrations as $registration) {
        ensure(isset($resources[$registration['CRN']]), 'Registration references a different semester.');
        $resources[$registration['CRN']][] = 'student:' . $registration['student_ID'];
    }
    $neighbors = [];
    foreach ($resources as $crn => $keys) {
        $neighbors[$crn] = [];
        foreach ($resources as $other => $otherKeys) if ($crn !== $other && array_intersect($keys, $otherKeys)) $neighbors[$crn][] = $other;
    }
    $order = array_keys($byCRN);
    usort($order, static fn($a,$b) => count($neighbors[$b]) <=> count($neighbors[$a]) ?: $a <=> $b);
    $assignments = [];
    $preferredSlots = [];
    $dayUse = $slotUse = [];
    // Preserve recurring course or instructor meeting patterns before balancing new assignments.
    foreach ($order as $crn) {
        $section = $byCRN[$crn];
        $minutes = (int)$section['course_Credits'] === 3 ? 90 : ($section['room_Type'] === 'Lab' ? 180 : 120);
        $referenceCandidates = array_values(array_filter($reference, static fn($prior) => isset($slots[$prior['time_Slot_ID']]) && $slots[$prior['time_Slot_ID']]['minutes'] === $minutes && ($prior['course_ID'] === $section['course_ID'] || $prior['faculty_ID'] === $section['faculty_ID'])));
        usort($referenceCandidates, static fn($a,$b) => ($b['course_ID'] === $section['course_ID']) <=> ($a['course_ID'] === $section['course_ID']) ?: ($b['faculty_ID'] === $section['faculty_ID']) <=> ($a['faculty_ID'] === $section['faculty_ID']) ?: ($b['room_ID'] === $section['room_ID']) <=> ($a['room_ID'] === $section['room_ID']) ?: $a['CRN'] <=> $b['CRN']);
        $preferred = $referenceCandidates ? (int)$referenceCandidates[0]['time_Slot_ID'] : null;
        if ($preferred !== null) $preferredSlots[$crn] = $preferred;
        $candidates = array_values(array_filter($slots, static fn($slot) => $slot['minutes'] === $minutes));
        usort($candidates, static fn($a,$b) => ($b['id'] === $preferred) <=> ($a['id'] === $preferred) ?: ($dayUse[$a['label']] ?? 0) <=> ($dayUse[$b['label']] ?? 0) ?: ($slotUse[$a['id']] ?? 0) <=> ($slotUse[$b['id']] ?? 0) ?: strcmp($a['start'],$b['start']) ?: $a['id'] <=> $b['id']);
        foreach ($candidates as $candidate) {
            foreach ($neighbors[$crn] as $neighbor) if (isset($assignments[$neighbor]) && overlap($candidate,$slots[$assignments[$neighbor]])) continue 2;
            $assignments[$crn] = $candidate['id'];
            $dayUse[$candidate['label']] = ($dayUse[$candidate['label']] ?? 0) + 1;
            $slotUse[$candidate['id']] = ($slotUse[$candidate['id']] ?? 0) + 1;
            break;
        }
        ensure(isset($assignments[$crn]), 'No conflict-free slot found; no changes applied.');
    }
    ksort($assignments);
    ensure(count($dayUse) === 3, 'Expected all three weekday patterns.');
    foreach ($neighbors as $crn => $others) foreach ($others as $other) ensure(!overlap($slots[$assignments[$crn]],$slots[$assignments[$other]]), 'Meeting conflict in generated plan.');
    $preserved = count(array_filter($preferredSlots, static fn($slot,$crn) => $assignments[$crn] === $slot, ARRAY_FILTER_USE_BOTH));
    echo json_encode(['semester'=>$semesterName,'sections'=>count($assignments),'reference_slots_available'=>count($preferredSlots),'reference_slots_preserved'=>$preserved,'day_patterns'=>$dayUse,'assignments'=>$assignments,'plan_audit'=>'PASS'],JSON_PRETTY_PRINT) . PHP_EOL;
    if (!$apply) {
        $pdo->rollBack();
        exit;
    }
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $digests = [];
    foreach ($tables as $table) if ($table !== 'Course_Section') $digests[$table] = table_digest($pdo,$table);
    $allSections = $pdo->query('SELECT * FROM Course_Section ORDER BY CRN')->fetchAll();
    $expectedSections = $allSections;
    foreach ($expectedSections as &$section) if ((int)$section['semester_ID'] === $semesterID) $section['time_Slot_ID'] = $assignments[$section['CRN']];
    unset($section);
    $backup = rtrim(sys_get_temp_dir(), '/') . '/northbridge-before-' . str_replace(' ', '-', strtolower($semesterName)) . '-' . date('Ymd-His') . '.sql';
    $file = fopen($backup,'x');
    ensure($file !== false, 'Cannot create backup.');
    chmod($backup,0600);
    fwrite($file,"SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
    foreach ($tables as $table) {
        $ddl = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];
        fwrite($file,"DROP TABLE IF EXISTS `$table`;\n$ddl;\n");
        foreach ($pdo->query("SELECT * FROM `$table`") as $row) {
            $columns = implode(',',array_map(static fn($column) => "`$column`",array_keys($row)));
            $values = implode(',',array_map(static fn($value) => $value === null ? 'NULL' : $pdo->quote((string)$value),array_values($row)));
            fwrite($file,"INSERT INTO `$table` ($columns) VALUES ($values);\n");
        }
    }
    fwrite($file,"SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($file);
    echo "Backup: $backup\n";
    $stmt = $pdo->prepare('UPDATE Course_Section SET time_Slot_ID=? WHERE CRN=? AND semester_ID=?');
    foreach ($assignments as $crn => $slotID) $stmt->execute([$slotID,$crn,$semesterID]);
    ensure($pdo->query('SELECT * FROM Course_Section ORDER BY CRN')->fetchAll() === $expectedSections, 'Unexpected course section changes.');
    foreach ($digests as $table => $digest) ensure(table_digest($pdo,$table) === $digest, 'Unexpected change in ' . $table);
    $matches = [];
    $seedAfter = preg_replace_callback("/^\((\d+), ('[^']+'), (\d+), (\d+), (\d+), ('[^']+'|NULL), (\d+), (\d+)\)([,;])$/m", static function($match) use ($assignments,$byCRN,$semesterID,&$matches) {
        $crn = (int)$match[1];
        if (!isset($assignments[$crn])) return $match[0];
        $row = $byCRN[$crn];
        ensure((int)$match[7] === $semesterID && trim($match[2],"'") === $row['course_ID'] && (int)$match[3] === (int)$row['section_No'] && (int)$match[4] === (int)$row['faculty_ID'] && (int)$match[5] === (int)$row['time_Slot_ID'] && trim($match[6],"'") === $row['room_ID'] && (int)$match[8] === (int)$row['available_Seats'], 'Seed/database mismatch for CRN ' . $crn);
        $matches[] = $crn;
        return "($match[1], $match[2], $match[3], $match[4], {$assignments[$crn]}, $match[6], $match[7], $match[8])$match[9]";
    },$seedBefore);
    ensure(count(array_unique($matches)) === 48 && count($matches) === 48, 'Expected exactly 48 seed rows.');
    ensure(file_get_contents($seedPath) === $seedBefore, 'Seed file changed during migration.');
    ensure(file_put_contents($seedPath,$seedAfter) === strlen($seedAfter), 'Cannot synchronize seed file.');
    $seedWritten = true;
    $pdo->commit();
    echo "APPLIED: 48 sections; duration/mapping/conflict checks passed. All other database records unchanged. Seed synchronized.\n";
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($seedWritten) file_put_contents($seedPath,$seedBefore);
    throw $error;
}
