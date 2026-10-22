<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/includes/academic-timeline.php';
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
