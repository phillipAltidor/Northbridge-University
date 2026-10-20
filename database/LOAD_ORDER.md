# Schema 2.3 seed load order

Select the target database first. On a fresh database, create the tables using
`database/schema.sql`, then run every file below once, in this order. These
files use regular INSERT statements and should not be rerun over populated
seed tables. Do not combine this dataset with `demo_seed.sql` on a fresh database.

Password recovery also requires `database/migrations/password_reset.sql` after
creating the schema. Its temporary recovery rows are not shared seed data.

1. `database/data/user.sql`
2. `database/data/admin.sql`
3. `database/data/stat_staff.sql`
4. `database/data/building.sql`
5. `database/data/day.sql`
6. `database/data/period.sql`
7. `database/data/time_slot.sql`
8. `database/data/semester.sql`
9. `database/data/hold.sql`
10. `database/data/login.sql`
11. `database/data/room.sql`
12. `database/data/lecture.sql`
13. `database/data/office.sql`
14. `database/data/lab.sql`
15. `database/data/faculty.sql`
16. `database/data/full_time_faculty.sql`
17. `database/data/part_time_faculty.sql`
18. `database/data/department.sql`
19. `database/data/major.sql`
20. `database/data/minor.sql`
21. `database/data/student.sql`
22. `database/data/undergraduate.sql`
23. `database/data/graduate_student.sql`
24. `database/data/full_time_undergraduate.sql`
25. `database/data/part_time_undergraduate.sql`
26. `database/data/full_time_graduate.sql`
27. `database/data/part_time_graduate.sql`
28. `database/data/course.sql`
29. `database/data/major_requirement.sql`
30. `database/data/minor_requirement.sql`
31. `database/data/time_slot_day.sql`
32. `database/data/time_slot_period.sql`
33. `database/data/faculty_department.sql`
34. `database/data/student_major.sql`
35. `database/data/student_minor.sql`
36. `database/data/student_hold.sql`
37. `database/data/advisor.sql`
38. `database/data/course_prerequisite.sql`
39. `database/data/course_section.sql`
40. `database/data/historical_course_section.sql`
41. `database/data/enrollment.sql`
42. `database/data/student_history.sql`
43. `database/data/faculty_history.sql`
44. `database/data/attendance.sql`

## Schema 2.3 adaptations

- `admin.sql` inserts `Admin.admin_ID`; `stat_staff.sql` inserts
  `Stat_Dept_Member.stat_Member_ID`. The former security/employment/access
  fields are absent from Schema 2.3 and are omitted, not interpreted as
  application authorization rules.
- Faculty class counts remain in Full_Time_Faculty and Part_Time_Faculty.
  They are omitted from Faculty, where the column was removed.
- Course sections use room_ID referencing Room, supporting both Lecture and
  Lab locations. Original lecture room assignments have been recovered.
  Room and related subtype/office IDs are building-code-plus-number strings.
- Major and minor requirements use the plural table names. The existing
  proposed curricula remain proposed; Schema 2.3 has no approval-status field.
- Room numbers are numeric literals for the INT column.
- Time_Slot descriptions are derived from the existing Day, Period,
  Time_Slot_Day and Time_Slot_Period seeds. The mapping rows are also retained.
- Part_Time_Graduate.year is copied from the corresponding Student.student_Year.
  Its original seed contains no earned-credit or thesis data, so those fields
  use the schema defaults of 0 and NULL. Zero is an initialization value, not a
  verified academic credit total.

Course IDs and prerequisite course IDs are VARCHAR(20) department-prefixed
codes. Every reference uses the same assigned code, including course sections,
requirements, attendance, and student/faculty history. CRNs and other IDs remain
unchanged. See config/course-prefixes.php and README.md for the prefix mapping.

User and Login profiles, emails, passwords, attempt counts and lock flags are
unchanged. This dataset contains accounts distinct from the four demo accounts.
`course_section.sql` and `historical_course_section.sql` both insert into
Course_Section and must both be loaded before teaching/student histories.

## Fall meeting durations

New Period/Time_Slot mappings support 90-, 120-, and 180-minute meetings.
The Fall section seed uses Monday/Wednesday, Tuesday/Thursday and Friday.
Four-credit lectures use 120 minutes and four-credit labs 180 minutes per
meeting. Existing Spring and historical time-slot definitions are retained.
The current Fall dataset has no three-credit sections; 90-minute slots are
available for those courses when introduced.
