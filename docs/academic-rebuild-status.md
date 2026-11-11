# Undergraduate academic rebuild active locally

As of November 11, 2026, the normal local portal at http://127.0.0.1:8000/
uses **northbridge_ow_rebuild_2026_8**, V11 plus the approved four-building and
faculty-count overlays. The real .env was switched from _5 to _8, and direct
main-port checks passed without a test database override.

Current release: 2,200 undergraduate profiles, 45,821 assignments, 37,823 completed
historical attempts, 5,065 rebuilt undergraduate sections and 9,388 exact meeting
rows across 14 terms. The combined master contains 6,147 sections, including
1,082 preserved graduate sections. Current/planned work remains separate from
completed credit; 64 audits are graduation-ready, not actual graduation awards.

Post-switch checks on main _8: 5,492 master, 908 authenticated portal, 94 degree
HTTP and 12,437 faculty roster checks; all 2,200 persisted degree evaluations
match. Schema (103) and advisor (five) checks pass. Final integrity checks confirm
all 53 active tables match the prepared release and both source _7 and old working
_5 remain unchanged. The schema and triggers were not changed by activation.

## Current operation and rollback

- Main: http://127.0.0.1:8000/ uses _8 through real .env.
- Isolated testing: http://127.0.0.1:8003/ remains on _7.
- The temporary _8 preview on port 8004 was retired after successful cutover.
- Preserved _5 is the immediate rollback connection. It also has a fresh verified
  backup under `/private/tmp/northbridge-rebuild-snapshot-95ab5f19d3e2/`, restored
  into northbridge_ow_restore_2026_15.
- Private applied receipt and post-switch results are under
  `/private/tmp/northbridge-v11-release-20261111/`. Rollback command is documented
  in v11-cutover-plan.md and changes only DB_NAME back to _5.
- Public aggregate result: database/staging/academic-alignment/v11-local-activation-receipt.json.

The release/v11-main branch includes the matching V11 academic installation
bundle, verified from repository SQL in a new _9 fixture. See V11 teammate setup
and the release manifest. Pulling code does not activate a database; the fresh
installer creates only a new target. Release publication/review status is recorded
in PROJECT_HANDOFF.md. No hosted environment was deployed.

The 33 under-five Spring sections remain open under the approved preregistration
policy; their presemester review deadline is unset. Registration/drop/grading
windows and other disabled features remain unimplemented. Current/next registered
undergraduates all have advisors; historical-only advising stays separately scoped.

## Earlier _5 release record

The following is preserved historical context. Its earlier active-database claims,
counts and rollback target do not describe the current V11 working portal.

# Undergraduate academic rebuild — active locally

**Current qualification:** the checks below establish technical consistency,
not sensible individual course planning. The owner subsequently found redundant
mathematics and poor major progression. Active data has not been replaced during
this review. A replacement is blocked from activation until its academic-purpose
and timetable checks both pass; see [planning review](academic-planning-review.md).

The local portal now uses `northbridge_ow_rebuild_2026_5`. The original
`northbridge_university` database remains unchanged and available for rollback.
The owner authorized this replacement of fictional undergraduate academics.

## Active data and verification

- 12 undergraduate majors and 12 minors, with reviewed concentrations and
  2026–2027 program definitions.
- 2,200 undergraduate profiles and 42,334 course attempts, including 34,682
  completed attempts. Current and planned work has no final grade.
- All 14 semester timetables passed independent checks: 4,801 sections and
  7,875 exact timed meeting rows, with fictional faculty and rooms.
- Official CRNs remain term-scoped source identifiers. Parallel Northbridge
  delivery groups have unique local keys. Borrowed historical-version meeting
  templates are labeled conversions, without invented official CRNs.
- All 42,334 PHP prerequisite results matched the independent Python review.
  No known prerequisite-order errors or unresolved used-course source conditions
  remain in the approved fictional candidate. Conditional future requirements
  remain conditional, never earned credits or unconditional authorization.
- All 2,200 database-backed degree evaluations match the offline evaluations.
  This validates accurate progress and unmet requirements; it does not declare
  every student eligible to graduate. No student is currently certified ready.
- 476 authenticated page checks passed for all 12 majors and four account roles.
  Nine additional assignment/meeting guard checks passed without database writes.
- 20 installation files were exported; 24 table exports passed reload checks.
- The final database was backed up and restored separately, with all 53 tables
  matching. Receipt files identify the private snapshots without exposing data.

## Preserved data and explicit project policies

People, accounts, fictional faculty appointments, rooms and graduate academics
are retained. Historical inactive accounts are not reactivated. Old undergraduate
attendance is archived with the original database; converted attendance was not
fabricated. Legacy course meanings needed by preserved graduate records remain
stored, but no active undergraduate registration uses an unversioned legacy
course. New program, course, prerequisite and exact-meeting tables are authoritative.

The previously approved eight curriculum decisions remain. Additional delegated
Northbridge decisions are recorded in `rebuild_project_policies.py`: ED4220 uses
ED4200 before/concurrently; PH3800 requires completed Composition I and II at C;
and the historical ED5925 retains its readable prerequisites plus an explicit
local review of the undefined ED400 reference. These are project exceptions,
not claims of official SUNY corrections or transcript equivalencies.

Placement, novice-language, permission and admission records generated for the
fictional rebuild are explicitly marked simulations. They are not Old Westbury
approvals or signatures. Admission academic conditions are checked independently.
No placement scores, real fieldwork, or transfer equivalencies were invented.
Degree requirements needing advisor plans or fieldwork remain reviewable until
proper scoped evidence exists. Six unused catalog entries with unestablished
approved credits remain quarantined; no active attempt uses them.

The registrar adapter reads actual database histories. It excludes future work
from degree completion, handles minimum grades and conditional prerequisites,
and checks minor overlap using the evaluated major allocation. It restores the
reviewed curriculum path order after MySQL JSON serialization so partial audits
remain deterministic. Existing unimplemented registration pages remain disabled;
the prerequisite service is available to future registration workflows.

## Operation and rollback

The normal portal is `http://127.0.0.1:8000/`. Its `.env` selects the rebuilt
local database. Roll back by changing only `DB_NAME` to `northbridge_university`;
new requests then use the untouched original database. Do not restore the whole
private `.env` backup if unrelated settings have since changed. A private copy
of the original configuration is recorded in `portal-activation-receipt.json`.

Public aggregate receipts are under `database/staging/academic-alignment/`:
`portal-activation-receipt.json`, `portal-degree-verification.json`,
`portal-http-verification.json`, `portal-seed-verification.json`, and
`restore-rehearsal-receipt.json`. Earlier blocked-candidate reports document
historical rehearsals; the final activation receipt and its referenced checks
identify the active dataset. SQL archives and student-level reports stay private.
