-- Checks seed relationships and scheduling rules.

USE northbridge_university;

SELECT 'user_role_counts' AS check_name, user_Type, COUNT(*) AS records
FROM User
GROUP BY user_Type
ORDER BY user_Type;

SELECT 'student_subtype_coverage' AS check_name,
       (SELECT COUNT(*) FROM Student) AS students,
       (SELECT COUNT(*) FROM Undergraduate) AS undergraduates,
       (SELECT COUNT(*) FROM Graduate_Student) AS graduates;

SELECT 'missing_student_subtype' AS check_name, COUNT(*) AS violations
FROM Student AS s
LEFT JOIN Undergraduate AS u ON u.student_ID = s.student_ID
LEFT JOIN Graduate_Student AS g ON g.student_ID = s.student_ID
WHERE (s.student_Type = 'Undergraduate' AND u.student_ID IS NULL)
   OR (s.student_Type = 'Graduate' AND g.student_ID IS NULL);

WITH active_rosters AS (
    SELECT cs.CRN, cs.available_Seats, COUNT(e.student_ID) AS roster_size
    FROM Course_Section AS cs
    LEFT JOIN Enrollment AS e ON e.CRN = cs.CRN
    WHERE cs.semester_ID IN (1, 2)
    GROUP BY cs.CRN, cs.available_Seats
)
SELECT 'active_roster_and_seats' AS check_name,
       MIN(roster_size) AS minimum_roster,
       MAX(roster_size) AS maximum_roster,
       SUM(roster_size < 5 OR roster_size > 10) AS roster_violations,
       SUM(available_Seats <> 10 - roster_size) AS seat_violations
FROM active_rosters;

WITH historical_rosters AS (
    SELECT cs.CRN, cs.available_Seats, COUNT(sh.student_ID) AS roster_size
    FROM Course_Section AS cs
    LEFT JOIN Student_History AS sh ON sh.CRN = cs.CRN
    WHERE cs.semester_ID NOT IN (1, 2)
    GROUP BY cs.CRN, cs.available_Seats
)
SELECT 'historical_roster_and_seats' AS check_name,
       MIN(roster_size) AS minimum_roster,
       MAX(roster_size) AS maximum_roster,
       SUM(roster_size < 5 OR roster_size > 10) AS roster_violations,
       SUM(available_Seats <> 10 - roster_size) AS seat_violations
FROM historical_rosters;

SELECT 'major_requirement_catalog_alignment' AS check_name, COUNT(*) AS violations
FROM Major_Requirement AS r
JOIN Major AS m ON m.major_ID = r.major_ID
JOIN Course AS c ON c.course_ID = r.course_ID
WHERE m.dept_ID <> c.dept_ID
   OR c.course_Type <> 'Undergraduate'
   OR c.course_Credits <> 4
   OR r.min_Grade_Req <> 'C';

SELECT 'minor_requirement_catalog_alignment' AS check_name, COUNT(*) AS violations
FROM Minor_Requirement AS r
JOIN Minor AS m ON m.minor_ID = r.minor_ID
JOIN Course AS c ON c.course_ID = r.course_ID
WHERE m.dept_ID <> c.dept_ID
   OR c.course_Type <> 'Undergraduate'
   OR c.course_Credits <> 4
   OR r.min_Grade_Req <> 'C';

SELECT 'degree_requirement_plan_status' AS check_name,
       'Major' AS program_type,
       requirement_Status AS plan_status,
       COUNT(*) AS course_rows
FROM Major_Requirement
GROUP BY requirement_Status
UNION ALL
SELECT 'degree_requirement_plan_status',
       'Minor',
       requirement_Status,
       COUNT(*)
FROM Minor_Requirement
GROUP BY requirement_Status
ORDER BY program_type, plan_status;

SELECT 'major_requirement_prerequisite_gaps' AS check_name, COUNT(*) AS violations
FROM Major_Requirement AS r
JOIN Course_Prerequisite AS p ON p.course_ID = r.course_ID
LEFT JOIN Major_Requirement AS prior_req
       ON prior_req.major_ID = r.major_ID
      AND prior_req.course_ID = p.prerequisite_course_ID
WHERE prior_req.course_ID IS NULL;

