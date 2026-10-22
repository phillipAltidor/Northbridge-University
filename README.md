# Northbridge University Portal

PHP and MySQL portal currently scoped to use cases 1-5, 52, 53, and 70:

- Successful Login
- Unsuccessful Login
- Password Reset
- Unsuccessful Password Reset
- View Student Information
- Create User
- Update User Information
- Create Master Schedule for Fall 2026

## Current Status

The public entry page (`index.php`) displays top navigation above an illustrative
campus image. Its Login dropdown contains Student, Faculty, Admin, and Statistics
Staff links; public self-registration is not available. Each link opens
`login.php?role=...` with a matching login heading. The shared login handler
verifies credentials and the stored account role; a portal selection never
grants permissions. A role mismatch does not increase failed-login attempts.
Direct `login.php` access retains the general login form for compatibility.
Reset Password navigation preserves the selected portal. Recovery now uses
SMTP-delivered codes and server-side verification as described below.
Login screens share the welcome page's N seal, tagline, campus background, and
compact navy footer. Home navigation is in the header. A solid white panel keeps
the role heading, credentials, Login button, and Reset Password link visible;
login errors appear only when applicable, without a permanent lockout notice.

Login and account lockout are implemented. All roles open Home. Admin Home has
a personalized campus-photo welcome banner, quick-access links, and the approved
grouped sidebar. Groups start collapsed and remember the user's choices;
unfinished actions are grey and disabled. Other roles retain their minimal Home
page displaying the logged-in person's name and ID. Students can select Student Information
to view their own personal User data and Login email. Academic information is
hidden for now; the page does not require an academic Student record.
Detailed admin information remains hidden for now.
Update User Information (use case 53) lets admins find a user by ID, name, or
email, edit personal details and login email, and record the user’s request.
User ID and role stay fixed. Every saved change records the administrator, UTC
time, request note, and before/after values in User_Update_Log, in the same
transaction as the update. Passwords and academic fields are excluded. The
page shows the latest 20 changes and rejects stale edits from another admin.
Create User is admin-only and follows the User fields for Student, Faculty,
Admin, and StatStaff. It assigns a unique name-based university email and a
random initial password, storing only the password hash in Login. The assigned
ID and credentials are shown once after creation. User and Login are saved
together with Admin or Stat_Dept_Member records for those roles. Student and
Faculty academic records are deferred because the form does not collect their
required academic fields. Create User authorization checks Admin.admin_ID;
Schema 2.3 has no security_Level column.

Password reset implements expiring, single-use emailed codes, invalid-code
rejection, verified password changes, and account unlocking for all four roles.
It needs Composer dependencies, the supplemental password-reset migration, and
private SMTP settings. Original account records are not rewritten during setup.
For fictional accounts, optional private `MAIL_DEMO_RECIPIENT` routing sends
recovery emails to one demo inbox without changing login addresses. This is
allowed only in `local` or `demo` environments, never with real users.
See [password-reset setup and tests](docs/password-reset.md). Automated tests
cover the flow, but real inbox delivery and a real-account demonstration must
also be confirmed before treating the presentation workflow as complete.

Admins also have Create User and Update User Information. For this checkpoint's
database upgrade sequence, see [teammate setup](docs/checkpoint-setup.md).
Master Schedule (use case 70) lets admins select ten semesters from Fall 2022
through Spring 2027. Historical and Spring 2027 schedules are read-only.
The approved academic snapshot is October 22, 2026: Fall 2026 is in progress
with ungraded enrollment and attendance only through that date. Spring 2027
contains planned registrations and its schedule, but no attendance, final grades,
or completed student/faculty history. Earlier semesters retain their results.
`config/academic-timeline.php` records this explicit snapshot; the manual must
provide semester boundaries and registration windows before automatic rollover
or registration eligibility is implemented. The timeline migration and full-data
load instructions are documented in `database/LOAD_ORDER.md`.
Fall 2026 supports reviewing, editing, and saving Course_Section records.
Semester selection loads the schedule automatically. Each Fall 2026 section can be edited:
course_ID, section_No, faculty_ID, and optional room_ID. Time-slot changes are
blocked once the semester starts. Maximum seats are fixed at 10 and available
seats are read-only, calculated from the relevant semester roster. Enrollment
triggers synchronize stored seats on registration/drop/transfer and reject full
sections. The schedule includes credits, separate times, building, capacity,
semester/year, and course/department/availability filters. See
[Master Schedule setup and limitations](docs/master-schedule.md).
CRN and semester_ID remain fixed to protect existing references.
The form validates foreign keys, section numbers and nonnegative seats, rejects
duplicate course/section numbers, and checks instructor/room and enrolled-student overlaps when those
assignments change. A course cannot be reassigned on a section with enrollment
or academic history. Time conflict checks use Time_Slot_Day, Time_Slot_Period,
and Period mappings; changed assignments require these mappings.

Section edits update Course_Section, add before/after history in
Course_Section_Update_Log, and refresh an already saved Master_Schedule in one
transaction. Stale edits are rejected. Adding, deleting, or moving sections to
another semester is outside this page's scope.

Unrelated academic management, registration, transcripts, reports, and public
self-registration pages have been removed.

## Course identifiers

Course.course_ID and every referencing column use VARCHAR(20), including
Course_Prerequisite.prerequisite_course_ID. Codes combine the assigned department
prefix and original numeric identifier (for example 1101 becomes CS1101).
Assigned codes stay fixed if a course later changes departments.

