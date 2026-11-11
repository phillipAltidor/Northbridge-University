<?php

require_once __DIR__ . '/meeting-schedule.php';
require_once __DIR__ . '/schedule.php';
require_once __DIR__ . '/academic-portal.php';

function faculty_schedule_meetings(PDO $pdo, int $facultyID, string $semester): array
{
    $relation=academic_portal_meeting_relation($pdo);
    $statement = $pdo->prepare(
        "SELECT cs.CRN, cs.semester_ID, cs.course_ID, cs.section_No, c.course_Name, c.course_Credits,
                mt.day_Of_Week, mt.start_Time, mt.end_Time, mt.start_Date, mt.end_Date, b.Bldg_Name, r.room_Number,
                CONCAT_WS(' ', NULLIF(u.first_Name, ''), NULLIF(u.middle_Name, ''), NULLIF(u.last_Name, '')) AS faculty_Name,
                ((SELECT COUNT(*) FROM Enrollment e WHERE e.CRN=cs.CRN AND e.semester_ID=cs.semester_ID)
                 +(SELECT COUNT(*) FROM Student_History h WHERE h.CRN=cs.CRN AND h.semester_ID=cs.semester_ID AND NOT EXISTS(SELECT 1 FROM Enrollment e WHERE e.CRN=h.CRN AND e.student_ID=h.student_ID AND e.semester_ID=h.semester_ID))) AS enrolled_Students
         FROM Course_Section cs
         JOIN Semester s ON s.semester_ID=cs.semester_ID
         JOIN Course c ON c.course_ID=cs.course_ID
         JOIN User u ON u.user_ID=cs.faculty_ID
         JOIN $relation mt ON mt.CRN=cs.CRN
         LEFT JOIN Room r ON r.room_ID=cs.room_ID
         LEFT JOIN Building b ON b.Bldg_ID=r.Bldg_ID
         WHERE cs.faculty_ID=? AND s.semester_Name=?
         ORDER BY FIELD(mt.day_Of_Week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
                  mt.start_Time, cs.course_ID, cs.CRN"
    );
    $statement->execute([$facultyID, $semester]);
    $rows = academic_portal_source_labels($pdo,$statement->fetchAll());
    foreach ($rows as &$row) $row['available_Seats'] = (int)($row['section_capacity']??SCHEDULE_MAXIMUM_SEATS) - (int) $row['enrolled_Students'];
    unset($row);
    return $rows;
}
