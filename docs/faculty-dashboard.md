# Faculty Dashboard

Faculty uses the existing shared home.php entry point, with a role-specific
Home view and sidebar. Navigation follows the supplied Faculty behavior list.
Its four groups cover My Account, Teaching, Advising, and Academic Information.
Groups start collapsed and remember per-user choices, just like the other roles.

Implemented:

- Personalized campus banner and live New York date.
- Three Quick Access tiles: Semester Schedule, Course Rosters, Advisee List.
- Read-only My Profile, including Full-Time/Part-Time employment type from Faculty.
- Read-only Semester Schedule: Fall 2026 by default, Spring 2027 by dropdown.
- Week at a Glance from the logged-in faculty member's course assignments.
- One row per section with every exact meeting group, date range, and location.
- Reference column order shared with student/master schedules; capacity and seat
  counts are omitted from display while backend seat enforcement remains intact.
- Course Rosters from the sidebar, Home tile, or assigned schedule section.
- Current/next and assigned historical rosters, with student ID, name, university
  email, student type, year, and major. Enrollment/history rows are deduplicated.
- Student Degree Audit selection and read-only evaluation.

The Advisee List tile and 15 unfinished sidebar actions remain disabled. No
attendance marking, grade assignment, advisee browsing, account editing,
master-schedule browsing, or academic catalogue workflows are implemented here.

Faculty profile and teaching queries use the session's user ID, not request IDs.
Other roles cannot enter these pages. Faculty Home does not expose Admin update
logs or link to Admin or Student pages. Existing passwords are unchanged.
Roster access checks the session faculty ID, local CRN, and semester before
reading students. Changing request faculty/user IDs cannot change that ownership;
unassigned sections return not found and malformed selectors are rejected.
Rosters reject non-GET requests and send Cache-Control: no-store.

Week at a Glance shows recurring class meetings, not attendance. Official semester
boundaries, holidays/cancellations, registration windows, and grading windows
still need the approved calendar. Faculty load/department/advisee limits remain
requirements for future assignment workflows; this read-only UI does not repair
or certify those relationships.

Student and Faculty schedule formatting share includes/meeting-schedule.php.
Table columns share includes/schedule-columns.php; see semester-schedule-layout.md.
Role-specific queries remain separate so enrollment and teaching access do not mix.

Checks:

```sh
php tests/faculty-dashboard.php
php tests/faculty-dashboard.php --database
php tests/faculty-dashboard-http.php
NORTHBRIDGE_REHEARSAL_DB=northbridge_ow_rebuild_2026_7 NORTHBRIDGE_HTTP_BASE=http://127.0.0.1:8003/ php tests/faculty-rosters-http.php
php tests/student-dashboard.php
php tests/student-dashboard-http.php
node tests/admin-profile.cjs
php tests/admin-navigation.php
```

Database checks are read-only. Dashboard HTTP checks support 127.0.0.1:8000 or
the NORTHBRIDGE_HTTP_BASE override at 127.0.0.1:8003 with disposable
authenticated sessions, remove those sessions, and verify protected records stay
unchanged. No real login credentials are printed or reset.
