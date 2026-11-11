<?php

require_once __DIR__ . '/meeting-schedule.php';
require_once __DIR__ . '/academic-portal.php';

function student_schedule_meetings(PDO $pdo, int $studentID, string $semester): array
{
    $relation=academic_portal_meeting_relation($pdo);
    $statement = $pdo->prepare(
        "SELECT cs.CRN, cs.course_ID, cs.section_No, c.course_Name, c.course_Credits,
                mt.day_Of_Week, mt.start_Time, mt.end_Time, mt.start_Date, mt.end_Date, b.Bldg_Name, r.room_Number,
                CONCAT_WS(' ', NULLIF(u.first_Name, ''), NULLIF(u.middle_Name, ''), NULLIF(u.last_Name, '')) AS faculty_Name
         FROM Enrollment e
         JOIN Semester s ON s.semester_ID = e.semester_ID
         JOIN Course_Section cs ON cs.CRN = e.CRN AND cs.semester_ID = e.semester_ID
         JOIN Course c ON c.course_ID = cs.course_ID
         JOIN User u ON u.user_ID = cs.faculty_ID
         JOIN $relation mt ON mt.CRN=cs.CRN
         LEFT JOIN Room r ON r.room_ID = cs.room_ID
         LEFT JOIN Building b ON b.Bldg_ID = r.Bldg_ID
         WHERE e.student_ID = ? AND s.semester_Name = ?
         ORDER BY FIELD(mt.day_Of_Week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
                  mt.start_Time, cs.course_ID, cs.CRN"
    );
    $statement->execute([$studentID, $semester]);
    return academic_portal_source_labels($pdo,$statement->fetchAll());
}
