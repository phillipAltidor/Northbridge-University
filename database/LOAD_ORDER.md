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
45. `database/migrations/2026-11-07-portal-academic-rebuild.sql`
46. `database/data/academic_rebuild.sql`

Steps 45–46 supply the active 2026–2027 undergraduate catalog, program rules,
fictional registrar profiles, source CRN labels and exact meeting rows. Apply
that schema once on a fresh installation, after the ordinary referenced tables
exist. Keep the rebuilt seed files together; do not mix them with older course,
section, enrollment, history or program seeds. Load rosters before installing
seat triggers because the section seed already contains final seat counts.
Original people/account seed files were not changed by the rebuild.

The versioned tables supply full AND/OR requirements and prerequisites. Legacy
flat requirement tables remain for protected legacy meanings and cannot express
the new rule structure. Exact undergraduate meetings use `NB_AcademicMeeting`;
legacy day × period mappings must not be used to reconstruct those meetings.
See [active rebuild and rollback](../docs/academic-rebuild-status.md).


After importing the data, run
`php database/migrations/master_schedule_requirements.php --apply` to audit the
rosters/attendance and install the enrollment seat-count triggers. See
`docs/master-schedule.md`. Do not install these triggers before loading the seed
rosters: the section seeds already contain their final available-seat counts.

The four enrollment/history/attendance seeds now represent the approved academic
snapshot captured by their export receipt. The application date in
`config/academic-timeline.php` is live; it does not regenerate seed data. Enrollment retains
current and planned registrations, with NULL final grades for Fall 2026 and
later. Completed student/faculty histories contain only earlier semesters.
Fall 2026 attendance stops at the snapshot date; future terms have none.
Existing installations can audit with
`php database/migrations/align_academic_timeline.php`, then apply with `--apply`.
Application creates a private full backup, preserves all other tables and past
records, and reload-checks the synchronized seed SQL before committing.
This is a data snapshot, not an automatic academic calendar. Exact semester
boundaries and registration windows must come from the system manual.

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

## Meeting patterns and staged candidate

Rebuilt undergraduate sections preserve the reviewed Old Westbury source
meetings in `NB_AcademicMeeting`, including their dates. Custom four-credit
lectures prefer Monday/Wednesday or Tuesday/Thursday for 100 minutes each;
source-supported lab/studio and one-day patterns are handled separately.
Do not apply a blanket 120-minute lecture or 180-minute lab rule to this data.
Legacy graduate meetings retain their original day/period mappings.

The current V11 test candidate and campus/count overlays remain private until
cutover review. Repository installation seeds are an older snapshot and must
not be described as the verified V11 test state. Use the matching private seed
export receipt and PROJECT_HANDOFF.md for the reviewed candidate. Full-history
exports include faculty subtype class counts for the configured current term.
No schema changes or replacement of repository seeds are authorized by the audit.
