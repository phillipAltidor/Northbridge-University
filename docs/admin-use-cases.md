# Remaining Admin use cases

Implementation candidate on `codex/faculty-use-cases`, November 19, 2026.
Source requirements: owner's System Manual, Admin use cases 56–85 and Admin /
Student / Faculty restrictions. Database contract: the GitHub repository's
`database/schema.sql`, existing migrations and `database/LOAD_ORDER.md` seed set.
No substitute academic dataset was created. The local demo imports the repository
snapshot; it does not establish parity with Phillip's running database.

## Manual comparison

| Feature | Manual | Implemented behavior |
|---|---|---|
| Create / update course | 56, Admin specification | Validated catalog fields, department, credits and academic level; protect existing academic records |
| Add prerequisites | 57 | Existing course selection, minimum grade, self-reference and cycle rejection |
| Create / update section | 58–60 | Department membership, full/part-time loads, room capacity, meeting validity, faculty/room/student conflicts; no time changes after start |
| Create / update major and minor | 61–64 | Department-linked records with generated IDs and stale-form protection |
| Create / update major and minor requirements | 65–68 | Required course and minimum grade; create rejects duplicates, update requires existing row |
| Add / update section faculty | 72–73 | Validates instructor replacement; current schema requires a faculty member at section creation |
| Add / drop student | 74–75 | Approved dates, holds, academic level, credits, capacity, previous passes, prerequisites and conflicts; seat counts synchronized |
| Update / correct grade | 76, 85 | Existing academic record, letter grade, required reason, atomic enrollment/history synchronization and audit |
| Declare major / add minor | 77–80 | Add declaration, reject duplicate; existing primary major is retained |
| Assign advisor | 81 | Full-time faculty only, maximum 15 advisees |
| Create / update building, create room | 82–84 | Building linkage, duplicate room-number rejection, matching Lecture/Lab/Office subtype and capacity |

All 24 formerly disabled Admin entries now link to forms. Update Course adds one
extra action explicitly required by the manual. Existing department, user, master
schedule and degree-preview implementations remain separate.

## Dates and permissions

The owner explicitly selected full semester ranges for add/drop, inclusive in
America/New_York: Fall August 24–December 16, 2026 and Spring January 25–May 18,
2027. They are stored in `config/admin-windows.php`; they are an owner decision,
not a claim that SUNY's official registration deadline is the semester end.

Only authenticated Admin identities can open the pages. Write access additionally
requires their ID in private `ADMIN_FEATURE_WRITER_IDS`. Absent/invalid configuration
is read-only. All saves require CSRF and POST. Updates require a server-derived
snapshot version, and use a transaction plus row locks. A dedicated mutex serializes
these new cross-record actions; other legacy writers still require concurrency
integration testing. Every save inserts its before/after records in Admin_Action_Log
in the same transaction. The log stays in the database, never in Git.

`database/migrations/admin_features.sql` is additive (mutex and audit tables).
`tools/enable-admin-demo.php` targets only the named local demo and refuses other
hosts/databases. It has NOT been executed successfully: automatic approval review
failed due to the usage limit. No partner database migration or live writer access
has been changed. Fresh-install tooling includes the migration for future demos.

## Important limits still requiring review

- Reviewed NB academic definitions must remain consistent with degree audits.
  The generic catalog/requirement forms reject changes to those managed records;
  they do not silently overwrite versioned curriculum JSON or flatten alternatives.
  Those changes need the existing academic review process. New legacy records can
  be created and edited. Verify behavior against Phillip's actual export when supplied.
- Versioned registration uses RebuildRegistrar; only satisfied prerequisites permit
  enrollment. Conditional/review-required outcomes remain closed. Latest adapter
  integration has not yet been rerun on MySQL or the private V11 dataset.
- An enrollment with grades/history is not destructively deleted. Registrar
  withdrawal policy is separate. Attendance-only drop policy remains conservative.
- Grade saves update existing Enrollment/Student_History rows and log the reason;
  no term rollover, degree award, transcript-release policy or stored subtype credit
  counter recalculation is performed.
- Major declarations add Student_Major rows; they do not replace Student.major_ID
  or migrate a reviewed NB student profile. Do not claim new primary-major audit
  behavior without that separate integration.
- Cancellation/under-five review and high-importance emails are not gray menu
  actions and are not implemented here. No emails are sent.

## Verification and remaining acceptance

Initial MySQL candidate: 24 assertions passed in a fresh disposable fixture,
covering successful writes across the new actions, atomic audit, room/faculty
conflicts, maximum loads, stale forms, prerequisite cycles, seats, grade correction,
duplicate declarations, hold/date/read-only rejections. The fixture was dropped.
This result predates the latest registration adapter/history changes.

Latest candidate: 25 pure policy/permission/input checks, 50 form states, PHP and
JavaScript navigation checks, 49 PHP syntax checks, diff whitespace checks pass.
Fixture forms are generated under `/private/tmp/northbridge-admin-previews`.
Headless Chrome could not launch under the sandbox, so visual QA is pending.

Pending: apply the local-only migration/allowlist after approval service recovery,
rerun `tests/admin-features.php`, run `tests/admin-features-http.py` and authenticated
save/concurrency tests, inspect desktop/mobile layouts. No final completion claim,
merge, push, remote deployment or partner-database validation has occurred.
