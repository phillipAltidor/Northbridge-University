<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/faculty-navigation.php';
require dirname(__DIR__) . '/includes/faculty-schedule.php';
function faculty_check(bool $pass, string $label): void
{
    if (!$pass) throw new RuntimeException($label);
    echo "PASS: $label\n";
}
$groups = faculty_nav_groups();
faculty_check(count($groups) === 4 && array_sum(array_map(static fn($g) => count($g[1]), $groups)) === 19, 'Faculty requirements mapped to four groups and 19 actions');
$_SERVER['SCRIPT_NAME'] = '/home.php';
foreach (['home.php', 'faculty/information.php', 'faculty/schedule.php', 'faculty/schedule.php?term=next'] as $active) {
    ob_start(); faculty_navigation($active); $html = ob_get_clean();
    $dom = new DOMDocument(); @$dom->loadHTML($html); $xpath = new DOMXPath($dom);
    faculty_check($xpath->query('//a')->length === 3 && $xpath->query('//span[@aria-disabled="true"]')->length === 17, 'Only Faculty Home, profile, and schedule are linked');
    faculty_check($xpath->query('//a[@aria-current="page"]')->length === 1 && $xpath->query('//details[@open]')->length === 0, 'Faculty active link and collapsed groups are correct');
    faculty_check(!str_contains($html, '/admin/') && !str_contains($html, '/student/'), 'No cross-role navigation');
}
$_SERVER['SCRIPT_NAME'] = '/northbridge/faculty/schedule.php';
faculty_check(app_url('home.php') === '/northbridge/home.php', 'Faculty subfolder resolves application root');
ob_start(); faculty_navigation('faculty/schedule.php?term=next'); $html = ob_get_clean();
faculty_check(str_contains($html, 'href="/northbridge/faculty/schedule.php"'), 'Faculty subfolder links preserve install path');
faculty_check(meeting_schedule_group_days([]) === [] && meeting_schedule_week([], new DateTimeImmutable('2026-10-25'))[0]['meetings'] === [], 'No invented classes for unassigned faculty');

if (in_array('--database', $argv, true)) {
    require dirname(__DIR__) . '/config/database.php';
    $timeline = require dirname(__DIR__) . '/config/academic-timeline.php';
    $ids = $pdo->query('SELECT DISTINCT faculty_ID FROM Course_Section ORDER BY faculty_ID LIMIT 2')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        foreach ([$timeline['current_semester'], $timeline['next_semester']] as $semester) {
            $rows = faculty_schedule_meetings($pdo, (int) $id, $semester);
            $q = $pdo->prepare('SELECT cs.CRN FROM Course_Section cs JOIN Semester s ON s.semester_ID=cs.semester_ID WHERE cs.faculty_ID=? AND s.semester_Name=? ORDER BY cs.CRN');
            $q->execute([$id, $semester]); $expected = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
            $actual = array_values(array_unique(array_map(static fn($row) => (int)$row['CRN'], $rows))); sort($actual);
            faculty_check($actual === $expected, 'Faculty schedule exactly matches own semester assignments');
            foreach ($rows as $row) {
                $q = $pdo->prepare('SELECT COUNT(*) FROM Enrollment e JOIN Semester s ON s.semester_ID=e.semester_ID WHERE e.CRN=? AND s.semester_Name=?');
                $q->execute([$row['CRN'], $semester]);
                faculty_check($row['available_Seats'] === 10 - (int)$q->fetchColumn(), 'Available seats match actual enrollment');
            }
        }
    }
    faculty_check(faculty_schedule_meetings($pdo, -1, $timeline['current_semester']) === [], 'Unknown faculty has no teaching records');
}
