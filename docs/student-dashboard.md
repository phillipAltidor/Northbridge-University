# Student Dashboard

The Student sidebar maps to the supplied use cases 5-27. There are 19 navigation
actions, plus Home. Current and next semester schedules share one Semester Schedule
entry, defaulting to the current semester with a dropdown for the next semester.
Failure outcomes remain part of registration, dropping, and
adding a major; they are not separate navigation actions.

Implemented read-only actions:

- Student Information / My Profile: read-only academic, personal, contact, and account details.
- Semester Schedule: current Fall 2026 and next Spring 2027 views.
- Home with three Quick Access tiles and Week at a Glance.

The 17 remaining actions are grey, non-interactive items. Registration is also
disabled in Quick Access. This dashboard does not implement registration, drop,
major/minor changes, or their validation rules.

Schedules read only the session student's Enrollment records, matching each
section's semester. Rebuilt sections use exact NB_AcademicMeeting records; legacy
sections use Time_Slot_Day/Day and Time_Slot_Period/Period. Names and rooms come
from the related records. URL
parameters cannot select another student's information.

Academic Details reads Student.student_Type and student_Year, the applicable
Undergraduate or Graduate_Student enrollment type, the primary Major, and that
subtype's Department. Undergraduate years 1-4 display Freshman, Sophomore,
Junior, and Senior; graduate students display their numbered graduate year.
Missing academic fields display Not provided. Class standing does not use the
earned-credit totals awaiting reconciliation, and this view does not change data.

Semester Schedule has one row per section and displays full day names such as
Monday, Wednesday. Only meetings with the same CRN, times, date range, faculty,
and location are grouped; all distinct meeting groups remain in the row.
Column order follows the owner reference without capacity/seat-count columns;
see semester-schedule-layout.md. Backend seat tracking remains unchanged.
Week at a Glance retains its individual daily meetings.

The semester names use config/academic-timeline.php. Week at a Glance uses the
current Monday-Sunday week in America/New_York, highlights today, and shows the
current semester's recurring meeting pattern. It is not an attendance record.
Holiday/cancellation exceptions and exact semester dates require the approved
academic calendar and are not inferred. Spring 2027 is view-only; no changes to
enrollment, grades, attendance, or registration windows are made here.

Checks:

```sh
php tests/student-dashboard.php
php tests/student-dashboard.php --database
php tests/student-dashboard-http.php
node tests/admin-profile.cjs
node tests/student-schedule.cjs
php tests/admin-navigation.php
```

The database and HTTP checks are read-only. The HTTP check expects the local PHP
server at 127.0.0.1:8000 and creates then removes disposable authenticated sessions.
