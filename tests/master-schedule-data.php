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
        if ($row['maximum_Seats'] < 1 || $row['maximum_Seats'] > 10 || $row['available_Seats'] < 0 || $row['available_Seats'] > $row['maximum_Seats']) throw new RuntimeException('Invalid section capacity');
        if ($row['available_Seats'] !== $row['maximum_Seats']-(int)$row['enrolled_Students']) throw new RuntimeException('Seat formula mismatch');
        if (!$row['start_Time'] || !$row['end_Time'] || !$row['Bldg_Name'] || !$row['room_Number']) throw new RuntimeException('Incomplete meeting details');
    }
    data_check(count($rows)>0, $term['semester_Name'] . ': capacity, roster seats, faculty/room/time data');
}
foreach ($terms as $term) foreach (schedule_sections($pdo,(int)$term['semester_ID']) as $row) {
    $q=$pdo->prepare('SELECT available_Seats FROM Course_Section WHERE CRN=?');$q->execute([$row['CRN']]);
    if ((int)$q->fetchColumn()!==$row['available_Seats']) throw new RuntimeException('Stored seat mismatch.');
}
data_check(true, 'Stored seats match actual capacity and roster for every semester');
data_check((int)$pdo->query('SELECT COUNT(*) FROM Student_History h LEFT JOIN Enrollment e ON e.student_ID=h.student_ID AND e.CRN=h.CRN WHERE e.student_ID IS NULL OR e.semester_ID<>h.semester_ID OR NOT(e.Grade<=>h.Grade)')->fetchColumn() === 0, 'Historical enrollment and final grades match completed history');
$relation=academic_portal_meeting_relation($pdo);
data_check((int)$pdo->query("SELECT COUNT(*) FROM Attendance a JOIN Course_Section cs ON cs.CRN=a.CRN WHERE a.course_ID<>cs.course_ID OR NOT EXISTS (SELECT 1 FROM $relation m WHERE m.CRN=a.CRN AND m.day_Of_Week=ELT(WEEKDAY(a.attendance_Date)+1,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') AND (m.start_Date IS NULL OR a.attendance_Date>=m.start_Date) AND (m.end_Date IS NULL OR a.attendance_Date<=m.end_Date))")->fetchColumn() === 0, 'Attendance matches course and exact meeting date');
data_check((int)$pdo->query('SELECT COUNT(*) FROM Attendance a LEFT JOIN Enrollment e ON e.student_ID=a.student_ID AND e.CRN=a.CRN WHERE e.student_ID IS NULL')->fetchColumn() === 0, 'Attendance belongs to registered students');
$timeline=require dirname(__DIR__) . '/config/academic-timeline.php';
$statement=$pdo->prepare('SELECT COUNT(*) FROM Attendance WHERE attendance_Date>?'); $statement->execute([$timeline['as_of']]);
data_check((int)$statement->fetchColumn()===0,'No attendance after today in New York');
data_check(count($pdo->query("SHOW TRIGGERS WHERE `Trigger` LIKE 'northbridge_enrollment_%'")->fetchAll())===3,'Registration seat triggers installed');
echo "PASS: $checks read-only Master Schedule data checks\n";
