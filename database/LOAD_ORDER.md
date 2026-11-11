# V11 development installation and load order

This release packages the activated V11 academic data, including the approved
four-campus-building and faculty-count overlays. The manifest is
`database/releases/v11/manifest.json`; clean-install verification is recorded
beside it. Existing schema definitions and account seeds are unchanged.

For teammates, follow [V11 setup](../docs/v11-teammate-setup.md). The preferred
installer connects to local MySQL using your private .env and creates only a new,
unused development database. It verifies the complete bundle before execution:

```sh
php tools/install-v11-development.php --target northbridge_ow_rebuild_2026_9
php tools/install-v11-development.php --target northbridge_ow_rebuild_2026_9 --create
```

It never changes DB_NAME. After a successful result, choose the new database in
your own .env. It refuses existing targets; do not seed over populated databases.
Do not combine this release with demo_seed.sql. MySQL 9.6.0 was the verified
server; other engines/versions are not certified and require investigation if
schema fingerprints differ.

## Manual dependency order

Select a new target database. Base `database/schema.sql` contains a historical
USE northbridge_university line; the installer removes that line only in memory
and keeps the existing schema definitions. For manual import, select the intended
new target and account for that header explicitly. Then load these files once:

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


After the base and seed order above, apply the four existing supplemental
schemas (no operational log or recovery records are shared):

- `database/migrations/admin_53_update_log.sql`
- `database/migrations/admin_70_master_schedule.sql`
- `database/migrations/admin_70_section_update_log.sql`
- `database/migrations/password_reset.sql`

Finally install the reviewed statements returned by
`database/migrations/section_seat_triggers.php`. The V11 installer does this after
rosters are loaded, since sections already contain final availability. Do not
run the older timeline/master-schedule repair scripts as a V11 installation
step: they were written for legacy data/mappings and a different activation.
Existing installations need a separately reviewed migration or a new target;
they must not be overwritten by these seeds.

The source snapshot and hash identities are in the V11 manifest. The application
calendar date is live and does not regenerate attendance, grades or registration
windows. Fall 2026 is in progress and Spring 2027 preregistered; neither earns
completed credit or a final grade from the seed data. No deadline is invented.

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

The shared V11 seed package now matches the activated local academic release.
It contains 25 updated SQL files / 29 academic tables; original people/account
seed files remain unchanged. Private backups, credentials, operational records
and student-level planner/debug artifacts are excluded. Academic generation
source files and aggregate verification receipts are retained for continuity.
