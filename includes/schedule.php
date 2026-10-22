<?php

require_once __DIR__ . '/academic-timeline.php';

const SCHEDULE_MAXIMUM_SEATS = 10;

function schedule_enrolled_count(PDO $pdo, int $semesterID, int $crn): int
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM Enrollment WHERE semester_ID = ? AND CRN = ?');
    $statement->execute([$semesterID, $crn]);
    return (int)$statement->fetchColumn();
}

function schedule_filter_sections(array $sections, string $query, int $department, bool $availableOnly): array
{
    $query = mb_strtolower(trim($query), 'UTF-8');
    $codeQuery = preg_replace('/[\s-]+/u', '', $query);
    return array_values(array_filter($sections, static function ($row) use ($query, $codeQuery, $department, $availableOnly) {
        if ($department && (int)$row['dept_ID'] !== $department) return false;
        if ($availableOnly && (int)$row['available_Seats'] <= 0) return false;
        $text = mb_strtolower(implode(' ', [$row['CRN'], $row['course_ID'], $row['course_Name']]), 'UTF-8');
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
        "SELECT cs.CRN, cs.course_ID, c.course_Name, c.course_Credits, c.dept_ID, dept.dept_Name,
                cs.section_No, cs.faculty_ID, cs.time_Slot_ID, cs.semester_ID, u.first_Name, u.middle_Name, u.last_Name,
                ts.time_Slot_Day, ts.time_Slot_Period, cs.room_ID, r.room_Number, r.room_Type, b.Bldg_Name,
                (SELECT COUNT(*) FROM Enrollment roster WHERE roster.CRN=cs.CRN AND roster.semester_ID=cs.semester_ID) AS enrolled_Students,
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
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['maximum_Seats'] = SCHEDULE_MAXIMUM_SEATS;
        $row['available_Seats'] = SCHEDULE_MAXIMUM_SEATS - (int)$row['enrolled_Students'];
    }
    unset($row);
    return $rows;
}
