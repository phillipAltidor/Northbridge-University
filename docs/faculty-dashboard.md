# Faculty Dashboard

Faculty uses the existing shared home.php entry point, with a role-specific
Home view and sidebar. Navigation follows the supplied Faculty behavior list.
Its four groups cover My Account, Teaching, Advising, and Academic Information.
Groups start collapsed and remember per-user choices, just like the other roles.

Implemented:

- Personalized campus banner and live New York date.
- Three Quick Access tiles: Semester Schedule, Course Rosters, Advisee List.
- Read-only My Profile, with the shared email/ID hover preview.
- Read-only Semester Schedule: Fall 2026 by default, Spring 2027 by dropdown.
- Week at a Glance from the logged-in faculty member's course assignments.
- Grouped meeting days and enrollment-derived available seats.

Course Rosters and Advisee List tiles remain grey, along with the 17 unfinished
sidebar actions. No attendance marking, grade assignment, advisee browsing,
student-record access, account editing, master-schedule browsing, or academic
catalogue workflows are implemented by this dashboard.

Faculty profile and teaching queries use the session's user ID, not request IDs.
Other roles cannot enter these pages. Faculty Home does not expose Admin update
logs or link to Admin or Student pages. Existing passwords are unchanged.

Week at a Glance shows recurring class meetings, not attendance. Official semester
boundaries, holidays/cancellations, registration windows, and grading windows
still need the approved calendar. Faculty load/department/advisee limits remain
requirements for future assignment workflows; this read-only UI does not repair
or certify those relationships.

Student and Faculty schedule formatting share includes/meeting-schedule.php.
Role-specific queries remain separate so enrollment and teaching access do not mix.

Checks:

```sh
php tests/faculty-dashboard.php
php tests/faculty-dashboard.php --database
php tests/faculty-dashboard-http.php
php tests/student-dashboard.php
php tests/student-dashboard-http.php
node tests/admin-profile.cjs
php tests/admin-navigation.php
```

Database checks are read-only. HTTP checks use 127.0.0.1:8000 with disposable
authenticated sessions, remove those sessions, and verify protected records stay
unchanged. No real login credentials are printed or reset.
