# Master Schedule

## Scope

The Admin schedule lists the nine required semesters from Fall 2022 through
Fall 2026, plus the existing upcoming Spring 2027 schedule. It displays CRN, Course, Course Name, Section, Professor, Days, Time,
Building / Room, Total Seats, and Available Seats. Editable terms retain the Edit
action. Section numbers are plain integers. Semester is the table heading; credits
remain stored but are omitted from the schedule display.
Year and semester selections submit automatically. Course code/name/CRN search,
department, and available-seat filters affect the displayed rows only. Saving
always validates the complete semester snapshot, not the filtered result.
Results are paginated at 25 or 50 sections per page. Paging preserves filters;
changing filters starts again at the first page. The table has synchronized top
and bottom horizontal scrolling, a bounded vertical scroll area with sticky
column headers, and unwrapped codes/times. Pagination does not truncate saved
semester snapshots.

Rebuilt section capacity comes from NB_AcademicSection.source_JSON.capacity
(currently 3–10); legacy sections retain the ten-seat limit. Seats are calculated
from distinct Enrollment/Student_History roster membership, so history is not
counted again. The editor cannot set
seat counts or capacity manually. Database triggers update the stored seat count
on Enrollment inserts, drops, and transfers, reject full-section registration,
and validate the section's semester. Rollbacks also roll back seat changes.
Future registration code must use transactions and retry deadlocks as appropriate.
Direct roster imports must start with consistent section/roster counts.

The "As of" date follows today's calendar date in America/New_York on each page
request, rather than the original October 22 snapshot. Attendance checks use that
same live cutoff; advancing the date does not create attendance or change grades.
Fall 2026 remains the configured current semester until approved semester dates
are supplied. Its time slots
cannot change once the semester has started. Existing conflict checks remain;
rebuilt assignment edits validate department eligibility and semester teaching
limits. Faculty subtype class counters track the configured current semester.
See [schema alignment audit](schema-alignment-audit.md) for verified scope and limits.

## Installation

After loading the schema and full data, align the timeline and then run:

```sh
php database/migrations/master_schedule_requirements.php
php database/migrations/master_schedule_requirements.php --apply
```

Audit mode is read-only. Apply mode is restricted to the local project database,
creates a private SQL backup, retains missing historical Enrollment from existing
Student_History without changing grades, and corrects generated Fall attendance.
Bad synthetic dates are moved to the next scheduled weekday in the same week.
Duplicate Friday meeting entries collapse to one, keeping Absent if either entry
is Absent. Dates after the snapshot are excluded. Already valid attendance is
preserved. These are corrections to fictional project data, not real attendance.
Historical attendance is not invented: official academic calendars/holidays and
source records are required before generating complete historical attendance.

Enrollment and attendance seed exports are reload-tested. All other table rows
are checked unchanged before commit. The three seat triggers are installed only
after the data transaction commits; trigger DDL itself is not transactional.
If trigger installation fails, rerun the migration after resolving permissions.
Do not use the old cohort rebuild tools with the new retained enrollment/seat
triggers until their import process has been adapted and audited.

## Verification

```sh
php tests/master-schedule.php
php tests/master-schedule-data.php
php tests/academic-timeline.php --database
```

The first suite creates/drops an isolated test database. It tests capacity,
registration/drop/transfer behavior, rejected duplicates/full sections, rollback,
historical rosters, and filters. The other checks are read-only on the local DB.

## Remaining Work

This does not implement registration screens, prerequisite/hold/credit checks,
registration windows, faculty roster/attendance/grading workflows, section creation, or Spring
2027 master-schedule saving. Those workflows remain disabled until checked
against the SRS. Full historical attendance and holiday validation are pending
official semester calendars. Existing audit findings about faculty load and
department checks are not resolved by the seat-count triggers.

Separate read-only Student and Faculty semester schedule pages are implemented;
see student-dashboard.md and faculty-dashboard.md for their scope and tests.
