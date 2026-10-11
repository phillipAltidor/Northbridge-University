-- Summarizes proposed major and minor requirements.

USE northbridge_university;

SELECT m.major_ID AS program_ID,
       m.major_Name AS program_Name,
       COUNT(*) AS required_courses,
       SUM(c.course_Credits) AS requirement_credits,
       MIN(r.min_Grade_Req) AS minimum_grade,
       MIN(r.requirement_Status) AS plan_status
FROM Major AS m
JOIN Major_Requirement AS r ON r.major_ID = m.major_ID
JOIN Course AS c ON c.course_ID = r.course_ID
GROUP BY m.major_ID, m.major_Name
ORDER BY m.major_ID;

SELECT m.major_ID AS program_ID,
       m.major_Name AS program_Name,
       c.course_ID,
       c.course_Name,
       c.course_Credits,
       r.min_Grade_Req,
       r.requirement_Status,
       COALESCE(p.prerequisites, '(none listed)') AS prerequisites
FROM Major AS m
JOIN Major_Requirement AS r ON r.major_ID = m.major_ID
JOIN Course AS c ON c.course_ID = r.course_ID
LEFT JOIN (
    SELECT cp.course_ID,
           GROUP_CONCAT(CONCAT(pr.course_ID, ' ', pr.course_Name)
                        ORDER BY pr.course_ID SEPARATOR ', ') AS prerequisites
    FROM Course_Prerequisite AS cp
    JOIN Course AS pr ON pr.course_ID = cp.prerequisite_course_ID
    GROUP BY cp.course_ID
) AS p ON p.course_ID = c.course_ID
ORDER BY m.major_ID, c.course_ID;

SELECT m.minor_ID AS program_ID,
       m.minor_Name AS program_Name,
       COUNT(*) AS required_courses,
       SUM(c.course_Credits) AS requirement_credits,
       MIN(r.min_Grade_Req) AS minimum_grade,
       MIN(r.requirement_Status) AS plan_status
FROM Minor AS m
JOIN Minor_Requirement AS r ON r.minor_ID = m.minor_ID
JOIN Course AS c ON c.course_ID = r.course_ID
GROUP BY m.minor_ID, m.minor_Name
ORDER BY m.minor_ID;

SELECT m.minor_ID AS program_ID,
       m.minor_Name AS program_Name,
       c.course_ID,
       c.course_Name,
       c.course_Credits,
       r.min_Grade_Req,
       r.requirement_Status,
       COALESCE(p.prerequisites, '(none listed)') AS prerequisites
FROM Minor AS m
JOIN Minor_Requirement AS r ON r.minor_ID = m.minor_ID
JOIN Course AS c ON c.course_ID = r.course_ID
LEFT JOIN (
    SELECT cp.course_ID,
           GROUP_CONCAT(CONCAT(pr.course_ID, ' ', pr.course_Name)
                        ORDER BY pr.course_ID SEPARATOR ', ') AS prerequisites
    FROM Course_Prerequisite AS cp
    JOIN Course AS pr ON pr.course_ID = cp.prerequisite_course_ID
    GROUP BY cp.course_ID
) AS p ON p.course_ID = c.course_ID
ORDER BY m.minor_ID, c.course_ID;
