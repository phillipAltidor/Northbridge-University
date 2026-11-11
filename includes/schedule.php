<?php

require_once __DIR__ . '/academic-timeline.php';
require_once __DIR__ . '/academic-portal.php';

const SCHEDULE_MAXIMUM_SEATS = 10;

function schedule_enrolled_count(PDO $pdo, int $semesterID, int $crn): int
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM (SELECT student_ID FROM Enrollment WHERE semester_ID = ? AND CRN = ? UNION SELECT student_ID FROM Student_History WHERE semester_ID = ? AND CRN = ?) roster');
    $statement->execute([$semesterID, $crn, $semesterID, $crn]);
    return (int)$statement->fetchColumn();
}

/** Rebuilt capacity is registrar metadata; legacy sections retain the ten-seat limit. */
function schedule_section_capacity(PDO $pdo, int $crn): int
{
    $rows=academic_portal_source_labels($pdo,[['CRN'=>$crn]]);
    return (int)($rows[0]['section_capacity']??SCHEDULE_MAXIMUM_SEATS);
}

/** Schema subtype counters describe the configured current semester, not lifetime history. */
function schedule_sync_faculty_counts(PDO $pdo): int
{
    $timeline=require __DIR__.'/../config/academic-timeline.php';
    $changed=0;
    foreach (['Full_Time_Faculty','Part_Time_Faculty'] as $table) {
        $q=$pdo->prepare("UPDATE `$table` f SET no_of_Classes=(SELECT COUNT(*) FROM Course_Section c JOIN Semester s ON s.semester_ID=c.semester_ID WHERE c.faculty_ID=f.faculty_ID AND s.semester_Name=?) WHERE EXISTS(SELECT 1 FROM Semester WHERE semester_Name=?) AND no_of_Classes<>(SELECT COUNT(*) FROM Course_Section c JOIN Semester s ON s.semester_ID=c.semester_ID WHERE c.faculty_ID=f.faculty_ID AND s.semester_Name=?)");
        $q->execute(array_fill(0,3,$timeline['current_semester']));$changed+=$q->rowCount();
    }
    return $changed;
}

function schedule_filter_sections(array $sections, string $query, int $department, bool $availableOnly, string $level = 'all'): array
{
    $query = mb_strtolower(trim($query), 'UTF-8');
    $codeQuery = preg_replace('/[\s-]+/u', '', $query);
    return array_values(array_filter($sections, static function ($row) use ($query, $codeQuery, $department, $availableOnly, $level) {
        if ($level !== 'all' && ($row['course_Type'] ?? null) !== $level) return false;
        if ($department && (int)$row['dept_ID'] !== $department) return false;
        if ($availableOnly && (int)$row['available_Seats'] <= 0) return false;
        $text = mb_strtolower(implode(' ', [$row['display_CRN']??$row['CRN'], $row['display_course_ID']??$row['course_ID'], $row['course_Name']]), 'UTF-8');
        return $query === '' || str_contains($text, $query) || ($codeQuery !== '' && str_contains(mb_strtolower($row['course_ID'], 'UTF-8'), $codeQuery));
    }));
}

function schedule_page(array $sections, int $perPage, int $page): array
{
    $perPage = in_array($perPage, [25, 50], true) ? $perPage : 25;
    $total = count($sections);
    $pages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($page, $pages));
    $offset = ($page - 1) * $perPage;
    return [
        'rows' => array_slice($sections, $offset, $perPage), 'per_page' => $perPage,
        'page' => $page, 'pages' => $pages, 'total' => $total,
        'start' => $total ? $offset + 1 : 0, 'end' => min($offset + $perPage, $total),
    ];
}

function schedule_day_label(string $days): string
{
    return strtr($days, [
        'Monday' => 'M', 'Tuesday' => 'T', 'Wednesday' => 'W',
        'Thursday' => 'Th', 'Friday' => 'F', 'Saturday' => 'Sa', 'Sunday' => 'Su',
        ', ' => '/'
    ]);
}

function schedule_course_label(string $courseID): string
{
    return preg_replace('/^([A-Z]+)([0-9]+)$/D', '$1-$2', $courseID);
}

function schedule_time_label(string $period): string
{
    $label = preg_replace_callback(
        '/\b([01]?\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?\b/',
        static function (array $match): string {
            $hour = (int) $match[1];
            return ($hour % 12 ?: 12) . ':' . $match[2] . ($hour < 12 ? ' AM' : ' PM');
        },
        $period
    );
    return preg_replace('/(AM|PM)\s*-\s*(?=\d)/', '$1 - ', $label);
}

function schedule_sections(PDO $pdo, int $semesterID, bool $lock = false): array
{
    $stmt = $pdo->prepare(
        "SELECT cs.CRN, cs.course_ID, c.course_Name, c.course_Credits, c.course_Type, c.dept_ID, dept.dept_Name,
                cs.section_No, cs.faculty_ID, cs.time_Slot_ID, cs.semester_ID, u.first_Name, u.middle_Name, u.last_Name,
                ts.time_Slot_Day, ts.time_Slot_Period, cs.room_ID, r.room_Number, r.room_Type, b.Bldg_Name,
                ((SELECT COUNT(*) FROM Enrollment roster WHERE roster.CRN=cs.CRN AND roster.semester_ID=cs.semester_ID)
                 +(SELECT COUNT(*) FROM Student_History h WHERE h.CRN=cs.CRN AND h.semester_ID=cs.semester_ID AND NOT EXISTS(SELECT 1 FROM Enrollment e WHERE e.CRN=h.CRN AND e.student_ID=h.student_ID AND e.semester_ID=h.semester_ID))) AS enrolled_Students,
                (SELECT GROUP_CONCAT(TIME_FORMAT(p.start_Time, '%l:%i %p') ORDER BY p.start_Time SEPARATOR ', ') FROM Time_Slot_Period tp JOIN Period p ON p.period_ID=tp.period_ID WHERE tp.time_Slot_ID=cs.time_Slot_ID) AS start_Time,
                (SELECT GROUP_CONCAT(TIME_FORMAT(p.end_Time, '%l:%i %p') ORDER BY p.start_Time SEPARATOR ', ') FROM Time_Slot_Period tp JOIN Period p ON p.period_ID=tp.period_ID WHERE tp.time_Slot_ID=cs.time_Slot_ID) AS end_Time
         FROM Course_Section cs
         JOIN Course c ON c.course_ID = cs.course_ID
         JOIN Department dept ON dept.dept_ID = c.dept_ID
         JOIN Faculty f ON f.faculty_ID = cs.faculty_ID
         JOIN User u ON u.user_ID = f.faculty_ID
         JOIN Time_Slot ts ON ts.time_Slot_ID = cs.time_Slot_ID
         LEFT JOIN Room r ON r.room_ID = cs.room_ID
         LEFT JOIN Building b ON b.Bldg_ID = r.Bldg_ID
         WHERE cs.semester_ID = ?
         ORDER BY cs.course_ID, cs.section_No, cs.CRN" . ($lock ? ' FOR UPDATE' : '')
    );
    $stmt->execute([$semesterID]);
    $rows = academic_portal_source_labels($pdo,$stmt->fetchAll());
    foreach ($rows as &$row) {
        $row['maximum_Seats'] = (int)($row['section_capacity']??SCHEDULE_MAXIMUM_SEATS);
        $row['available_Seats'] = $row['maximum_Seats'] - (int)$row['enrolled_Students'];
    }
    unset($row);
    return $rows;
}
