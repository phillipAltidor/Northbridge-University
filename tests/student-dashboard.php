<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/student-navigation.php';
require dirname(__DIR__) . '/includes/student-schedule.php';

function check_student(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: $message\n";
}

$_SERVER['SCRIPT_NAME'] = '/home.php';
$groups = student_nav_groups();
check_student(count($groups) === 4 && array_sum(array_map(static fn($g) => count($g[1]), $groups)) === 19, '19 Student navigation actions cover both semester views through one schedule entry');
foreach (['home.php', 'student/information.php', 'student/schedule.php', 'student/schedule.php?term=current', 'student/schedule.php?term=next'] as $active) {
    ob_start(); student_navigation($active); $html = ob_get_clean();
    $dom = new DOMDocument(); @$dom->loadHTML($html); $xpath = new DOMXPath($dom);
    check_student($xpath->query('//a')->length === 3 && $xpath->query('//span[@aria-disabled="true"]')->length === 17, 'Only implemented Student actions are linked');
    check_student($xpath->query('//a[@aria-current="page"]')->length === 1 && $xpath->query('//details[@open]')->length === 0, 'Active action identified; groups start collapsed');
    check_student(!str_contains($html, '/admin/') && !str_contains($html, 'Unsuccessfully'), 'No Admin links or separate failure actions');
}
$_SERVER['SCRIPT_NAME'] = '/northbridge/student/schedule.php';
ob_start(); student_navigation('student/schedule.php?term=next'); $html = ob_get_clean();
check_student(str_contains($html, 'href="/northbridge/student/schedule.php"'), 'Schedule navigation supports subfolder installation');

$rows = [
    ['day_Of_Week' => 'Monday', 'start_Time' => '15:00:00', 'course_ID' => 'CS101', 'CRN' => 2],
    ['day_Of_Week' => 'Monday', 'start_Time' => '08:00:00', 'course_ID' => 'ART101', 'CRN' => 1],
    ['day_Of_Week' => 'Friday', 'start_Time' => '11:30:00', 'course_ID' => 'MATH101', 'CRN' => 3],
];
$today = new DateTimeImmutable('2026-10-23 20:00:00', new DateTimeZone('America/New_York'));
$week = meeting_schedule_week($rows, $today);
check_student(count($week) === 7 && $week[0]['date']->format('Y-m-d') === '2026-10-19' && $week[6]['date']->format('Y-m-d') === '2026-10-25', 'Week uses Monday-Sunday dates in New York');
check_student($week[4]['today'] && count(array_filter($week, static fn($d) => $d['today'])) === 1, 'Only today is highlighted');
check_student(count($week[0]['meetings']) === 2 && $week[0]['meetings'][0]['CRN'] === 1 && !$week[5]['meetings'], 'Classes sorted by time; weekends can be empty');
check_student(meeting_schedule_week([], $today)[0]['meetings'] === [], 'No synthetic classes for empty enrollments');
check_student(meeting_schedule_week([], $today->modify('+2 days'))[0]['date']->format('Y-m-d') === '2026-10-19', 'Sunday remains in its current week');
check_student(meeting_schedule_time('00:00:00') === '12:00 AM' && meeting_schedule_time('12:30:00') === '12:30 PM', 'Meeting times display correctly');

$meeting = ['CRN' => 1, 'start_Time' => '09:00:00', 'end_Time' => '10:00:00',
    'Bldg_Name' => 'Science', 'room_Number' => 101, 'faculty_Name' => 'Faculty', 'day_Of_Week' => 'Wednesday'];
$grouped = meeting_schedule_group_days([$meeting, array_replace($meeting, ['day_Of_Week' => 'Monday']), $meeting]);
check_student(count($grouped) === 1 && $grouped[0]['meeting_Days'] === 'Mon/Wed', 'Matching section meetings combine ordered, deduplicated days');
foreach (['CRN' => 2, 'start_Time' => '11:00:00', 'end_Time' => '11:30:00', 'Bldg_Name' => 'Arts', 'room_Number' => 102, 'faculty_Name' => 'Another Faculty'] as $field => $value) {
    check_student(count(meeting_schedule_group_days([$meeting, array_replace($meeting, [$field => $value])])) === 2, 'Different ' . $field . ' stays separate');
}
check_student(meeting_schedule_group_days([]) === [], 'Empty schedules stay empty after grouping');
check_student($meeting['day_Of_Week'] === 'Wednesday' && count(meeting_schedule_week([$meeting], $today)[2]['meetings']) === 1, 'Day-by-day agenda is unaffected by grouping');

if (in_array('--database', $argv, true)) {
    require dirname(__DIR__) . '/config/database.php';
    $timeline = require dirname(__DIR__) . '/config/academic-timeline.php';
    $ids = $pdo->query('SELECT student_ID FROM Student ORDER BY student_ID LIMIT 2')->fetchAll(PDO::FETCH_COLUMN);
    check_student(count($ids) === 2, 'Two Student accounts available for read-only isolation checks');
    foreach ($ids as $id) {
        foreach ([$timeline['current_semester'], $timeline['next_semester']] as $semester) {
            $meetings = student_schedule_meetings($pdo, (int) $id, $semester);
            $q = $pdo->prepare('SELECT e.CRN FROM Enrollment e JOIN Semester s ON s.semester_ID=e.semester_ID WHERE e.student_ID=? AND s.semester_Name=?');
            $q->execute([$id, $semester]);
            $enrolled = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
            $actual = array_values(array_unique(array_map(static fn($row) => (int) $row['CRN'], $meetings)));
            sort($enrolled); sort($actual);
            check_student($actual === $enrolled, 'Schedule exactly matches own semester enrollment');
            foreach ($meetings as $meeting) {
                check_student(in_array($meeting['day_Of_Week'], ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'], true), 'Meeting weekday supported');
            }
        }
    }
    check_student(student_schedule_meetings($pdo, -1, $timeline['current_semester']) === [], 'Unknown student cannot retrieve anyone else’s schedule');
}
