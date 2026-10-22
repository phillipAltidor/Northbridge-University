<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/config/database.php';
require dirname(__DIR__) . '/includes/schedule.php';
if ($host !== '127.0.0.1') throw new RuntimeException('Local tests only.');
$checks = 0;
function schedule_check(bool $condition, string $label): void {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
    echo "PASS: $label\n";
}
$sourceHash = hash('sha256', json_encode($pdo->query('SELECT * FROM Course_Section ORDER BY CRN')->fetchAll(), JSON_THROW_ON_ERROR));
$paginationRows = array_map(static fn($i) => ['CRN' => $i], range(1, 372));
$pagination = schedule_page($paginationRows, 25, 1);
schedule_check(count($pagination['rows']) === 25 && $pagination['pages'] === 15 && $pagination['start'] === 1 && $pagination['end'] === 25, 'Default pagination shows 25 rows');
$pagination = schedule_page($paginationRows, 50, 2);
schedule_check(count($pagination['rows']) === 50 && $pagination['rows'][0]['CRN'] === 51 && $pagination['pages'] === 8, 'Fifty-row pagination and second-page offset');
$pagination = schedule_page($paginationRows, 25, 99999);
schedule_check($pagination['page'] === 15 && count($pagination['rows']) === 22 && $pagination['end'] === 372, 'Out-of-range page clamps to last page');
$pagination = schedule_page([], 5000, -5);
schedule_check($pagination['per_page'] === 25 && $pagination['page'] === 1 && $pagination['start'] === 0 && $pagination['end'] === 0, 'Empty/invalid pagination stays safe');
$name = 'northbridge_schedule_test_' . bin2hex(random_bytes(5));
$pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4");
try {
    $test = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    foreach (['User','Faculty','Department','Course','Semester','Time_Slot','Time_Slot_Period','Period','Room','Building','Course_Section','Enrollment','Student_History'] as $table) {
        $test->exec("CREATE TABLE `$table` LIKE `$database`.`$table`");
        if (!in_array($table, ['Course_Section','Enrollment','Student_History'], true)) $test->exec("INSERT INTO `$table` SELECT * FROM `$database`.`$table`");
    }
    $fixture = $pdo->query("SELECT cs.* FROM Course_Section cs JOIN Semester s ON s.semester_ID=cs.semester_ID WHERE s.semester_Name='Fall 2026' ORDER BY CRN LIMIT 1")->fetch();
    $term = (int)$fixture['semester_ID'];
    $insert = $test->prepare('INSERT INTO Course_Section VALUES (?,?,?,?,?,?,?,?)');
    foreach ([900001,900002] as $crn) $insert->execute([$crn,$fixture['course_ID'],1,$fixture['faculty_ID'],$fixture['time_Slot_ID'],$fixture['room_ID'],$term,10]);
    foreach (require dirname(__DIR__) . '/database/migrations/section_seat_triggers.php' as $sql) $test->exec($sql);
    $add = $test->prepare('INSERT INTO Enrollment (student_ID,CRN,semester_ID,Grade) VALUES (?,?,?,NULL)');
    $seats = static function(int $crn) use ($test): int { return (int)$test->query('SELECT available_Seats FROM Course_Section WHERE CRN=' . $crn)->fetchColumn(); };
    schedule_check($seats(900001) === 10, 'Empty section starts with 10 seats');
    for ($i=1;$i<=7;$i++) $add->execute([900000+$i,900001,$term]);
    schedule_check($seats(900001) === 3, 'Seven registrations leave three stored seats');
    $rows = schedule_sections($test,$term,true);
    schedule_check((int)$rows[0]['available_Seats'] === 3 && (int)$rows[0]['enrolled_Students'] === 7, 'Schedule calculates seats directly from roster');
    schedule_check(!empty($rows[0]['start_Time']) && !empty($rows[0]['end_Time']) && isset($rows[0]['course_Credits'],$rows[0]['Bldg_Name']), 'Credits, times, and building populated');
    for ($i=8;$i<=10;$i++) $add->execute([900000+$i,900001,$term]);
    schedule_check($seats(900001) === 0, 'Full section displays zero seats');
    $blocked=false; try { $add->execute([900011,900001,$term]); } catch(PDOException $e) { $blocked=true; }
    schedule_check($blocked && $seats(900001) === 0, 'Eleventh registration rejected without changing seats');
    $test->exec('UPDATE Enrollment SET CRN=900002 WHERE student_ID=900001 AND CRN=900001');
    schedule_check($seats(900001) === 1 && $seats(900002) === 9, 'Section transfer updates both seat counts');
    $test->exec('DELETE FROM Enrollment WHERE student_ID=900001 AND CRN=900002');
    schedule_check($seats(900002) === 10, 'Dropping registration restores a seat');
    $blocked=false; try { $add->execute([900002,900001,$term]); } catch(PDOException $e) { $blocked=true; }
    schedule_check($blocked && $seats(900001) === 1, 'Duplicate registration does not consume another seat');
    $blocked=false; try { $add->execute([900012,900002,2147483647]); } catch(PDOException $e) { $blocked=true; }
    schedule_check($blocked && $seats(900002) === 10, 'Wrong semester rejected');
    $test->beginTransaction(); $add->execute([900013,900001,$term]); $test->rollBack();
    schedule_check($seats(900001) === 1, 'Rolled-back registration restores seats atomically');
    $past=(int)$test->query("SELECT semester_ID FROM Semester WHERE semester_Name='Fall 2022' LIMIT 1")->fetchColumn();
    $insert->execute([900003,$fixture['course_ID'],1,$fixture['faculty_ID'],$fixture['time_Slot_ID'],$fixture['room_ID'],$past,10]);
    $add->execute([900020,900003,$past]);
    $test->prepare('INSERT INTO Student_History VALUES (?,?,?,?,?)')->execute([900020,900003,$fixture['course_ID'],$past,'A']);
    $history=schedule_sections($test,$past);
    schedule_check((int)$history[0]['available_Seats'] === 9 && (int)$history[0]['enrolled_Students'] === 1, 'Historical enrollment counted once, not doubled by history');
    $rows=schedule_sections($test,$term);
    schedule_check(count(schedule_filter_sections($rows,schedule_course_label($fixture['course_ID']),0,false)) === 2, 'Hyphenated course code search');
    schedule_check(count(schedule_filter_sections($rows,'900002',0,false)) === 1, 'CRN search');
    schedule_check(count(schedule_filter_sections($rows,'no-such-course',0,false)) === 0, 'No-match search');
    schedule_check(count(schedule_filter_sections($rows,'',2147483647,false)) === 0, 'Department filter');
    $add->execute([900030,900001,$term]);
    schedule_check(count(schedule_filter_sections(schedule_sections($test,$term),'',0,true)) === 1, 'Available-only excludes full sections');
    $add->execute([900040,900002,$term]);
    $blocked=false; try { $test->exec('UPDATE Enrollment SET CRN=900001 WHERE student_ID=900040 AND CRN=900002'); } catch(PDOException $e) { $blocked=true; }
    schedule_check($blocked && $seats(900001) === 0 && $seats(900002) === 9, 'Transfer into full section leaves both rosters/seats unchanged');
    $test->exec("UPDATE Enrollment SET Grade='B' WHERE student_ID=900020 AND CRN=900003");
    schedule_check($seats(900003) === 9, 'Grade-only update does not change seats');
} finally {
    if (isset($test) && $test->inTransaction()) $test->rollBack();
    unset($test);
    $pdo->exec("DROP DATABASE `$name`");
}
schedule_check($sourceHash === hash('sha256', json_encode($pdo->query('SELECT * FROM Course_Section ORDER BY CRN')->fetchAll(), JSON_THROW_ON_ERROR)), 'Original course sections unchanged');
echo "PASS: $checks isolated schedule/seat checks; no original accounts or registrations changed\n";
