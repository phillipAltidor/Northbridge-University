# Faculty use cases

Work branch: `codex/faculty-use-cases`. The fifteen previously disabled actions
now have faculty-only routes, responsive forms/tables, and server-side handling.
This is application implementation, not a claim that every institutional policy
has been approved or that Phillip's site has been deployed.

## Manual comparison

| Menu action | Manual use case | Behavior |
| --- | --- | --- |
| Master Schedule | 28–34 | Semester, department, course, faculty, day/time, building/room and level filters; exact meeting groups, seat counts and pagination; read-only |
| Departments | 38 | Department contacts, chair, manager, office and links to offerings |
| Courses Offered by Departments | 39 | Department selection, names, credits, level, descriptions and prerequisite links |
| Majors Offered by Departments | 40 | Department majors with requirement links |
| Minors Offered by Departments | 41 | Department minors |
| Course Prerequisites | 42 | Reviewed AND/OR rules when academic metadata exists; otherwise clearly labeled recorded flat prerequisites |
| Major Requirements | 43 | Reviewed groups, alternatives, concentrations and degree/program policies; legacy rows explicitly labeled as partial |
| Course Requirements for a Major | 44 | Same complete program structure, preserving alternatives rather than turning every option into a requirement |
| Student Unofficial Transcript | 45 | Faculty student search; chronological, deduplicated history/enrollment, course credits, grades and term status; no earned credit inferred from current/planned work |
| Student Information | 37 | Academic/contact basics for enrolled students in the faculty member's sections and their advisees; no DOB or personal address |
| Mark Attendance | 47 | Own enrolled roster, approved class date and exact meeting period, today's date only, Present/Absent, atomic save and stale-form rejection |
| Assign Grades | 48 | Own enrolled roster, approved per-section grading window, permitted grades, atomic save and existing-grade protection |
| Update Password | 49 | Current-password check, existing 12–72-byte policy, confirmation, password hashing and session-version invalidation |
| Update Address | 50 | Own four address fields only, required/length validation, optimistic concurrency and row lock |
| Advisee List | 51 | Own assigned advisees with transcript and degree-audit links |

Transcript access follows use case 45 and the existing faculty degree-audit model:
faculty can search students. Student Information is more narrowly scoped to
teaching/advising relationships. Neither page grants edit access to student data.
Existing faculty profile, semester schedule, roster and degree-audit behavior is
retained. The Home advisee tile now opens the implemented list.

## Calendar and grading limits

`config/faculty-windows.php` uses the owner-confirmed Fall 2026 boundaries
(August 24–December 16) and Spring 2027 boundaries (January 25–May 18).
Regular attendance saves are available only on an approved date during the
selected section’s exact meeting period. Per-section grading remains unconfigured.
Never use browser-submitted dates or query parameters to override the clock.
Server time is America/New_York. Attendance uses exact NB_AcademicMeeting rows
when available and legacy day/period mappings otherwise; dates must also appear
in the approved attendance-date list, so holidays are not silently counted.

The owner confirmed these semester dates on 2026-11-19 after suggesting
Old Westbury’s calendar. Official sources checked on that date:
[Fall 2026 calendar](https://www.oldwestbury.edu/fall-2026-academic-calendar) gives
August 24 as the first day and December 16 as the last day, with finals December
10–16 and final grades due 48 hours after each scheduled final. The
[academic calendars page](https://www.oldwestbury.edu/degrees-programs/academic-calendars)
gives January 25 as the first day of Spring 2027. These sources do not by themselves
approve Northbridge grading opening times, part-of-term treatment, makeup meetings,
or rollover. Regular attendance excludes published holidays/recesses, study/makeup and final
exam dates. May 4 Spring attendance closes at 16:30 for convocation. Individual
section start/end dates are also checked. Makeup/final-exam attendance and
section-specific grading windows remain pending; the broader portal
current/next-term configuration is unchanged.

The owner confirmed Fall finals December 10–16, 2026 and Spring finals May
12–18, 2027, with grades due 48 hours after each final. Each internal CRN needs a
verified `final_exam_ends_at` timestamp with the correct New York offset and
`allowed_grades`. Grade entry opens at that instant and closes exactly 48 elapsed
hours later (exclusive), including deadlines after semester end. Missing, invalid,
or out-of-exam-range times fail closed. Regular class times are not exam times.
The previous completed-semester guard is superseded for grade entry by this
per-section rule; transcript visibility and term rollover remain unchanged.
This implementation does not enter midterm grades (there is
no separate field), change existing final grades, award degrees, rewrite historical
attempts, recalculate stored subtype credit counters, or perform term rollover.
Live audits read the saved grades; a preexisting history row is kept consistent.
Registrar finalization and stored-counter maintenance remain separate work.

## Authorization and safe writes

All routes validate both the session role and a corresponding Faculty/User/Login
record. Read-only pages refuse POST. Write pages require a session CSRF token.
Passwords are never repopulated into HTML or logged. Password attempts are throttled
within the session; this is not a replacement for deployment-wide rate limiting.
Password updates support the existing legacy-login compatibility path and always
store a hash afterward. Email, user ID, role and unrelated profile fields are fixed.

Marks lock the faculty and section rows, revalidate section ownership and enrolled
student IDs, compare a roster snapshot bound to section/date/action, validate the
window again before commit, and roll back the whole request on failure. Empty marks
are unchanged. Faculty cannot modify already recorded final grades. Missing or
unconfigured policy denies writes instead of inventing dates.

## Local demo and tests

See `docs/local-demo.md` for the separate local database and role logins. The
repository seeds are an older installation snapshot, not the private V11 preview.
Credentials, runtime files and database storage are ignored in `.local/` and `.env`.
Do not add them to a commit or upload bundle.

Tests:

- `php tests/faculty-features.php`: isolated in-memory schema, validation, ownership,
  account changes, calendar boundaries, rollback, grade/history consistency and
  current-term protection. SQLite removes row-lock syntax and cannot certify MySQL.
- `php tests/faculty-feature-views.php`: all fifteen templates, labels, CSRF inputs,
  read-only forms and closed grading state. An optional output directory renders
  fictional static previews for browser layout checks.
- `php tests/faculty-dashboard.php`: navigation, active routes and subfolder URLs.
- `python3 tests/faculty-features-http.py`: loopback-only actual role logins, all
  faculty routes, catalog selectors, schedule filters, Admin/Student existing pages,
  CSRF/calendar denial, temporary own address/password saves with restoration, and
  private-file HTTP protection. Refuses any database except the named local demo.
- Live local HTTP and MySQL results are recorded in PROJECT_HANDOFF.md as they are
  actually observed, separately from fixture-only checks.

Before a shared-site deployment, review the diff, complete the approved calendar,
verify the target MySQL dataset and concurrency, and review the role/access policy.
No merge, push, source-seed replacement or shared database activation is implied.
