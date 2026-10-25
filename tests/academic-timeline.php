<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/includes/academic-timeline.php';
foreach ([
    '2026-10-25T03:59:59+00:00' => '2026-10-24',
    '2026-10-25T04:00:00+00:00' => '2026-10-25',
    '2026-11-02T04:59:59+00:00' => '2026-11-01',
    '2026-11-02T05:00:00+00:00' => '2026-11-02',
] as $instant => $date) {
    if (academic_today(new DateTimeImmutable($instant))->format('Y-m-d') !== $date) throw new RuntimeException('Incorrect New York day boundary');
}
$originalTimezone = date_default_timezone_get();
try {
    date_default_timezone_set('Asia/Tokyo');
    $timeline = require dirname(__DIR__) . '/config/academic-timeline.php';
    $expected = (new DateTimeImmutable('now', new DateTimeZone('America/New_York')))->format('Y-m-d');
    if ($timeline['as_of'] !== $expected) throw new RuntimeException('Timeline date is not live in New York');
} finally {
    date_default_timezone_set($originalTimezone);
}
echo "PASS: live New York date, daily rollover, daylight saving, and host-timezone independence\n";
foreach (['Fall 2022' => 'Completed', 'Spring 2026' => 'Completed', 'Fall 2026' => 'In progress', 'Spring 2027' => 'Upcoming', 'Fall 2027' => 'Upcoming'] as $name => $expected) {
    if (academic_semester_status($name) !== $expected) throw new RuntimeException('Incorrect status: ' . $name);
}
$invalid = false;
try { academic_semester_status('Unknown'); } catch (InvalidArgumentException $e) { $invalid = true; }
if (!$invalid) throw new RuntimeException('Unrecognized semester accepted');
echo "PASS: past/current/future semester policy and invalid names\n";

if (in_array('--database', $argv, true)) {
    require dirname(__DIR__) . '/config/database.php';
    $timeline = require dirname(__DIR__) . '/config/academic-timeline.php';
    foreach ($pdo->query('SELECT semester_ID,semester_Name FROM Semester') as $semester) {
        $status = academic_semester_status($semester['semester_Name']);
        if ($status === 'Completed') continue;
        $id = $semester['semester_ID'];
        foreach ([
            'SELECT COUNT(*) FROM Enrollment WHERE semester_ID=? AND Grade IS NOT NULL',
            'SELECT COUNT(*) FROM Student_History WHERE semester_ID=?',
            'SELECT COUNT(*) FROM Faculty_History WHERE semester_ID=?',
        ] as $sql) {
            $statement = $pdo->prepare($sql); $statement->execute([$id]);
            if ((int)$statement->fetchColumn() !== 0) throw new RuntimeException('Premature results/history: ' . $semester['semester_Name']);
        }
        $statement = $pdo->prepare('SELECT COUNT(*) FROM Attendance a JOIN Course_Section cs ON cs.CRN=a.CRN WHERE cs.semester_ID=?' . ($status === 'Upcoming' ? '' : ' AND a.attendance_Date>?'));
        $statement->execute($status === 'Upcoming' ? [$id] : [$id,$timeline['as_of']]);
        if ((int)$statement->fetchColumn() !== 0) throw new RuntimeException('Future attendance remains');
    }
    echo "PASS: database has no current/future final results or completed history, and no premature attendance\n";
}
