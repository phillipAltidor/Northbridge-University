<?php

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
        "SELECT cs.CRN, cs.course_ID, c.course_Name,
                cs.section_No, cs.faculty_ID, cs.time_Slot_ID, cs.semester_ID, u.first_Name, u.middle_Name, u.last_Name,
                ts.time_Slot_Day, ts.time_Slot_Period, cs.room_ID, r.room_Number, r.room_Type, b.Bldg_Name, cs.available_Seats
         FROM Course_Section cs
         JOIN Course c ON c.course_ID = cs.course_ID
         JOIN Faculty f ON f.faculty_ID = cs.faculty_ID
         JOIN User u ON u.user_ID = f.faculty_ID
         JOIN Time_Slot ts ON ts.time_Slot_ID = cs.time_Slot_ID
         LEFT JOIN Room r ON r.room_ID = cs.room_ID
         LEFT JOIN Building b ON b.Bldg_ID = r.Bldg_ID
         WHERE cs.semester_ID = ?
         ORDER BY cs.course_ID, cs.section_No, cs.CRN" . ($lock ? ' FOR UPDATE' : '')
    );
    $stmt->execute([$semesterID]);
    return $stmt->fetchAll();
}
