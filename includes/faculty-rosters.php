<?php
declare(strict_types=1);
require_once __DIR__.'/faculty-schedule.php';

/** Current/next terms plus historical terms taught by this faculty member. */
function faculty_roster_semesters(PDO $pdo, int $facultyID): array
{
    $timeline=require __DIR__.'/../config/academic-timeline.php';
    $q=$pdo->prepare("SELECT s.semester_ID,s.semester_Name FROM Semester s
        WHERE s.semester_Name IN (?,?) OR EXISTS(SELECT 1 FROM Course_Section cs WHERE cs.semester_ID=s.semester_ID AND cs.faculty_ID=?)
        ORDER BY CAST(SUBSTRING_INDEX(s.semester_Name,' ',-1) AS UNSIGNED) DESC,
                 CASE WHEN s.semester_Name LIKE 'Fall%' THEN 1 ELSE 0 END DESC,s.semester_ID");
    $q->execute([$timeline['current_semester'],$timeline['next_semester'],$facultyID]);
    return $q->fetchAll();
}

/** Ownership is checked before accessing any student information. */
function faculty_section_roster(PDO $pdo, int $facultyID, int $semesterID, int $crn): ?array
{
    $q=$pdo->prepare('SELECT CRN FROM Course_Section WHERE CRN=? AND semester_ID=? AND faculty_ID=?');
    $q->execute([$crn,$semesterID,$facultyID]);
    if($q->fetchColumn()===false)return null;
    $q=$pdo->prepare("SELECT s.student_ID,u.first_Name,u.middle_Name,u.last_Name,l.user_Email,
                            s.student_Type,s.student_Year,m.major_Name
        FROM (SELECT student_ID FROM Enrollment WHERE CRN=? AND semester_ID=?
              UNION SELECT student_ID FROM Student_History WHERE CRN=? AND semester_ID=?) roster
        JOIN Student s ON s.student_ID=roster.student_ID
        JOIN User u ON u.user_ID=s.student_ID
        JOIN Login l ON l.user_ID=s.student_ID
        JOIN Major m ON m.major_ID=s.major_ID
        ORDER BY u.last_Name,u.first_Name,u.middle_Name,s.student_ID");
    $q->execute([$crn,$semesterID,$crn,$semesterID]);
    return $q->fetchAll();
}