SELECT 'minor_requirement_prerequisite_gaps' AS check_name, COUNT(*) AS violations
FROM Minor_Requirement AS r
JOIN Course_Prerequisite AS p ON p.course_ID = r.course_ID
LEFT JOIN Minor_Requirement AS prior_req
       ON prior_req.minor_ID = r.minor_ID
      AND prior_req.course_ID = p.prerequisite_course_ID
WHERE prior_req.course_ID IS NULL;

SELECT 'repeated_completed_courses' AS check_name, COUNT(*) AS violations
FROM (
    SELECT student_ID, course_ID
    FROM Student_History
    GROUP BY student_ID, course_ID
    HAVING COUNT(*) > 1
) AS repeated;

WITH all_records AS (
    SELECT h.student_ID, h.course_ID, h.semester_ID, h.Grade
    FROM Student_History AS h
    UNION ALL
    SELECT e.student_ID, cs.course_ID, e.semester_ID, e.Grade
    FROM Enrollment AS e
    JOIN Course_Section AS cs ON cs.CRN = e.CRN
), ordered_records AS (
    SELECT r.*,
           CAST(SUBSTRING_INDEX(s.semester_Name, ' ', -1) AS UNSIGNED) AS term_year,
           CASE WHEN LEFT(s.semester_Name, 4) = 'Fall' THEN 2 ELSE 1 END AS term_order
    FROM all_records AS r
    JOIN Semester AS s ON s.semester_ID = r.semester_ID
)
SELECT 'prerequisite_order_and_grade_violations' AS check_name, COUNT(*) AS violations
FROM ordered_records AS target
JOIN Course_Prerequisite AS p ON p.course_ID = target.course_ID
WHERE NOT EXISTS (
    SELECT 1
    FROM ordered_records AS prior
    WHERE prior.student_ID = target.student_ID
      AND prior.course_ID = p.prerequisite_course_ID
      AND prior.Grade IN ('A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C')
      AND (prior.term_year < target.term_year
           OR (prior.term_year = target.term_year AND prior.term_order < target.term_order))
);

SELECT 'undergraduate_earned_credit_mismatches' AS check_name, COUNT(*) AS violations
FROM (
    SELECT student_ID, credits_Earned FROM Full_Time_Undergraduate
    UNION ALL
    SELECT student_ID, credits_Earned FROM Part_Time_Undergraduate
) AS earned
LEFT JOIN (
    SELECT h.student_ID, SUM(c.course_Credits) AS credits
    FROM Student_History AS h
    JOIN Course AS c ON c.course_ID = h.course_ID
    WHERE h.Grade IN ('A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C')
    GROUP BY h.student_ID
) AS completed ON completed.student_ID = earned.student_ID
WHERE earned.credits_Earned <> COALESCE(completed.credits, 0);

SELECT 'full_time_graduate_earned_credit_mismatches' AS check_name, COUNT(*) AS violations
FROM Full_Time_Graduate AS earned
LEFT JOIN (
    SELECT h.student_ID, SUM(c.course_Credits) AS credits
    FROM Student_History AS h
    JOIN Course AS c ON c.course_ID = h.course_ID
    WHERE h.Grade IN ('A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C')
    GROUP BY h.student_ID
) AS completed ON completed.student_ID = earned.student_ID
WHERE earned.credits_Earned <> COALESCE(completed.credits, 0);

WITH student_loads AS (
    SELECT e.student_ID, e.semester_ID, COUNT(*) * 4 AS credits
    FROM Enrollment AS e
    GROUP BY e.student_ID, e.semester_ID
)
SELECT 'active_student_credit_load_violations' AS check_name, COUNT(*) AS violations
FROM student_loads AS l
JOIN Student AS s ON s.student_ID = l.student_ID
LEFT JOIN Full_Time_Undergraduate AS ftu ON ftu.student_ID = s.student_ID
LEFT JOIN Part_Time_Undergraduate AS ptu ON ptu.student_ID = s.student_ID
LEFT JOIN Full_Time_Graduate AS ftg ON ftg.student_ID = s.student_ID
LEFT JOIN Part_Time_Graduate AS ptg ON ptg.student_ID = s.student_ID
WHERE (ftu.student_ID IS NOT NULL AND l.credits NOT BETWEEN ftu.min_Credits AND ftu.max_Credits)
   OR (ptu.student_ID IS NOT NULL AND l.credits NOT BETWEEN ptu.min_Credits AND ptu.max_Credits)
   OR (ftg.student_ID IS NOT NULL AND l.credits <> 12)
   OR (ptg.student_ID IS NOT NULL AND l.credits NOT BETWEEN 4 AND 8);

