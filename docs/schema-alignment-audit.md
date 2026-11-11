# Schema alignment audit — November 9, 2026

The V11 test database `northbridge_ow_rebuild_2026_7` passes the implemented
portal checks below. Its schema is unchanged: 43 base Schema 2.3 tables plus
10 existing portal extension tables, for 53 tables total. This does not approve
cutover or certify features that have not been implemented.

| Area | Verified relationship / result |
| --- | --- |
| Keys | All 76 declared foreign keys have zero orphan records, including composite-key handling |
| Accounts | Login/User roles agree; existing student/faculty records have matching roles |
| Subtypes | Student academic level and full/part-time subtypes agree; faculty employment subtypes agree |
| Sections | All 6,147 section rows survive schedule joins; course, faculty, room, semester and time-slot references remain valid |
| Course credits | Stored in Course.course_Credits; rebuilt metadata agrees; hidden in schedule display by owner request |
| Semester | Stored in Course_Section.semester_ID; shown once in the table heading |
| Meetings | Rebuilt undergraduate sections use existing NB_AcademicMeeting exact day/time/date pairs; graduate sections retain legacy mappings |
| Source labels | Official term/CRN and source section labels are metadata; links and roster ownership use the numeric local CRN |
| Rosters | Enrollment/history course, semester and grade relationships agree; faculty ownership and deduplication tested |
| Attendance | Registered student, correct course, actual meeting weekday/date, and no future attendance |
| Faculty | Department and semester teaching limits pass; subtype no_of_Classes matches the configured current semester |
| Seats | Rebuilt section capacities are source_JSON.capacity, currently 3–10; legacy capacity remains ten. Stored availability matches capacity minus distinct roster |
| Degrees | All 2,200 undergraduate persisted evaluations match the candidate; all 2,200 live audit views render and agree with the registrar service |

Two issues were corrected: schedule/editor calculations assumed every section
had ten seats, and 50 full-time subtype counters were stale. Capacity now comes
from each rebuilt section's existing metadata. Faculty counts were synchronized
in the backed-up test clone; admin section edits and future full-history rehearsals
maintain those counters transactionally. No identities, course assignments, grades,
meeting patterns, or schema definitions were changed by this repair.

Verification on this repaired V11 state:

- `tests/schema-alignment.php`: 103 checks, zero failures, all tables unchanged by the audit.
- `tests/master-schedule-data.php`: 21 checks.
- `tests/master-catalog.php`: 5,492 checks.
- `tests/academic-portal-http.php`: 807 checks.
- `tests/faculty-rosters-http.php`: 12,437 checks, 6,147 sections, 55,830 distinct roster assignments.
- `tests/academic-portal-assignments.php`: nine checks.
- Persisted evaluations: 2,200 students, 37,823 completed attempts, zero evaluation mismatches.
- Private installation seed export: 25 files / 29 tables reload-verified, including both faculty subtype tables.
- Final full backup restored and all 53 table fingerprints verified in `northbridge_ow_restore_2026_12`.

Schema SHA256: `3bdeb67a0f87cac21c99b76e2bcea201ba613431c70f210a6217b74b2b195d41`.
Trigger SHA256: `5936968154ec1186642c038abdab94675d51db53749f8a719733ac37b608c246`.
Candidate SHA256: `69c1d4099a0788c56cc7e1bf8700d2c0bb32ef0fe6eb8e55d66f679f838e2560`.
The campus overlay and faculty-count repair receipts are also required to identify
this final state; the candidate hash alone does not identify database overlays.

Limits and continuity:

- This uses the existing base schema **and its existing extensions**. The original
  base ERD alone cannot represent exact irregular meetings or versioned program rules.
- The base Course_Section has available_Seats, not a maximum-capacity column.
  The audit preserves that design. Registration triggers enforce stored seats and
  the global ten-seat bound; imports must initialize seats from the actual capacity.
- Account creation remains the documented User/Login workflow. Creating a new
  Student or Faculty account does not provision academic subtypes or assignments;
  their required fields need a separate academic setup workflow.
- Linked lecture/lab registration, shared cross-list capacities, unreviewed section
  restrictions, graduate degree-audit configuration, and disabled dashboard actions
  are not certified as implemented by these checks.
- Repository installation seeds remain an older snapshot. The final V11 seed
  bundle and private backup paths are recorded in PROJECT_HANDOFF.md. Working `_5`
  and its portal configuration were not changed; cutover still needs owner review.

The November 8 source mapping review remains historical evidence. This document
and the handoff describe the later persisted test state.