| Department | Prefix |
| --- | --- |
| Computer Science | CS |
| Business | BUS |
| Mathematics | MATH |
| Natural Sciences | SCI |
| Engineering | ENG |
| English | ENGL |
| Social Sciences | SOC |
| Education | EDU |
| Health Sciences | HSC |
| Arts and Media | ART |

The mapping is defined in config/course-prefixes.php. Existing numeric local
databases can be migrated with `php database/migrations/course_ids_to_codes.php`.
This CLI-only migration is restricted to northbridge_local at 127.0.0.1. It saves
a full SQL backup, preserves/restores affected foreign keys, converts course IDs
and JSON references in saved schedules/history, and verifies all table contents.
MySQL column changes auto-commit; use the generated backup if recovery is needed.
Fresh installations already use text IDs and do not need this migration. For an
older branch schema, apply schema_2_3.sql before the course-code migration.

## Fall 2026 meeting times

Fall 2026 is distributed across Monday/Wednesday, Tuesday/Thursday, and Friday:
124 sections per day group. Four-credit Lecture meetings last 120 minutes;
four-credit Lab meetings last 180 minutes. Three-credit meeting slots last
90 minutes, although the current Fall dataset has no three-credit courses.
Durations apply to each meeting. New slots were added without changing Spring
or historical definitions. The edit form rejects assignments with incompatible
durations and checks faculty, room, and enrolled-student overlaps.

The applied plan is documented in database/migrations/fall_2026_meetings.json.
The corresponding CLI migration creates a full backup, checks its expected
source records, changes only Fall time-slot assignments, and verifies all
resource conflicts and meeting durations before committing. It is a one-time
migration; do not rerun it on an already migrated database. Fresh imports use
the updated seed files directly.

## Room identifiers

Course_Section.room_ID replaces lab_ID and references Room.room_ID. Room IDs
use VARCHAR(20) building codes plus room numbers (for example N212). Lecture,
Lab, Office, Faculty.office_ID, and Department.office_ID use matching text IDs
so their existing relationships remain intact. Room.room_Number remains INT.
The section editor offers only Lecture and Lab rooms and displays building,
room number, and room type. Sections may remain unassigned during preparation.

Existing numeric-room installations can run
`php database/migrations/section_rooms_to_codes.php` after the course-code
migration. It is CLI-only and restricted to the local database. It backs up
all tables, preserves lab locations, restores original lecture rooms using
section_room_assignments.csv, converts related identifiers and audit JSON, and
refreshes saved schedules. Explicit admin removals of lab locations are retained.
It checks recovered room conflicts and verifies every section and table count.
MySQL DDL auto-commits; retain the generated SQL backup for recovery.
Fresh installations already use the updated schema and seed files.

## Local Setup

1. Create a local database named `northbridge_university`.
   Install PHP dependencies with `composer install --no-dev`.
2. Import `database/schema.sql` (Schema 2.3), then
   `database/migrations/admin_53_update_log.sql` and
   `database/migrations/admin_70_master_schedule.sql` and
   `database/migrations/admin_70_section_update_log.sql`, then `database/demo_seed.sql`.
   Password reset additionally requires `database/migrations/password_reset.sql`.
3. Copy `.env.example` to `.env` and configure your local database credentials.
4. Run `php -S 127.0.0.1:8000` from the project folder.
5. Open http://127.0.0.1:8000.

The demo accounts use `Password123!`: student `jordan.ellis@northbridge.edu`,
faculty `e.vasquez@northbridge.edu`, admin `m.liu@northbridge.edu`, and statistics
staff `t.reyes@northbridge.edu`. These are separate from accounts in a remote database.

For an existing database using this branch's previous schema,
`database/migrations/schema_2_3.sql` applies the Schema 2.3 changes after you
select the target database. Back up first and run the migration only once.
Review populated Faculty, Room, Part_Time_Graduate, and Time_Slot tables before
running: the migration removes Faculty.no_of_Classes, converts room numbers to
integers, and adds required graduate/year and time-slot fields. It preserves
User and Login records, including passwords and account lock status.

Use case 53 also requires `database/migrations/admin_53_update_log.sql` on
existing databases. This creates a supplemental audit table; it does not alter
the supplied Schema 2.3 tables. Use case 70 similarly requires
`database/migrations/admin_70_master_schedule.sql` to store saved schedules
and `database/migrations/admin_70_section_update_log.sql` for section history.

The current local installation uses `northbridge_local` as configured in `.env`.
The full dataset in `database/data/` is adapted to Schema 2.3. For full-data
setup, use these files instead of `demo_seed.sql`, following `database/LOAD_ORDER.md`.
Existing reports have not been adapted to Schema 2.3.
Keep `.env` private and use separate database settings on AWS.

## Project Structure

- admin/master-schedule.php: Admin-only Fall 2026 schedule review and save
- admin/edit-section.php: Schema 2.3 section edits, conflict checks, and change history
- admin/update-user.php: Admin-only user search, personal/email updates, and audit history
- admin/create-user.php: Admin-only User and Login creation for all four roles
- admin/information.php: Compatibility redirect to Home
- student/information.php: Read-only personal data for the logged-in student
- index.php: Login form
- login.php: Credential verification and account lockout
- reset-password.php: Email-code verification, password reset, and account unlocking
- home.php: Shared login destination with the person's name and ID
- logout.php: End the authenticated session
- includes/: Shared authentication, layout, and query helpers
- config/: Database configuration
- assets/css/: Shared styles
- database/: Original schema and sample data