WITH teaching_loads AS (
    SELECT faculty_ID, semester_ID, COUNT(*) AS section_count
    FROM Course_Section
    GROUP BY faculty_ID, semester_ID
)
SELECT 'faculty_section_load_violations' AS check_name, COUNT(*) AS violations
FROM teaching_loads AS l
JOIN Faculty AS f ON f.faculty_ID = l.faculty_ID
WHERE (f.faculty_Type = 'Full-Time' AND l.section_count > 2)
   OR (f.faculty_Type = 'Part-Time' AND l.section_count <> 1);

SELECT 'advisee_cap_violations' AS check_name, COUNT(*) AS violations
FROM (
    SELECT faculty_ID
    FROM Advisor
    GROUP BY faculty_ID
    HAVING COUNT(*) > 15
) AS over_cap;

SELECT 'course_section_faculty_department_mismatches' AS check_name, COUNT(*) AS violations
FROM Course_Section AS s
JOIN Course AS c ON c.course_ID = s.course_ID
LEFT JOIN Faculty_Department AS fd
       ON fd.faculty_ID = s.faculty_ID
      AND fd.dept_ID = c.dept_ID
WHERE fd.faculty_ID IS NULL;

WITH meetings AS (
    SELECT cs.CRN, cs.semester_ID, cs.faculty_ID,
           COALESCE(cs.lecture_ID, cs.lab_ID) AS room_ID,
           tsd.day_ID, p.start_Time, p.end_Time
    FROM Course_Section AS cs
    JOIN Time_Slot_Day AS tsd ON tsd.time_Slot_ID = cs.time_Slot_ID
    JOIN Time_Slot_Period AS tsp ON tsp.time_Slot_ID = cs.time_Slot_ID
    JOIN Period AS p ON p.period_ID = tsp.period_ID
)
SELECT 'faculty_meeting_conflicts' AS check_name, COUNT(*) AS violations
FROM meetings AS a
JOIN meetings AS b
  ON a.semester_ID = b.semester_ID
 AND a.faculty_ID = b.faculty_ID
 AND a.CRN < b.CRN
 AND a.day_ID = b.day_ID
 AND a.start_Time < b.end_Time
 AND b.start_Time < a.end_Time;

WITH meetings AS (
    SELECT cs.CRN, cs.semester_ID,
           COALESCE(cs.lecture_ID, cs.lab_ID) AS room_ID,
           tsd.day_ID, p.start_Time, p.end_Time
    FROM Course_Section AS cs
    JOIN Time_Slot_Day AS tsd ON tsd.time_Slot_ID = cs.time_Slot_ID
    JOIN Time_Slot_Period AS tsp ON tsp.time_Slot_ID = cs.time_Slot_ID
    JOIN Period AS p ON p.period_ID = tsp.period_ID
)
SELECT 'room_meeting_conflicts' AS check_name, COUNT(*) AS violations
FROM meetings AS a
JOIN meetings AS b
  ON a.semester_ID = b.semester_ID
 AND a.room_ID = b.room_ID
 AND a.CRN < b.CRN
 AND a.day_ID = b.day_ID
 AND a.start_Time < b.end_Time
 AND b.start_Time < a.end_Time;

WITH registrations AS (
    SELECT student_ID, CRN, semester_ID FROM Enrollment
    UNION
    SELECT student_ID, CRN, semester_ID FROM Student_History
), meetings AS (
    SELECT r.student_ID, cs.CRN, cs.semester_ID,
           tsd.day_ID, p.start_Time, p.end_Time
    FROM registrations AS r
    JOIN Course_Section AS cs ON cs.CRN = r.CRN
    JOIN Time_Slot_Day AS tsd ON tsd.time_Slot_ID = cs.time_Slot_ID
    JOIN Time_Slot_Period AS tsp ON tsp.time_Slot_ID = cs.time_Slot_ID
    JOIN Period AS p ON p.period_ID = tsp.period_ID
)
SELECT 'student_meeting_conflicts' AS check_name, COUNT(*) AS violations
FROM meetings AS a
JOIN meetings AS b
  ON a.student_ID = b.student_ID
 AND a.semester_ID = b.semester_ID
 AND a.CRN < b.CRN
 AND a.day_ID = b.day_ID
 AND a.start_Time < b.end_Time
 AND b.start_Time < a.end_Time;
