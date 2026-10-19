-- Checks historical section rosters and seat counts.

USE northbridge_university;

WITH section_rosters AS (
    SELECT cs.CRN, COUNT(sh.student_ID) AS roster_size, cs.available_Seats
    FROM Course_Section AS cs
    LEFT JOIN Student_History AS sh ON sh.CRN = cs.CRN
    WHERE cs.semester_ID NOT IN (1, 2)
    GROUP BY cs.CRN, cs.available_Seats
)
SELECT 'historical_roster_range' AS check_name,
       MIN(roster_size) AS minimum_roster,
       MAX(roster_size) AS maximum_roster,
       SUM(roster_size < 5 OR roster_size > 10) AS roster_violations,
       SUM(available_Seats <> 10 - roster_size) AS seat_violations
FROM section_rosters;

WITH section_rosters AS (
    SELECT cs.course_ID, cs.semester_ID, cs.CRN, COUNT(sh.student_ID) AS roster_size
    FROM Course_Section AS cs
    LEFT JOIN Student_History AS sh ON sh.CRN = cs.CRN
    WHERE cs.semester_ID NOT IN (1, 2)
    GROUP BY cs.course_ID, cs.semester_ID, cs.CRN
), offering_rosters AS (
    SELECT course_ID, semester_ID, COUNT(*) AS section_count,
           SUM(roster_size) AS offering_roster
    FROM section_rosters
    GROUP BY course_ID, semester_ID
)
SELECT 'historical_offerings_below_section_minimum' AS check_name,
       COUNT(*) AS offering_violations
FROM offering_rosters
WHERE offering_roster < 5 * section_count;

WITH section_rosters AS (
    SELECT
        cs.CRN,
        cs.course_ID,
        c.course_Name,
        cs.section_No,
        cs.semester_ID,
        s.semester_Name,
        cs.time_Slot_ID,
        cs.available_Seats,
        COUNT(sh.student_ID) AS roster_size
    FROM Course_Section AS cs
    JOIN Course AS c ON c.course_ID = cs.course_ID
    JOIN Semester AS s ON s.semester_ID = cs.semester_ID
    LEFT JOIN Student_History AS sh ON sh.CRN = cs.CRN
    WHERE cs.semester_ID NOT IN (1, 2)
    GROUP BY
        cs.CRN, cs.course_ID, c.course_Name, cs.section_No,
        cs.semester_ID, s.semester_Name, cs.time_Slot_ID, cs.available_Seats
)
SELECT
    CRN,
    course_ID,
    course_Name,
    section_No,
    semester_ID,
    semester_Name,
    time_Slot_ID,
    roster_size,
    available_Seats,
    CASE
        WHEN roster_size < 5 THEN 'UNDER 5'
        WHEN roster_size > 10 THEN 'OVER 10'
    END AS roster_issue
FROM section_rosters
WHERE roster_size < 5 OR roster_size > 10
ORDER BY semester_ID, course_ID, section_No;

WITH section_rosters AS (
    SELECT
        cs.CRN,
        cs.course_ID,
        cs.semester_ID,
        COUNT(sh.student_ID) AS roster_size
    FROM Course_Section AS cs
    LEFT JOIN Student_History AS sh ON sh.CRN = cs.CRN
    WHERE cs.semester_ID NOT IN (1, 2)
    GROUP BY cs.CRN, cs.course_ID, cs.semester_ID
)
SELECT
    sr.course_ID,
    c.course_Name,
    sr.semester_ID,
    s.semester_Name,
    COUNT(*) AS section_count,
    SUM(sr.roster_size) AS offering_roster,
    SUM(GREATEST(0, 5 - sr.roster_size)) AS section_deficits_to_5_before_redistribution,
    CASE
        WHEN SUM(sr.roster_size) < 5 THEN 'COURSE SUBSTITUTIONS REQUIRED'
        WHEN SUM(sr.roster_size) < 5 * COUNT(*) THEN 'NOT ENOUGH STUDENTS TO KEEP EVERY SECTION; REVIEW SECTION COUNT'
        ELSE 'REDISTRIBUTE WITHIN OFFERING'
    END AS recommended_repair
FROM section_rosters AS sr
JOIN Course AS c ON c.course_ID = sr.course_ID
JOIN Semester AS s ON s.semester_ID = sr.semester_ID
GROUP BY sr.course_ID, c.course_Name, sr.semester_ID, s.semester_Name
HAVING SUM(sr.roster_size) < 5
    OR SUM(CASE WHEN sr.roster_size < 5 THEN 1 ELSE 0 END) > 0
ORDER BY sr.semester_ID, sr.course_ID;

WITH section_rosters AS (
    SELECT cs.CRN, cs.course_ID, cs.semester_ID, COUNT(sh.student_ID) AS roster_size
    FROM Course_Section AS cs
    LEFT JOIN Student_History AS sh ON sh.CRN = cs.CRN
    WHERE cs.semester_ID NOT IN (1, 2)
    GROUP BY cs.CRN, cs.course_ID, cs.semester_ID
), affected_offerings AS (
    SELECT course_ID, semester_ID, SUM(roster_size) AS offering_roster
    FROM section_rosters
    GROUP BY course_ID, semester_ID
    HAVING SUM(roster_size) < 5
        OR SUM(CASE WHEN roster_size < 5 THEN 1 ELSE 0 END) > 0
)
SELECT
    sh.student_ID,
    sh.CRN,
    sh.course_ID,
    sh.semester_ID,
    cs.time_Slot_ID,
    d.day_Of_Week,
    p.start_Time,
    p.end_Time,
    ao.offering_roster
FROM Student_History AS sh
JOIN Course_Section AS cs ON cs.CRN = sh.CRN
JOIN affected_offerings AS ao
    ON ao.course_ID = sh.course_ID AND ao.semester_ID = sh.semester_ID
JOIN Time_Slot_Day AS tsd ON tsd.time_Slot_ID = cs.time_Slot_ID
JOIN Day AS d ON d.day_ID = tsd.day_ID
JOIN Time_Slot_Period AS tsp ON tsp.time_Slot_ID = cs.time_Slot_ID
JOIN Period AS p ON p.period_ID = tsp.period_ID
ORDER BY sh.semester_ID, sh.student_ID, p.start_Time, d.day_ID, sh.CRN;

WITH section_rosters AS (
    SELECT cs.CRN, cs.semester_ID, cs.available_Seats,
           COUNT(sh.student_ID) AS roster_size
    FROM Course_Section AS cs
    LEFT JOIN Student_History AS sh ON sh.CRN = cs.CRN
    WHERE cs.semester_ID NOT IN (1, 2)
    GROUP BY cs.CRN, cs.semester_ID, cs.available_Seats
)
SELECT CRN, semester_ID, roster_size, available_Seats,
       10 - roster_size AS expected_available_Seats
FROM section_rosters
WHERE available_Seats <> 10 - roster_size
ORDER BY semester_ID, CRN;
