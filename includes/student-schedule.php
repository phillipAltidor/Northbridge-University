<?php

require_once __DIR__ . '/meeting-schedule.php';

function student_schedule_meetings(PDO $pdo, int $studentID, string $semester): array
{
    $statement = $pdo->prepare(
        "SELECT cs.CRN, cs.course_ID, cs.section_No, c.course_Name, c.course_Credits,
                d.day_Of_Week, p.start_Time, p.end_Time, b.Bldg_Name, r.room_Number,
                CONCAT_WS(' ', NULLIF(u.first_Name, ''), NULLIF(u.middle_Name, ''), NULLIF(u.last_Name, '')) AS faculty_Name
         FROM Enrollment e
         JOIN Semester s ON s.semester_ID = e.semester_ID
         JOIN Course_Section cs ON cs.CRN = e.CRN AND cs.semester_ID = e.semester_ID
         JOIN Course c ON c.course_ID = cs.course_ID
         JOIN User u ON u.user_ID = cs.faculty_ID
         JOIN Time_Slot_Day td ON td.time_Slot_ID = cs.time_Slot_ID
         JOIN Day d ON d.day_ID = td.day_ID
         JOIN Time_Slot_Period tp ON tp.time_Slot_ID = cs.time_Slot_ID
         JOIN Period p ON p.period_ID = tp.period_ID
         LEFT JOIN Room r ON r.room_ID = cs.room_ID
         LEFT JOIN Building b ON b.Bldg_ID = r.Bldg_ID
         WHERE e.student_ID = ? AND s.semester_Name = ?
         ORDER BY FIELD(d.day_Of_Week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
                  p.start_Time, cs.course_ID, cs.CRN"
    );
    $statement->execute([$studentID, $semester]);
    return $statement->fetchAll();
}
