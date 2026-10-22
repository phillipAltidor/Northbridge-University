<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/config/database.php';
require dirname(__DIR__) . '/includes/schedule.php';
$checks = 0;
function data_check(bool $condition, string $label): void {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++; echo "PASS: $label\n";
}
$required = ['Fall 2022','Spring 2023','Fall 2023','Spring 2024','Fall 2024','Spring 2025','Fall 2025','Spring 2026','Fall 2026'];
$terms = $pdo->query('SELECT * FROM Semester ORDER BY semester_ID')->fetchAll();
data_check(!array_diff($required,array_column($terms,'semester_Name')), 'All nine required semesters exist');
foreach ($terms as $term) {
    $rows=schedule_sections($pdo,(int)$term['semester_ID']);
    foreach ($rows as $row) {
        if ($row['maximum_Seats'] !== 10 || $row['available_Seats'] < 0 || $row['available_Seats'] > 10) throw new RuntimeException('Invalid section capacity');
        if ($row['available_Seats'] !== 10-(int)$row['enrolled_Students']) throw new RuntimeException('Seat formula mismatch');
        if (!$row['start_Time'] || !$row['end_Time'] || !$row['Bldg_Name'] || !$row['room_Number']) throw new RuntimeException('Incomplete meeting details');
    }
    data_check(count($rows)>0, $term['semester_Name'] . ': capacity, roster seats, faculty/room/time data');
}
data_check((int)$pdo->query('SELECT COUNT(*) FROM Course_Section cs LEFT JOIN (SELECT CRN,COUNT(*) n FROM Enrollment GROUP BY CRN) e ON e.CRN=cs.CRN WHERE cs.available_Seats<>10-COALESCE(e.n,0)')->fetchColumn() === 0, 'Stored seats match actual enrollment for every semester');
data_check((int)$pdo->query('SELECT COUNT(*) FROM Student_History h LEFT JOIN Enrollment e ON e.student_ID=h.student_ID AND e.CRN=h.CRN WHERE e.student_ID IS NULL OR e.semester_ID<>h.semester_ID OR NOT(e.Grade<=>h.Grade)')->fetchColumn() === 0, 'Historical enrollment and final grades match completed history');
data_check((int)$pdo->query('SELECT COUNT(*) FROM Attendance a JOIN Course_Section cs ON cs.CRN=a.CRN WHERE a.course_ID<>cs.course_ID OR NOT EXISTS (SELECT 1 FROM Time_Slot_Day d WHERE d.time_Slot_ID=cs.time_Slot_ID AND d.day_ID=WEEKDAY(a.attendance_Date)+1)')->fetchColumn() === 0, 'Attendance matches course and meeting weekday');
data_check((int)$pdo->query('SELECT COUNT(*) FROM Attendance a LEFT JOIN Enrollment e ON e.student_ID=a.student_ID AND e.CRN=a.CRN WHERE e.student_ID IS NULL')->fetchColumn() === 0, 'Attendance belongs to registered students');
$timeline=require dirname(__DIR__) . '/config/academic-timeline.php';
$statement=$pdo->prepare('SELECT COUNT(*) FROM Attendance WHERE attendance_Date>?'); $statement->execute([$timeline['as_of']]);
data_check((int)$statement->fetchColumn()===0,'No attendance after approved snapshot date');
data_check(count($pdo->query("SHOW TRIGGERS WHERE `Trigger` LIKE 'northbridge_enrollment_%'")->fetchAll())===3,'Registration seat triggers installed');
echo "PASS: $checks read-only Master Schedule data checks\n";
