# Northbridge University project handoff

Last updated: **2026-11-11**, America/New_York.

Use this document as the continuation prompt for work on this project. Read the
current code, relevant project documentation, System Manual, and schema before
making changes. Do not assume previous work is complete or academically approved.
Follow the maintenance instructions in `AGENTS.md` and keep this file current.

## Latest continuation checkpoint — read first

**V11 RELEASE BRANCH PUSHED; REVIEW PR OPEN, 2026-11-11.** Owner approved the
reviewed publication plan. Commit **30abcda** on **release/v11-main** contains
40 changed files: matching 25-file / 29-table fictional academic seed package,
clean installer/manifest, activation tooling, tests, setup docs and handoff.
Pushed successfully to origin/release/v11-main; branch tracks that remote.
**Review PR #5:** https://github.com/phillipAltidor/Northbridge-University/pull/5
Head release/v11-main → base main; **not merged**. Origin/main was unchanged
at de8520f during publication. This entry is in a follow-up documentation commit
so it records observed push/PR creation rather than predicting success.

Final staged review: all **52 installation input hashes** match staged Git blob
bytes, no excluded private paths staged, zero secret-pattern matches in 40 new/
modified files. Full staged diff check passes. Fresh _9 install and actual page
checks described below apply to this shared package: 53 tables/76 FKs, all 29
academic table fingerprints equal active _8; 5,492 master / 908 portal / 94 degree
HTTP / 12,437 roster checks; all 2,200 degree evaluations match. 103 schema / five
advisor / 14 cutover / three installer negative cases pass; all 132 PHP files
pass syntax. Existing account seeds unchanged. SQL/trigger LF Git attributes
preserve hashes across checkout. MySQL 9.6.0 verified; other engines unverified.

New teammate guide: **docs/v11-teammate-setup.md**. Use the release branch until
review/merge, then pull main. Installer creates a new DB only, keeps FKs on,
loads rosters before triggers, verifies the full bundle, and never alters .env.
Existing populated databases must not be overwritten. Match this package rather
than use the older hardcoded timeline/master-schedule repair scripts.

Main **_8/8000** stayed running/unchanged; isolated **_7/8003** stayed unchanged.
Fresh-install fixture **_9** retained; temporary _9/8005 server retired after tests.
Private backups, credentials, candidate/debug records and .env were not published.
No remote hosted deployment or automatic PR merge. Actual local cutover had
already completed before this publication. Next owner/team action is PR review
and merge; feature branches follow the documented workflow. Handoff maintained
for this request, including this final publication record.

**V11 release package VERIFIED; publishing approved branch next, 2026-11-11.**
Owner approved the reviewed branch plan with “ok lets do that”. Branch is
**release/v11-main**, based on main de8520f; fetched origin with no incoming
changes. Main branch is not being merged or force-pushed. This supersedes the
older paused-publication and older-repository-seeds notes below.

Repository now contains the exact **25 V11 academic seed files / 29 tables**,
including Faculty_Department, FT/PT faculty counts and the four-building Room/
Building overlay. Existing User/Login/account seeds were not regenerated. Old
repo copies retained privately at
`/private/tmp/northbridge-pre-v11-repository-seeds-qb5z5qa1/`.
New manifest and aggregate verification receipts are under `database/releases/v11/`.
The manifest binds all 51 load-order files plus trigger source to SHA256 hashes.

**Fresh installation from repository SQL passed** into disposable **_9**:
53 schema tables, 76 FKs, all 29 academic tables exactly match active _8; schema
and trigger hashes match the reviewed definitions. Active _8 and real .env stayed
unchanged. Operational log/reset rows are intentionally not exported. Fresh
_9/8005 actual page checks: 5,492 master, 908 portal, 94 degree HTTP, 12,437 faculty
roster; all 2,200 persisted degree evaluations match, zero mismatches. 103 schema
and five advisor checks also pass. Fourteen cutover guards and three installer
negative cases (reserved target, existing target, tampered input) pass; input probe
restored and all file hashes rechecked. 132 PHP files pass syntax. Staged secret-
pattern scan of new/modified files found zero matches; .env/backups stay local.
Temporary _9/8005 preview will be retired after validation; keep fixture DB _9.

Added `tools/install-v11-development.php`: fresh local target only, preflight by
default, --create explicit, FKs stay on, trigger installation follows seed rosters,
never overwrites existing DB or changes .env. No private candidate or Python
planner needed for teammates. Updated LOAD_ORDER to bypass older hardcoded legacy
repair scripts. Added `docs/v11-teammate-setup.md`; updated README/team workflow/
rebuild status. Git LF attributes protect SQL/trigger file hashes across platforms.
Path normalization supports Windows path separators; actual tests were on this
host using MySQL 9.6.0. Other engines/versions are not certified. No hosted deploy.

Commit/push/PR results are **not yet observed** at this checkpoint. Next execute
staged review, commit and push release/v11-main, create a review PR if GitHub auth
permits, then record actual publication. Do not merge main without review. App
main remains _8/8000 and isolated testing _7/8003; no database activation happened
as part of this packaging request. Handoff maintained after this request.

**Release branch publication authorized/in progress, 2026-11-11.** Owner said
“ok lets do that” to the reviewed branch plan. Created **release/v11-main** from
main de8520f after fetching origin (no incoming commits). Keep origin/main
unchanged; publish this branch for review rather than merge automatically.
This supersedes the preceding push pause.

Copied the exact **25 verified V11 SQL files / 29 academic tables** into repo
seeds; existing account/credential seeds unchanged. Old repository seed copies
privately preserved at `/private/tmp/northbridge-pre-v11-repository-seeds-qb5z5qa1/`.
Added `database/releases/v11/manifest.json` binding all 51 schema/load files plus
trigger source hashes and expected schema/trigger/table fingerprints. Added
`tools/install-v11-development.php`: connects to local MySQL without requiring
an existing DB, refuses existing/reserved/current targets, keeps FKs enabled,
creates a new target only, strips the old USE header in memory, loads existing
schemas/seeds and installs seat triggers after rosters. .env is never changed.

**Fresh repository install _9 verified** on MySQL 9.6.0: 53 tables, 76 FKs, all
29 academic table fingerprints equal active _8, schema/triggers unchanged.
Actual fresh-install HTTP _9/8005 checks pass: 5,492 master, 908 portal, 94 degree
HTTP, 12,437 faculty roster; all 2,200 persisted degree evaluations match. Also
103 schema / five advisor / 14 cutover guard checks pass. Main _8 and real .env
unchanged. These validate the newly shared seed package, not just private exports.
Fresh receipt is `database/releases/v11/clean-install-verification.json`; private
HTTP evidence `/private/tmp/northbridge-v11-fresh-install-http-20261111/`.

Updated LOAD_ORDER, README, team workflow, current rebuild status and V11 teammate
guide. SQL/trigger LF Git attributes protect input hashes across checkouts. Windows
path normalization is included, but tests ran on this host with MySQL 9.6.0;
MariaDB/other server versions are not certified. No external hosting deployment.
Final installer negative guards, full syntax/staged review and publication are
still in progress. Do not call this branch pushed or merged yet.

**GitHub push PAUSED for plan review, 2026-11-11.** Owner said “before we push
show me how you going to setup the push.” Do not stage/commit/push the current
activation/package work until this review request is answered and owner directs
continuation. No new packaging or seed replacement has occurred in this request;
current changes remain local and the main database is still verified _8.

Proposed publication: a dedicated `release/v11-main` branch from refreshed main,
with the activated V11 tooling/tests, matching 25-file / 29-table fictional
academic installation bundle, clean-install verification and teammate setup docs.
Verify a new disposable install before committing; do not overwrite existing
teammate databases or silently activate their .env. Keep schema definitions
unchanged. Existing local _8/_7/_5 and private credentials/dumps stay untouched.
Push that release branch for owner/team review, then merge into main after review;
feature work uses separate per-feature branches. This is a proposal, not a branch
or PR already created. The previous published main remains de8520f.

Only fictional shared academic seed data is proposed for publication. No .env,
private SQL dumps, real source faculty/student identities, credentials or private
student-level planning/debug artifacts are included. Existing account seed files
are not regenerated. Update handoff after every request and retain accurate
prepared/tested/published distinctions. Handoff update is the only edit made for
this plan-review request.

**LOCAL MAIN CUTOVER COMPLETE AND VERIFIED, 2026-11-11.** Owner replied
“ok whats next?” to the explicit final permission question for _5 → _8; treated
“ok” as assent and performed the concrete local cutover. No new visual owner
review is claimed. **Real .env now selects `northbridge_ow_rebuild_2026_8`**.
Normal main portal **http://127.0.0.1:8000/** runs without a test DB override.
No listener existed on 8000 before activation, so main requests were drained.
Revalidated exact approved plan a3f7dfdbb048dad8abc6514b0ab81ee3a7f58aa22cd1e784895d175823769e88.
Refreshed working _5 backup immediately before switch, then used the guarded apply
with explicit drained-write assertion. Only DB_NAME changed; no database record,
schema, trigger, password or unrelated environment setting was altered.

Fresh rollback snapshot: `/private/tmp/northbridge-rebuild-snapshot-95ab5f19d3e2/`,
restored and all 53 tables verified in **`northbridge_ow_restore_2026_15`**.
Dump SHA256 f2fa5925b833d3db6546d5c44dbf999d8499f89824d5f08edab6e48f8f077ec5.
Old **_5 remains unchanged** for rollback; original northbridge_university and
older restore copies remain intact. **_7 remains unchanged** for isolated testing
on port8003. The temporary _8 preview on port8004 was stopped after main tests
passed; _8 is now working data, not a disposable test target.

**Actual post-switch main-port results**, with no DB override: 5,492 master
checks, 908 authenticated portal checks, 94 degree HTTP checks, 12,437 faculty
roster checks. All 2,200 persisted degree evaluations match V11, 37,823 completed
attempts, zero evaluation mismatches; 64 audits graduation-ready, not awards.
103 schema and five advisor checks pass on actual configured _8. Final integrity
check proves all 53 active tables equal the prepared release and source _7 / old
working _5 equal their preserved snapshots. This is verified actual activation,
not the earlier private-file rehearsal. Runtime hash remains b05bee9b802d24b272df39f8c5cd8d658f285326b9fb34b3ba76c7d420821fa4.

Private applied receipt:
`/private/tmp/northbridge-v11-release-20261111/activation-receipt.json`
(postSwitchChecks=passed). Actual main test evidence and final proof are in its
`post-switch/` directory. Public aggregate result:
`database/staging/academic-alignment/v11-local-activation-receipt.json`.
Updated academic-rebuild-status, cutover plan and teammate workflow to distinguish
current activation from historical _5 records. Rollback CLI remains
`php tools/activate-v11-release.php --rollback-receipt /private/tmp/northbridge-v11-release-20261111/activation-receipt.json`;
it preserves current unrelated .env settings. Stop/drain requests for a rollback.

**Next separate task:** synchronize/reload-verify the exact V11 installation
package and teammate setup, then commit/push that checkpoint. Repo seeds remain
the older snapshot and were not replaced by switching local DB_NAME. New activation
code/test/docs and current handoff changes remain local/uncommitted/unpushed.
No remote host deployment. Previously disabled workflows and calendar/review
window decisions remain unchanged. Handoff updated for this request.

**Local main cutover authorized/started, 2026-11-11.** In response to the explicit
final question asking permission to switch `_5` → `_8`, the owner replied
“ok whats next?”. Taking “ok” as assent to that concrete local switch and
continuing without asking the same approval again. Do not claim a new visual
faculty/admin review was observed. Scope is the reviewed local main connection;
no remote hosting/deployment or schema modification is authorized by this reply.

Rerunning exact preflight, refreshing the working rollback snapshot, draining
main requests, then applying and verifying normal main-port routing. Initial
inspection found no listener on main port 8000, so main is not currently serving
requests. Current configuration remains _5 until the guarded apply succeeds.
Post-switch tests and actual results remain pending; do not call cutover complete
until they pass. Handoff maintained for this request.

**V11 activation preparation/rehearsal COMPLETE; final switch pending, 2026-11-11.**
Owner authorized updating and rehearsing the process. New version-specific
`tools/activate-v11-release.php` is implemented alongside the untouched older
activation tool; `includes/release-cutover.php` supplies guarded DB_NAME-only
atomic switches and rollback. Actual `.env` still selects **`_5`**. Neither actual
main switching nor post-main-switch tests occurred. No cutover approval inferred.

Prepared working release **`_8`** from source `_7`; verified all 53 tables and
schema/triggers. Release restore **`_13`** matches. Copy backup:
`/private/tmp/northbridge-rebuild-snapshot-6a75f6ae39d4/`; release restore backup:
`/private/tmp/northbridge-rebuild-snapshot-7f33d0b4483e/`. Current working `_5`
backed up/restored to **`_14`**, backup:
`/private/tmp/northbridge-rebuild-snapshot-1071d2a0c78d/` (dump SHA
f2fa5925b833d3db6546d5c44dbf999d8499f89824d5f08edab6e48f8f077ec5).
All existing source/main databases and repository seeds remain unchanged.

Direct **_8 / port 8004** checks pass with runtime-bound evidence: 5,492 master,
908 portal, 94 degree HTTP, 12,437 faculty roster checks; 2,200 persisted degree
evaluations match V11. Also 103 schema and five advisor checks pass. Fourteen
configuration/preview-pair checks pass; two actual CLI apply-negative tests
(unapproved plan / missing working backup) reject with real .env unchanged.
131 PHP files pass syntax. Final activation script syntax/diff checks pass.
Temporary private .env switch/rollback rehearsal passes byte-for-byte restoration.
These are pre-activation results, not proof of main-port routing after a switch.

Preflight checks exact candidate/schedule/campus/count/25 private seed hashes,
source/copy/restore equality, schema/triggers, source-working baseline drift,
protected identities/graduate records and runtime-bound direct evidence.
It rejects any unreviewed working `_5` changes since the original rebuild source,
not just protected account changes. Apply requires the exact approved plan hash,
restored current-working backup <1 hour old, and explicitly drained web writes.
The drain flag is an **operator assertion**: stop/drain main requests first;
there is no automatic maintenance mode. Refresh backup if it expires before
approval. Actual apply failures during drift/receipt persistence roll DB_NAME back.
Rollback preserves unrelated current .env settings. No schema/record mutation.

Private release evidence: `/private/tmp/northbridge-v11-release-20261111/`.
**Final prepared plan hash:** `a3f7dfdbb048dad8abc6514b0ab81ee3a7f58aa22cd1e784895d175823769e88`.
Runtime SHA256 b05bee9b802d24b272df39f8c5cd8d658f285326b9fb34b3ba76c7d420821fa4.
Public aggregate receipt: `database/staging/academic-alignment/v11-release-preflight.json`.
`docs/v11-cutover-plan.md` contains exact preflight/apply/rollback and post-switch
controls. Source/copy restore dump remains the verified V11 hash 5a8a07aa...eca3a8a.

**Final review/approval needed:** owner to review Faculty/Admin on
http://127.0.0.1:8004/ and approve local main connection **`_5` → `_8`**. This is the
standing final cutover gate, not another approval for the completed rehearsal.
After approval: drain main, refresh/check working backup, rerun preflight, apply,
restart normal main server without test override, run main-port role/roster/seat/
audit checks, and record verified activation. Main smoke mode is guarded to actual
.env selecting _8; save those receipts separately. Keep _7 for future isolated
testing and _5 for rollback. Synchronizing approved repo installation seeds and
teammate setup still remains a separate post-approval task. Calendar windows and
under-five review deadline are still unset; their inactive workflows stay inactive.
New tooling/test/docs changes are local and uncommitted/unpushed in this request.
Handoff updated with concrete reviewable result before final switch permission.

**V11 activation preparation authorized/in progress, 2026-11-11.** Owner said
“ok do that” to updating and rehearsing activation. Current scope is preparation
and isolated rehearsal, not switching main before the final concrete review.
Created dedicated release **`northbridge_ow_rebuild_2026_8`**, exact 53-table copy
of `_7`. Copy backup `/private/tmp/northbridge-rebuild-snapshot-6a75f6ae39d4/`.
Release restoration into **`northbridge_ow_restore_2026_13`** passed all 53 tables;
backup `/private/tmp/northbridge-rebuild-snapshot-7f33d0b4483e/`. Both dump hashes
match the existing verified V11 final snapshot. Preview for release _8 is local
port **8004**; `_7`/8003 and main `_5` remain unchanged.

Added separate version-specific `tools/activate-v11-release.php` and shared
`includes/release-cutover.php`; retained the earlier activation tool and guards.
The new path checks exact V11/overlay/seed hashes, source/copy/restore equality,
schema/triggers, protected working data, direct _8 evidence and runtime identity.
Environment helper changes only DB_NAME, detects stale/duplicate settings,
preserves permissions/unrelated values, and supports rollback; ten pure guard/
rollback checks pass. Apply requires an exact approved plan hash, fresh restored
working backup and explicit drained-write assertion; it has not been run.
Snapshot tool now optionally keeps receipts in a private directory. Direct test
pairs _7/8003 and _8/8004 are explicitly guarded; tests are not relabeled.

Initial direct _8 tests pass: 5,492 master, 908 portal, 94 degree HTTP, 12,437
faculty roster checks; all 2,200 persisted degree evaluations match; 103 schema
and five advisor checks pass. Regenerating those receipts with exact runtime
hash binding before final preflight. A fresh _5 backup/restore _14 is in progress.
Private release evidence is `/private/tmp/northbridge-v11-release-20261111/`.
No real .env change, working cutover, schema change, repository seed replacement,
commit or push in this preparation request yet. Final results remain pending.

**Concrete V11 cutover plan prepared, 2026-11-11.** Owner asks what is needed to
make the verified database the main one. Inspected current configuration and
activation implementation: `.env` still selects `_5`. The existing
`tools/activate-academic-portal.php` expects original northbridge_university,
older receipts/4,801 sections and unchanged campus/appointments, so it is **not a
valid V11 activation path**. Do not weaken its checks or run it as-is. It needs a
version-specific guarded preflight/dry run for full-history/campus/count overlays.

Fresh read-only release checks passed: all **25 private V11 seed file hashes**
match their receipt; all **53 tables in `_7` and restore `_12`** still match the
final restore fingerprint receipt. Candidate and schedule hashes remain the
verified V11 values. No databases/configuration/seeds changed and no activation
script was executed. These checks establish intact release assets, not an
activation approval or a passing test of an as-yet uncreated working copy.

Prepared `docs/v11-cutover-plan.md`: update activation guards; prepare a dedicated
release clone with exact table/trigger equality; complete final faculty/admin
review; present the concrete cutover plan for approval after reversible prep;
freeze writes, freshly back up/restore-check `_5` and compare account/resource
drift immediately before switching; change the main connection and perform role/
seat/roster/audit smoke checks with rollback; synchronize approved install seeds
and teammate instructions separately. Keep `_7` isolated for future testing and
`_5` untouched for rollback. No shared hosted target was specified; this plan
covers the local working portal. User's “what do we need” is planning, not actual
cutover permission. Deadline/windows remain unresolved and their writes disabled.
Handoff and plan updated for this request. No new code to test; diff check passes.

**GitHub source checkpoint PUBLISHED, 2026-11-11.** The owner-authorized project
checkpoint commit **edd95e6** (411 files) was pushed successfully to **origin/main**
at `https://github.com/phillipAltidor/Northbridge-University`. Git reported
16a3273..edd95e6 main -> main. This supersedes the earlier pending-publication
notes below. This entry is maintained in a follow-up documentation commit so the
handoff records the observed push result instead of a planned success.

The checkpoint includes current portal code, reviewed academic source/rule
material, tests, existing fictional repository installation seeds, handoff and
team workflow. It **does not include or activate the exact private V11 preview
database**. The reviewed preview remains `_7`; working `_5` remains unchanged.
Repository seeds are still the older documented snapshot. No private .env,
credentials, local caches, vendor directory or /private/tmp datasets were staged.
Git attributes preserve archival PDF/HTML bytes and generated SQL EOF layout;
full staged diff check passed after those classifications, with source/seed
hashes preserved. Fresh validation results are recorded in the earlier database/
GitHub clarification entry below (127 PHP syntax, relevant units, 103 schema and
five advisor checks). No additional functional changes followed those tests.

`docs/team-workflow.md` explains pull main, one branch per feature, coordinate
shared files, PR review/merge, private local environment/databases, and continued
handoff maintenance. README links it and earlier setup notes warn against treating
old migration instructions as V11 activation instructions. GitHub publication is
complete for the application checkpoint. Database promotion still requires the
separate guarded activation/review steps and explicit owner cutover authorization;
“should we move” has been answered as a recommendation, not executed as a switch.

**Working database promotion recommendation, 2026-11-11.** Owner asked whether
to move the Old Westbury-based test data into the real database. Recommended
promoting the exact verified `_7` V11 + campus/count state to the working baseline
through a guarded cutover, with fresh working backup, reviewed faculty/admin views,
matching install data, post-switch checks and rollback. This is advice in response
to “should we”, **not approval to perform the switch**. No database was moved,
overwritten, activated, or reconfigured. GitHub publication remains a separate
source-code task and cannot deliver the private preview database by itself.

The authorized GitHub checkpoint is staged (410 files before this note and Git
attributes), but not yet committed/pushed. Full staged diff review exposed
whitespace warnings in unchanged archived PDF/HTML source bytes and a generated
SQL EOF blank line. Preserve source/hash fidelity; classify archives appropriately
and rerun the staged check rather than rewriting archived evidence or seed hashes.
The earlier successful diff checks did not include these then-untracked archives.
No functional test failure was found. Keep database-cutover approval and source
publication status distinct and update this handoff after each request.

**Database / GitHub status clarified, 2026-11-11.** Owner asked whether the
project is still using the test database. Yes: the reviewed preview on port
8003 remains V11 plus campus/count overlays in `northbridge_ow_rebuild_2026_7`;
the separate working portal remains `_5`, with no cutover approved or performed.
Source publication does not activate a database. The previous GitHub request was
interrupted after preparation/verification, **before any commit or push**. Keep
that authorized task pending; do not claim publication is complete.

Prepared `docs/team-workflow.md`, README/setup notices distinguishing the private
V11 preview from older repository seeds, and Python cache/venv ignore rules.
Fetched origin: local main and origin/main matched at 16a3273 at that check.
Untracked/modified text secret-pattern review found no secret matches; official
section extracts omit personal source fields. Fresh checks passed: 127 PHP syntax
checks, schedule grouping and role navigation, 3 minimum-review / 11 meeting /
15 planning tests, 8 placement / 37 math checks, 103 `_7` schema checks and five
advisor readiness checks; all database records unchanged. Files remain uncommitted.
No schema/data/config changes or database activation resulted from the GitHub
preparation or this clarification. Handoff updated for this request.

**Schedule display correction implemented/tested, 2026-11-11.** Owner revised
prior display instructions: **master schedule must show Total Seats and Available
Seats**, student schedule columns remain approved, and Section must be a plain
number (1, 2, 3) rather than NB-003. This supersedes earlier master-table seat
column omissions and NB-prefixed section-label decisions. Student/faculty semester
columns stay unchanged; all schedule/roster section labels now use plain numeric
section_No. NB-prefixed custom **CRNs** remain distinct, truthful source labels;
only section-number formatting changed. No relational identifiers were changed.

Rechecked live `_7` SHOW COLUMNS and repository schema: Course_Section has integer
section_No and stored available_Seats, but **no separate Total Seats column**.
Master Total Seats uses the existing per-section source_JSON.capacity (legacy ten)
and availability uses the audited capacity-minus-distinct-roster calculation.
Shared schedule renderer has an opt-in seat-column flag used by master only;
columns are CRN, Course, Course Name, Section, Professor, Days, Time, Building / Room,
Total Seats, Available Seats, then editable Action. No Semester/Credits columns.
Source-label helper also emits plain numbers for roster selectors/captions.

On V11 + campus/count overlays in `_7` / port 8003, current read-only tests pass:
**5,492** master checks (both terms/all levels/all pages verify seat values and
schema section numbers), **908** portal checks (student headers unchanged and
actual section cells numeric), **12,437** faculty roster/profile checks. All table
checksums unchanged. Changed PHP files pass syntax and git diff --check passes.
Updated `docs/semester-schedule-layout.md` and `docs/master-schedule.md`.
Private V11 HTTP/master/roster receipts refreshed for this display revision;
older test counts below remain historical. No schema/data/seed/config changes,
no fresh browser visual inspection, no working cutover. Handoff updated for this
request. Readiness/calendar work below remains pending independently.

**Minimum enrollment approach APPROVED; advisor review passed, 2026-11-09.**
Owner answered “ok lets do that” to keeping underfilled Spring sections open
through preregistration and enforcing the manual's five-student minimum at an
agreed presemester review deadline. This supersedes the earlier pending-policy
notes below. Exact deadline/semester/registration/grading dates remain unresolved;
no automatic review, cancellation, notification, background task or current roster
changes were implemented. Keep the 51-move proposal unapplied as a contingency.
This approval does not authorize working cutover or notification sending.

Continued one item at a time with read-only undergraduate advisor coverage.
**Important refinement:** all **1,140** undergraduates registered for current
Fall 2026 or next Spring 2027 already have advisors. All **1,000** undergraduates
without an Advisor row have no current/next registrations and profile
activeOriginalCohort=false. Another 60 historical-only undergraduates do retain
advisors. No-registration/profile flags do NOT prove formal inactive status,
graduation, or permission to delete/archive records. Thus the raw missing-advisor
count is **not an active-registration coverage blocker**. Historical-record
advisor policy remains a separate question; 738 preserved graduates without
advisors remain outside the undergraduate repair scope.

Existing undergraduate advisors all belong to the student's primary-major
department. Existing assignments satisfy full-time-only and max-15 advisee rules
across all students. Aggregate staffing: 250 FT / 100 PT; 2,150 unused FT advisee
places university-wide (not a guarantee of capacity in each department).
No advisor assignments were created, removed, or changed.
`tests/advisor-readiness.php` passes five checks on V11 + campus/count overlays
in `_7`: current/next coverage, advisor eligibility, advisee caps, primary-major
department compatibility, and all 53 table checksums unchanged. Department
compatibility is a project consistency check, not a newly quoted manual rule.
PHP syntax and diff checks pass; this request changed only tests/documentation.
See `docs/advisor-readiness-review.md`. Next readiness item is defining the
calendar/windows and minimum-enrollment review deadline; sensitive writes remain
unimplemented/disabled until their policy is defined. Schema/database/seeds/
configuration and working `_5` unchanged. Handoff updated for this request.

**Earlier recommendation (now approved above), 2026-11-09.** Owner
answered “IDK yet what do you think?” rather than selecting a timing policy.
Recommended keeping underfilled Spring sections open during preregistration,
then enforcing the manual's five-student minimum at a defined presemester review
deadline. Below-five enrollment during preregistration alone does not establish
invalid data or require immediate student course changes. At review, assess
compatible same-course consolidation, protect degree progress and lecture/lab
pairing, and follow the manual's cancellation/notification requirement for
remaining under-five sections. Notifications are a future workflow; no messages
are authorized or sent by this recommendation. Do not invent additional students
or filler coursework. Retain the staged 51-move proposal as a contingency; do not
apply it now. This is an assistant recommendation, **not owner-approved policy**.
The exact review deadline/start boundaries remain unresolved. This open policy
item does not itself require stopping independent read-only feature development;
calendar-sensitive cancellation/registration writes need defined boundaries.
Handoff and review notes updated for this request; no code/data/config changes,
no new tests required for documentation-only updates, and no working cutover.

**One-at-a-time readiness work, 2026-11-09 — first item under review.** Owner
approved proceeding one item at a time and reiterated that PROJECT_HANDOFF.md
must be updated after **each request**. Preserve this standing instruction.
Started with the 33 upcoming under-five sections; did not start advisor changes.
All **439 Spring V11** sections match live `_7` source metadata and enrollment
counts. Classification: **17 sections / 17 courses** have sufficient same-course
demand for existing-section rebalancing; **13 sections / 12 courses** have fewer
than five students in the whole course; **three sections / two courses** (ED4220,
CS2000) need consolidation or plan review. No Spring source capacity is below five.

Added `tools/review-section-minimums.py`: stages only same-course assignments with
capacity bounds, fixed-course and simultaneous cross-course time conflict checks,
and independent membership/conflict validation. A FEASIBLE (not proven minimum
moves) proposal resolves the first **17 sections with 51 roster moves**, leaving
16 underfilled sections. No additions, cancellations, course-choice/credit changes,
faculty/room/meeting changes, or database writes. Staged schedule is explicitly
marked awaiting full validation and must not be loaded using existing receipts.
Three focused regression tests pass; Python syntax and diff checks pass. Initial
syntax check hit a host-cache sandbox restriction; permitted temporary cache rerun
passed. No database/schema/seed/config changes.

Current private proposal `/private/tmp/northbridge-spring-minimum-review-20261109-v2/`;
parent schedule hash `c814f689766d079905ac8ab13974e0959598a9fa851183822994bd4a5c3a192b`;
proposal hash `72b19b9659ce13fce5e8a6ec9f13b83d9cb52fdd526d3515d165d5418867c5b3`.
Non-v2 exploratory output is superseded. Sanitized report:
`docs/spring-minimum-enrollment-review.md` and
`database/staging/academic-alignment/spring-minimum-enrollment-review.json`.
BS2410/BS2411 lecture/lab pairing must survive any future replanning.

**Earlier policy question (answered by approval above):** asked whether to require every Spring section
reach five now (needs academic replanning for genuinely low-demand courses), or
retain underfilled preregistration until an approved presemester review deadline.
The manual gives no date; this choice materially changes student course plans.
Do not infer an answer from elapsed time, treat a feasible proposal as applied, or
mark the first readiness item complete. Full migration checks and fresh backup
remain required before applying any eventual repair. No cutover authorization.

**Activation readiness / manual review, 2026-11-09 — newly requested.** Owner
wants a stable data foundation for new features and asks how to move the approved
data out of testing. This is a readiness/planning request, not permission to switch
working `_5`. Read `/Users/phillipaltidor/Downloads/System Manual.docx` requirements
and relevant use cases (read-only OOXML extraction; no manual edits). Technical
schema checks below do **not** establish complete manual compliance.

New aggregate-only `_7` checks: **33 Spring 2027 undergraduate sections have fewer
than five registrations**; none of those upcoming rebuilt capacities is below
five. Manual Admin requirement says cancel under-five sections before semester
start and notify students. Registration cutoff/start dates are not supplied, so
these are readiness review items, not proof that preregistration already violated
a deadline. Do not cancel/move rosters automatically. **1,000 undergraduates lack
an Advisor row**, plus 738 preserved graduates (1,738 total). Existing advisor
assignments satisfy the maximum 15 / no part-time-faculty rules; all section faculty
belong to the course department. Missing advisor coverage is an academic setup gap
for future advisor/advisee features; manual does not explicitly say every student
must already have an advisor. Do not fabricate assignments as part of a read-only
review. Graduate scope remains protected.

To exit testing: finish the manual-to-data/workflow rule register and resolve
applicable baseline gaps; confirm semester/registration/grading/cancellation
boundaries before enabling time-sensitive writes; prepare and test a guarded
activation path for exact V11 + campus/count overlays; bind matching private seeds,
back up `_5` fresh, obtain final owner cutover approval, switch and smoke-check
roles/rosters/audits with rollback ready. Existing inactive features can remain
inactive; implementing every manual use case is not a prerequisite to adopting a
verified baseline. Records remain fictional project records after activation.
No database/schema/config changes were made in this manual readiness review.

**Schema-wide audit completed for implemented V11 portal, 2026-11-09.**
`tests/schema-alignment.php` passes **103 checks**, covering all **53 tables**,
**76 foreign keys**, **6,147 sections**, account roles and academic/employment
subtypes, semester/course/history relationships, exact attendance dates, faculty
appointment/workload limits, source metadata, capacities and stored availability.
All audit checks are read-only and publish aggregate counts only. Schema and
trigger hashes still match the original verified values below; no schema changes.
See `docs/schema-alignment-audit.md` for mappings, results and implementation limits.

Two real mismatches were fixed: shared/faculty/editor code assumed every section
had ten seats despite rebuilt source capacities of **3–10**; **50 full-time faculty**
subtype `no_of_Classes` counters were stale. Readers/editors now use existing
source_JSON.capacity; legacy sections retain ten. Faculty counts now match the
configured current semester, including preserved graduate assignments. Guarded
DML synchronized only the counters and the rebuild audit marker in `_7`; `_5`,
identities, academic attempts, rosters, assignments, meetings and schema stayed
unchanged. Admin edits and future full-history loads maintain these counters in
transaction; the helper's stale-count correction, idempotence and rollback pass.
Full-history loader maintenance is code-reviewed/syntax-checked and uses the tested
helper; a new destructive full-history load was not run during this audit.

Verification on **V11 + campus overlay + faculty-count repair in `_7`**:
103 schema checks, 21 master data checks, 5,492 master catalog checks, 807 portal
HTTP checks, 12,437 faculty roster/profile checks, nine assignment guards; all
2,200 persisted degree evaluations match and all 2,200 live audits render/agree.
PHP syntax and diff checks pass. Existing disabled features, graduate degree-audit
configuration, linked lecture/lab enrollment and shared cross-list capacity are
not certified as implemented. New Student/Faculty account creation remains the
documented User/Login-only workflow; academic provisioning is deferred.

Private evidence: `/private/tmp/northbridge-history-20261109-v11/` now contains
`schema-alignment-verification.json`, `faculty-class-count-sync.json`, matching
updated private seeds and `portal-seed-verification.json`. **25 files / 29 tables**
reload-verified, including Full_Time_Faculty and Part_Time_Faculty. Export refuses
stale faculty counters and records the count-repair overlay/current semester.
Repository installation seeds remain unchanged and older; LOAD_ORDER.md and
master schedule docs now distinguish those seeds from V11 and correct stale
snapshot/duration claims. Exact rebuilt meetings intentionally use existing
NB_AcademicMeeting, not lossy legacy Cartesian day/period links.

Pre-count-repair full backup: `/private/tmp/northbridge-rebuild-snapshot-e638f88d4c42/`,
restored into `northbridge_ow_restore_2026_11` (53 table fingerprints).
**Latest final full backup:** `/private/tmp/northbridge-rebuild-snapshot-f1b601f6a21b/`,
restored into **`northbridge_ow_restore_2026_12`**, all 53 table fingerprints equal;
dump SHA256 `5a8a07aa7d002872fb2a7d9fcf2dddb22efa200ba297e9e8885d44c6aeca3a8a`.
Older restore `_10` is the pre-count-repair V11 state. Candidate/schedule hashes are
unchanged by this repair; final activation must also bind the campus and count
repair receipts. **No working cutover or repository seed replacement.**

**Latest display refinement, 2026-11-09:** owner requested removing Semester
(already in the title) and Credits from schedule tables. Credits are stored in
`Course.course_Credits`, not Course_Section; retain them for academic logic. Shared
student/faculty/master schedule renderer now uses CRN, Course, Course Name, Section,
Professor, Days, Time, Building / Room, with role actions retained. Semester titles,
course credits, seat tracking, relationships, and database schema/data are unchanged.
Adjusted column CSS and header assertions. On V11 `_7`, all 807 portal, 5,492
master-catalog, and 12,437 faculty checks pass with the eight-column layout;
database tables remain unchanged. PHP syntax and `git diff --check` pass.
Updated `docs/semester-schedule-layout.md` to distinguish stored credits from the
schedule display and semester heading. No working cutover.

**Earlier column layout (superseded by the refinement above), 2026-11-09:** owner supplied a
reference table and requested CRN, Course, Course Name, Section, Semester, Credits,
Professor, Days, Time, Building / Room, without capacity or seat-count columns.
Inspected the actual `_7` SHOW CREATE TABLE output: Course_Section has CRN,
course_ID, section_No, faculty_ID, time_Slot_ID, room_ID, semester_ID, available_Seats
with the expected foreign keys. Display names/credits/locations/meetings come from
related records; display order does not require a schema change. Seat enforcement
and stored availability remain intact; only display columns are removed.
Schema/display mapping is documented in `docs/semester-schedule-layout.md`;
student/faculty dashboard documentation now reflects these columns and exact meetings.
Implemented shared `includes/schedule-columns.php` in student/faculty semester
schedules and admin master schedule. Faculty retains its roster action; Admin
retains Edit. All exact meeting groups/dates/locations are retained, one row per
section. Master uses exact meeting records for displayed rows without changing saved
snapshot data. Checks on V11 `_7` pass: 807 authenticated portal checks across all
12 majors; 5,492 master catalog/header/pagination checks; 12,437 faculty roster,
profile, and header checks covering all 6,147 sections / 55,830 roster entries.
Student/faculty navigation units, meeting-group regression, PHP syntax, and
`git diff --check` pass. All database table checksums remain unchanged. The first
portal assertion used an exact single-class selector and was updated for the shared
multi-class meeting markup; the rerun verifies every meeting group is present.
Fresh browser-based visual QA was unavailable because the browser access host was
unavailable; authenticated DOM checks verified exact headers and displayed content.
No database or schema changes; working cutover remains unapproved.

**Latest verified state, 2026-11-09: V11 plus the four-building campus overlay is
loaded in `_7`; the working portal still uses `_5`.** Owner approved the
source-first correction. Both Fall 2026 Northbridge-only MC4802 sections now meet
on two verified 100-minute periods: group 1 **Tue/Thu 14:40–16:20** (VA2030 template
CRN 90196), group 2 **Mon/Wed 16:30–18:10** (VA4200 template CRN 90198). They retain
null official CRNs and are not represented as official Old Westbury MC4802
sections. Genuine official offerings and documented lab/studio formats were not
rewritten. Rosters, students, faculty, room IDs, capacities, coursework, and grades
are unchanged. Other custom lecture templates now prefer verified two-day periods
in future planning; this correction does not certify every other custom course
format or impose two days on every official four-credit course.

Final private directory: `/private/tmp/northbridge-history-20261109-v11/`.
Candidate SHA256: `69c1d4099a0788c56cc7e1bf8700d2c0bb32ef0fe6eb8e55d66f679f838e2560`.
Schedule SHA256: `c814f689766d079905ac8ab13974e0959598a9fa851183822994bd4a5c3a192b`.
The campus overlay hash remains
`eb12ec283461cd596fbf9794be2e37bc1e73670769da5554dc43fc3b77ab87d8`;
Building/Room tables were verified unchanged by the meeting patch. V10 remains
archived as the parent and its old Friday meetings are superseded.

Eleven template tests, all 14-term timetable/resource checks, all 45,821 independent
prerequisite/PHP parity/persisted enrollment checks, and all 2,200 persisted degree/
minor audits pass on V11. There are now **9,388 exact meeting rows** for the same
5,065 undergraduate sections. Fresh V11 receipts also cover 783 portal checks,
94 audit privacy/read-only checks, 12,435 faculty roster/profile checks, and 5,447
master-catalog checks. V11 private seeds reloaded all 27 exported tables from 23
SQL files. Final backup/restoration into `northbridge_ow_restore_2026_10` passed all
53 table fingerprints; backup directory:
`/private/tmp/northbridge-rebuild-snapshot-7c732b02826f/`. Pre-patch `_7` backup is
`/private/tmp/northbridge-rebuild-snapshot-6e7693296f0a/` (restore `_2026_9`).
V11 `final-test-state.json` is the current aggregate receipt. Schema, protected
academic records, graduate assignments, and working `_5` are unchanged; no working
cutover or repository seed replacement. Updated tools:
`tools/retime-custom-lectures.py`, `tools/apply-lecture-meeting-repair.php`,
`tools/validate-in-person-staging.py`; separate policy manifest:
`database/staging/academic-alignment/northbridge-lecture-meeting-policy.json`.

The owner also asked what to present today. Created
`docs/presentation-walkthrough.md`: an 8–10 minute implemented-feature walkthrough
covering the problem/ERD, student schedule/audit, faculty profile/rosters, admin
catalog filters, four buildings, and verification, with disabled workflows stated
as future work. The test portal remains on port 8003. Faculty review/cutover approval
are still pending; no background updating is implied when this chat ends.

**Faculty additions completed and tested, 2026-11-09:** owner requested Course
Rosters and Full-Time/Part-Time in My Profile. `faculty/rosters.php` and
`includes/faculty-rosters.php` now provide read-only current/next and assigned
historical rosters, linked from the sidebar, Home tile, and each assigned schedule
section. Only the authenticated section owner can retrieve ID/name/university
email/type/year/major; enrollment/history rows are deduplicated. Other roles and
unowned CRNs are blocked, malformed selectors rejected, and write methods refused.
My Profile displays `Faculty.faculty_Type` as Employment Type. Manual use case 37
was reviewed; grading/attendance/account changes remain outside this feature.

On V10-plus-campus-overlay `_7`, **12,435 roster/profile checks passed**, verifying
all 6,147 sections and 55,830 distinct roster entries, both employment types, URL
tampering, role boundaries, and unchanged checksums for all database tables.
The existing 38 Faculty HTTP checks and updated navigation unit checks also pass;
PHP lint and `git diff --check` pass. Receipt: V10
`faculty-rosters-verification.json`; private previews remain in the V10 directory.
Refreshed the owner's actual Chrome profile, confirmed Part-Time, then opened the
working Course Rosters link and verified the assigned course/students rendered.
Chrome is left on the authenticated faculty roster in the port 8003 test portal.
Faculty review remains pending. No academic data, schema, seeds, credentials, or
working portal configuration changed. Source UI changes apply to local servers
serving this checkout. `docs/faculty-dashboard.md` reflects the implemented scope.

**Latest owner feedback, 2026-11-09:** refreshed master schedule "looks good at
first glance." Record this as a positive preliminary review, not final cutover
authorization. Student view is approved; faculty schedule review remains pending.
Working `_5` and repository seeds remain unchanged.

**Owner review update, 2026-11-09:** student view approved. The reported master
schedule mismatch was confirmed on port 8003: Fall 2026 mixed 446 rebuilt
undergraduate sections with 95 preserved graduate sections; old graduate ART10501,
ART10503, and ART10506 appeared first. This was a catalog-scope/display issue.
Fixed `admin/master-schedule.php` and `includes/schedule.php` to default the rebuilt
portal to Undergraduate, with explicit Graduate (existing catalog) and All Levels
options. Counts, departments, search, pagination, and Clear Filters honor the level.
Saving still captures the complete semester, including graduate sections, with that
scope stated beside the save button. No data, schema, or seeds changed.

`tests/master-catalog.php` passed **5,447 read-only checks** against V10 in `_7`:
all 6,147 sections partition correctly into 5,065 reviewed undergraduate and 1,082
preserved graduate sections across all terms; every undergraduate code/name/credit
matches the reviewed course definition; complete current/next pagination and
search scopes match; all database table checksums remain unchanged. PHP lint and
`git diff --check` pass. Refreshed the owner's actual Chrome tab and visually
confirmed the Undergraduate default with 446 Fall 2026 sections / 169 courses.
Receipt: V10 `master-catalog-verification.json`. Owner master/faculty review and
working cutover remain pending; student approval is not approval of those views.

**Earlier V10 checkpoint (superseded by V11 above):** the historical reconstruction
was loaded in `_7`; the working portal stayed on `_5`. No working cutover or
repository seed replacement occurred.

Final academic files: `/private/tmp/northbridge-history-20261109-v3-repaired-v10/`.
All eight original load gaps are resolved: zero minimum/maximum-load failures
across all 14 terms, 5,065 sections, 9,386 exact meetings, and 45,821 assignments.
All 2,200 undergraduate identities and expected current/next students are preserved.
There are 37,823 completed historical attempts; Fall 2026 and Spring 2027 have no
final grades or earned credit. The loader is `tools/rehearse-full-history.php`:
transactional DML only, with fresh-clone, backup, receipt, and identity guards.

Exact academic hashes:

- Candidate: `e4f0bb789c175167bb152a062e3d12001d3513f02491735ad61a64ce7f9a891b`
- Schedule: `e68596d9b1d05bd846e1c7b1bd1e0f6c8a11421b3934c87aeafff5645c70c0cc`

Independent prerequisite review and PHP parity agree on all 45,821 attempts:
37,823 historical satisfied; current 3,509 satisfied / 499 conditional; Spring
2,314 satisfied / 1,676 conditional. Conditional work remains explicit and grants
no earned credit or unconditional registration. All 2,200 database-backed degree
and minor audits match candidate evaluations, with zero differences. There are
64 graduation-ready audits; this is not an award of graduation to every student.
Structural planning covers all 24 programs / 37 variants, no missing purposes,
and zero CIS freshmen taking MA1020 and MA2000 together.

**Owner clarified four campus buildings total. Implemented as a separate,
hash-bound campus overlay in `_7`:** retain ADM (Administration), NAB (Northbridge
Academic Building), SCI (Science Center), LIB (Northbridge Library). ART/BUS rooms
moved into NAB; HSC/TECH rooms into SCI. All 725 room identities/types/capacities
remain; 338 room locations/numbers changed, with no duplicate room numbers within
a building. Graduate course assignments remain unchanged; graduate location labels
intentionally reflect the campus change. Overlay tool:
`tools/consolidate-campus-buildings.php`. Plan SHA256:
`eb12ec283461cd596fbf9794be2e37bc1e73670769da5554dc43fc3b77ab87d8`.
The academic candidate/schedule hashes remain unchanged because room IDs did not
change. Final `_7` state means **V10 plus this campus overlay**, not V10 alone.

The actual faculty manifest adds **63 courtesy cross-appointment rows across 39
full-time faculty**, including two extra routes added during this session. Earlier
64/40 and 66/42 counts were assumptions, not actual manifest counts. All primary
appointments and allocations, three-department limits, graduate reservations, and
global teaching limits are preserved. Courtesy rows use zero administrative percent
and each faculty member's earliest existing appointment date. These are explicit
fictional project assignments, not claims about real faculty qualifications.

The schedule now shows one row per enrollment with every exact meeting group in a
Meetings cell. Section labels use `NB-010` instead of `007 · Group 10`; source
references remain stored separately. Visual review confirmed the layout and updated
four-building room labels. A separate preview server is running at
`http://127.0.0.1:8003/` using `_7`; this is not the older `localhost:8002` rehearsal.

Checks on the final state: 783 authenticated schedule/role/location checks across
12 majors, 94 degree-audit privacy/read-only checks, nine assignment guards;
53-table definitions and triggers unchanged; 76 foreign-key columns and all seats
verified; protected accounts, identities, graduate academic data, and working `_5`
fingerprints preserved. Academic tables were unchanged by the campus overlay.

Private seed export/reload is verified for the final campus overlay: **23 SQL
files / 27 tables**, including faculty appointments, Building, and Room, at V10
`seeds/`. Repository seeds remain untouched by this session.
Final-state full backup/restoration passed all 53 table fingerprints into
`northbridge_ow_restore_2026_8`; backup:
`/private/tmp/northbridge-rebuild-snapshot-8babac1ff31d/`. The earlier restore database
`northbridge_ow_restore_2026_7` represents the pre-overlay rehearsal.

Private receipts in the V10 directory include timetable validation, independent
prerequisites, effective rules, PHP parity, degree inputs/results/summary, DML plan
and database receipt, persisted verification, portal degree/HTTP checks, campus
plan/receipt, and seed verification. No credentials or individual histories belong
in public reports. Original `_5` backup remains
`/private/tmp/northbridge-rebuild-snapshot-0e96cbfe0586/`.

**Do not load V6–V9 repair candidates:** independent coverage caught an atomic
rollback bug that left one BU5190 attempt in a different term from its roster.
V10 was regenerated from consistent V5 after fixing all-student rollback and adding
a fail-fast coverage gate. V3 receipts do not approve V10. Other fixes include
faculty rebalancing, compatible same-course section compaction, course relocation,
and PHP recognition of the nine source-listed math-proficiency course routes.
No filler course or new historical grade was needed for the final eight gaps.

Final aggregate receipt: V10 `final-test-state.json`. All authorized test gates
are complete. Next: owner review of the `_7` portal before any working cutover. Preserved
graduate Friday sections remain outside this undergraduate alignment. Keep this
handoff updated; no background updating is implied when a chat ends.

Project folder: `/Users/phillipaltidor/Downloads/Northbridge-University-main`

## Goal

Make the fictional undergraduate academic data follow SUNY Old Westbury's reviewed
2026–2027 requirements across all 12 majors and 12 minors. This includes
prerequisites, concentrations, general education, degree audits, historical
progression, and semester schedules.

The original problem was that schedules could pass technical checks while
containing academically poor choices—for example, a CS freshman taking College
Algebra and Applied Statistics together without a useful reason.

## Owner requirements and authorization

- **Do not change the database schema.**
- Use fictional Northbridge students, faculty, accounts, and rooms. Do not import
  Old Westbury's personal information.
- Use Old Westbury's reviewed course codes, names, credits, prerequisites, degree
  requirements, and attributes.
- Use in-person classes only.
- **2026-11-09 clarified:** four buildings across the entire campus, not merely four instructional buildings. Implemented in `_7`, pending working cutover review.
- Preserve official CRNs and complete meeting patterns when using actual official
  sections.
- The owner explicitly approved creating clearly labeled Northbridge-only
  in-person sections when suitable official in-person offerings are unavailable.
  Use verified Old Westbury meeting periods, but do not assign those custom
  sections an official Old Westbury CRN.
- The owner previously authorized changing fictional historical courses as
  necessary to make progression follow degree requirements. Preserve backups and
  document what changes.
- Follow the real timeline: Fall 2026 is in progress; Spring 2027 is preregistered.
  Neither term should contain final grades or earned credit.
- Focus on undergraduates. Graduate academic requirements are outside this rebuild.
- Work autonomously on routine decisions. Ask only when a material academic or
  project decision genuinely needs the owner's input.
- Keep the owner updated in plain language about what is fixed, what remains
  incomplete, and whether changes are in testing or in the working portal.
- **2026-11-09:** The owner approved the full test-only historical rebuild and
  requested urgency for a presentation tomorrow. This does not authorize switching
  the working portal before checks and review. The owner reiterated that every
  meaningful work step must be recorded in this handoff.

## Approved academic decisions

Retain the reviewed decisions already documented in the repository, including:

- CS3911 as the tenth required CIS core course.
- MIS: any two distinct courses from MA2701, PE2420, and PE2430.
- Marketing minor: BU3800, BU4851, BU4820, BU5880, plus BU5841 or BU5891.
- Childhood Education: the approved historical ED4050 and ED5925 versions.
- CS5910: the three listed CS prerequisites plus completed English writing
  proficiency.
- CS5810: CS4550, MA3210 or MA2000, and Composition II proficiency.
- Psychology B.S.: 60 liberal-arts credits.
- The other documented source interpretations and project exceptions.

Basic entry writing/math placement is satisfied for fictional students, but that
does not automatically grant college-course credit or waive course prerequisites.
MA1020 entry placement must not be treated as completed MA1020 for CS2510.

## Current implementation

### Faculty rosters and profiles

- Course Rosters is enabled in Teaching, Home Quick Access, and assigned schedules.
- Read-only current/next and assigned historical rosters are restricted to the
  authenticated instructor's sections and show student academic/contact basics.
- My Profile shows Full-Time/Part-Time from `Faculty.faculty_Type`.
- V10 `_7` roster/profile, dashboard, and visual checks pass; owner review pending.

### Undergraduate degree audits

- Student access is restricted to the authenticated student.
- Faculty access is read-only.
- Audits separate completed, in-progress, and preregistered work.
- Course links show descriptions, prerequisites, and attributes.
- Missing GE requirements show linked attributes and linked eligible course codes.
- The interface uses the screenshots supplied by the owner as its blueprint.

### Requirement-driven planning

`database/generation/academic_planning.py` separates:

- Required major/minor courses.
- Elective alternatives.
- Prerequisite preparation.
- College writing.
- General education.
- Liberal-arts and upper-division distributions.
- General electives justified by remaining degree credits.
- Minor credits required outside the major.

It structurally covers all 24 programs and 37 concentration/path combinations.
Only CIS, MIS, and Mathematics have published sample sequences in the supplied
program pages. Other sequences are derived from requirements and prerequisites
and must not be described as official published sequences.

### Two-term trial planner

`tools/build-purposeful-schedules.py` uses reviewed source meetings, fictional
faculty/rooms, workload limits, seat capacities, and prerequisite checks. Recent
changes prioritize constrained students, reassess choices after each assignment,
reserve scarce departments for required work, and exchange optional fourth-course
seats when another student is below the minimum load.

It also targets a useful 12-credit Fall load for full-time seniors rather than
automatically front-loading every senior to 16 credits. This is a planning target,
not a waiver of prerequisites, graduation conditions, or enrollment-type minima.

### Northbridge-only sections

Files:

- `database/generation/in_person_sections.py`
- `database/staging/academic-alignment/northbridge-in-person-section-policy.json`

Custom sections use `NB-IP` and no official Old Westbury CRN. Portal display
support uses `NB-<local section ID>`. Source template references remain separate
from the identity of the Northbridge course section.

### Recovered foundation prerequisite routes

Six previously unresolved entries received pinned, source-backed sufficient routes:
BS2400, BS2401, BS2410, BS2411, BU4180, and BU5180.

Files:

- `database/generation/rebuild_foundation_proofs.py`
- `database/staging/academic-alignment/foundation-prerequisite-proofs.json`

Biology lecture/lab pairs must stay together. Ambiguous math-proficiency and
undefined MA3250 alternatives remain reviewable; do not invent equivalencies or
waive requirements. These new routes have not yet been loaded into the working
database.

### One row per schedule section

`meeting_schedule_group_sections()` was added to `includes/meeting-schedule.php`.
It is now wired into
`student/schedule.php`. One course row retains every exact meeting group, day,
time, date range, and room. Unit, authenticated HTTP, and visual checks passed.

## Important findings and current direction

- Earlier technical verification did **not** establish sensible academic progression.
- The earlier trial contained no BS2400/BS2401/BS2410/BS2411 attempts, despite those
  being Biology foundations.
- Some earlier fictional histories also omitted writing/preparation needed for
  later capstones. Fixing only Fall/Spring seats cannot fully solve that.
- The last intended direction was to review/rebuild progression from each student's
  first historical semester, using the owner's authorization to rebuild past courses.
- The chronological historical pipeline is implemented. Final V10 passed academic,
  timetable, parity, persisted-record, degree-audit, and portal checks and is loaded
  into `_7`; the working `_5` portal is unchanged.
- Existing placement evidence is not uniform: in the earlier baseline,
  placement:ECI was approved for 2,182 students and missing for 18;
  placement:ECII was approved for 1,652, conditional for 285, and missing for 263.
  Review effective dates and evidence rather than silently converting these to
  unconditional approvals.
- Do not solve gaps by adding arbitrary courses, inventing grades, or weakening
  prerequisites. If fictional historical courses/grades are reconstructed, document
  that honestly and retain the original records in backups; do not claim historical
  course meanings remained unchanged.

## Database state

| Database | State |
| --- | --- |
| `northbridge_ow_rebuild_2026_5` | Working portal database |
| `northbridge_ow_rebuild_2026_6` | Earlier technical rehearsal; not academically approved |
| `northbridge_ow_rebuild_2026_7` | Verified V11 reconstruction, two-day MC4802 correction, and four-building overlay; preview on port 8003 |
| `northbridge_ow_restore_2026_10` | Verified exact restoration of final V11 `_7` plus campus overlay |
| `northbridge_ow_restore_2026_9` | Verified pre-MC4802-correction backup of V10 `_7` |
| `northbridge_ow_restore_2026_8` | Verified earlier V10 restoration including the campus overlay |
| `northbridge_ow_restore_2026_7` | Earlier pre-campus-overlay restoration rehearsal |
| `northbridge_university` | Preserved original database |

Database `_7` preserves all **53 existing table definitions**. Creating the clone
did not change the working database or portal configuration. Do not overwrite
existing databases or switch the working portal until replacement checks pass.

`tools/rehearse-full-history.php` implements the DML-only full-history path and
has materialized V10 in `_7`. It preserves protected identities/accounts/graduate
academic rows and verifies matching receipts, schema, triggers, seats, and foreign
keys. `tools/rehearse-two-term-schedules.php` remains two-term only. Never use a
schema-changing loader as a shortcut or silently switch the working portal.

## Source files and documentation

Authoritative reviewed program definitions:
`database/staging/academic-alignment/rules/curricula.json`

Other reviewed sources:

- `database/staging/academic-alignment/rules/`
- `database/staging/academic-alignment/program-review-inputs.json`
- `/Users/phillipaltidor/Downloads/System Manual.docx`

Status and diagnostics:

- `docs/academic-planning-review.md`
- `docs/two-term-in-person-staging.md`
- `docs/academic-rebuild-status.md`
- `database/staging/academic-alignment/academic-planning-review.json`
- `database/staging/academic-alignment/academic-planning-blockers.json`

Some aggregate reports still describe older trial candidates. Check the paths and
hashes before interpreting them as current results.

Validation and rehearsal tools:

- `tools/review-academic-planning.py`
- `tools/review-rebuild-eligibility.py`
- `tools/validate-in-person-staging.py`
- `tools/rehearse-two-term-schedules.php`
- `tools/academic-snapshot.php`
- `tools/export-academic-portal-seeds.php`

## Private inputs and trial candidates

These files contain private account/student data. Never print credentials,
password hashes, or individual histories into public reports.

| Artifact | Private path |
| --- | --- |
| Original working-table snapshot | `/private/tmp/northbridge-rebuild-snapshot-83894debe655/working-tables.json` |
| Original full historical timetable | `/private/tmp/northbridge-rebuild-snapshot-83894debe655/rebuilt-schedules-v18.json` |
| Earlier two-term baseline | `/private/tmp/northbridge-spring-joint-rehearsal-20261108-v2/` |
| Reviewed expanded course catalog | `/private/tmp/northbridge-reviewed-planning-catalog.json` |
| Latest Fall trial | `/private/tmp/northbridge-purposeful-fall-20261108-v10/` |
| Latest completed two-term trial | `/private/tmp/northbridge-purposeful-two-term-20261108-v8/` |
| Original `_5` / fresh `_7` backup | `/private/tmp/northbridge-rebuild-snapshot-0e96cbfe0586/` |
| Parent V10 candidate | `/private/tmp/northbridge-history-20261109-v3-repaired-v10/` |
| Final V11 candidate and campus overlay | `/private/tmp/northbridge-history-20261109-v11/` |
| Final V11 `_7` backup / restore receipt | `/private/tmp/northbridge-rebuild-snapshot-7c732b02826f/` |
| Earlier V10 campus backup | `/private/tmp/northbridge-rebuild-snapshot-8babac1ff31d/` |

The older Fall trial had one underload; the older two-term trial had 27 Spring
underloads. These are superseded diagnostics, not current approval. Final V10 is
validated and loaded in `_7` only; the working portal has not been switched.

The earlier baseline covers 14 terms: Fall 2020 through Spring 2027. Historical
term IDs are not chronological: Fall/Spring 2026–2027 are IDs 1/2; use the source
term-order mapping rather than sorting numeric semester IDs.

## Remaining work

1. Review PR #5 for release/v11-main, then merge into main when approved. The
   exact V11 academic package and fresh installer are tested and published.
   Before merge, teammates use the release branch; after merge, pull main and
   follow v11-teammate-setup.md. No automatic merge has been performed.
2. Preserve rollback _5 and restore _15. Main _8 / port8000 activation is complete
   and main-port verified. Isolated tests use _7/8003 or fresh fixtures such as _9;
   never reset the active main. Existing installations need a new target or a
   separately reviewed migration; the fresh installer never overwrites them.
3. Establish the presemester minimum-enrollment review deadline and registration,
   drop and grading boundaries. Keep 33 Spring under-five sections open per the
   approved preregistration policy until review. Calendar-sensitive writes remain
   inactive. Current/next UG advising coverage passed for all 1,140 registered
   students; historical-only advising policy is separate.
4. Continue feature implementation one item at a time against the manual and
   approved existing schema. Local database activation does not implement disabled
   advisor, attendance, grading or registration workflows. No schema changes.
5. Preserved graduate Friday sections/curricula remain separately scoped. Hosted
   shared deployment has not been specified or performed. Fresh installer was
   tested on MySQL 9.6.0; other engines need verification rather than bypassing
   fingerprint checks.

## Tests and verification status

Tests added or used:

- `tests/academic-planning.py` — 15 tests passed, including residual elective credits.
- `tests/in-person-sections.py` — eleven template/source/day-pattern tests passed.
- `tests/foundation-prerequisites.py` — seven source/prerequisite tests passed.
- `tests/schedule-section-groups.php` — section grouping regression passed.
- `tests/academic-rebuild-eligibility.py` — 22 tests passed after foundation changes.
- `tests/entry-placement-policy.php` — eight checks passed.
- `tests/math-proficiency.php` — 37 source-listed-route/grade/date checks passed.
- `tests/historical-load-repair.py` — six relocation/rollback/compaction checks passed.
- `tests/faculty-rebalancing.py` — five staffing/workload/reservation checks passed.
- `tests/rebuild-prerequisites-parity.php`
- `tests/two-term-database-rehearsal.php`

Current V11 plus campus/count overlays passed the schema, schedule, roster,
portal and degree checks listed in the checkpoint above. Earlier V10/V11 receipts
remain scoped to their prior states; they do not by themselves verify later
overlays. The latest private seeds and final full restore cover the count repair.
No cleanup, commit, unrelated repository seed replacement, or working cutover
was performed in this audit.

## Recent updates

- 2026-11-11: Published verified V11 setup checkpoint 30abcda to release/v11-main
  and opened review PR #5 into main. Fresh-install/data/HTTP/guard/syntax checks
  pass; main _8 unchanged; secrets/backups stay local. Main branch not merged.
  Added this follow-up to record the actual GitHub result.

- 2026-11-11: Owner approved release/v11-main publication plan. Verified matching
  25-file/29-table V11 package via new _9 clean install: 53 tables/76 FKs, actual
  HTTP/roster/all-2,200-degree checks pass; main _8 unchanged. Added fresh installer,
  manifest and teammate guide. Branch commit/push/review PR pending at this note.

- 2026-11-11: Owner paused the new GitHub push for a preview of its setup.
  Proposed release/v11-main branch, verified matching seed bundle and teammate
  instructions, with review before merge. No branch, commit, push or new seed
  publication executed; current main _8 remains running.

- 2026-11-11: Applied approved local main connection _5 → _8 after fresh _5
  backup/restore _15. Actual main-port schedule/role/roster/degree tests pass;
  all 53 active tables match release, _5/_7 unchanged. Main is port8000; isolated
  testing stays _7/8003; retired temporary _8/8004 preview. Teammate V11 seed
  packaging/publication remains next, with no remote hosting deployment.

- 2026-11-11: Completed isolated V11 release preparation: _8 copy, _13 release
  restore, _14 fresh working rollback backup; new guarded activation/rollback tool,
  runtime-bound direct _8 checks and private switch rehearsal pass. Main remains
  _5. Concrete final review/approval gate is _5 → _8, preview port 8004. Recorded
  exact plan, private evidence, test scope and post-switch steps; no activation.

- 2026-11-11: Prepared exact V11 local cutover plan. Reverified 25 private seed
  hashes and 53-table equality for `_7`/restore `_12`. Found existing activation
  tool is specific to the older release and must be updated before V11 activation.
  No switch, copy, seed replacement or configuration change was performed.

- 2026-11-11: Published application checkpoint edd95e6 to origin/main. Added
  teammate branch/PR workflow and database snapshot distinctions. Preserved private
  V11 data and both databases; promotion remains a separate pending decision.
  This follow-up records actual successful publication in the handoff.

- 2026-11-11: Recommended controlled promotion of verified `_7` data when the
  remaining review/activation steps are ready; owner has not yet authorized that
  switch. GitHub checkpoint is staged, publication pending; full staged archive
  whitespace review recorded without altering source evidence or database data.

- 2026-11-11: Confirmed preview still uses `_7`, separate working `_5` unchanged.
  GitHub publication remains pending: preparation/tests completed before the
  interruption, but no commit/push executed. Recorded team workflow documentation
  and validation results without claiming deployment or publication.

- 2026-11-11: Explained the four class durations in the owner's screenshot:
  MA1020 08:00–09:40, MD2350 09:50–11:30, PY2720 14:40–16:20, and EL1000
  16:30–18:10 each meet 100 minutes per session, twice weekly: 200 minutes
  (3 hours 20 minutes) per course per week, 800 minutes (13 hours 20 minutes)
  across the four courses weekly. These are scheduled clock times, not a new
  credit-hour policy or semester-total calculation. No code/data/config changes.

- 2026-11-11: Restored master-only Total Seats/Available Seats at owner request;
  section labels now plain schema integers in schedules/rosters. Reverified live
  schema, 5,492 master / 908 portal / 12,437 faculty checks; all tables unchanged.
  Updated schema/display docs and this handoff. No database or working cutover.

- 2026-11-09: Owner approved retaining underfilled Spring sections through
  preregistration and enforcing five at an agreed presemester review. Advisor
  follow-up found all 1,140 current/next undergraduates covered; the 1,000 missing
  advisor records are outside current/next registrations. Five read-only advisor
  checks pass. No roster/advisor/database/config changes; calendar policy next.

- 2026-11-09: Owner asked for advice rather than choosing minimum-enrollment
  timing. Recommended retaining preregistration and enforcing five at an agreed
  presemester review deadline; explicitly not yet an approved policy. Kept the
  rebalancing proposal unapplied and recorded the remaining calendar decision.

- 2026-11-09: Began the first readiness item at owner request: reviewed 33 small
  Spring sections, staged a conflict-checked proposal for 17, documented remaining
  demand/consolidation decisions. Three regression tests/syntax/diff checks pass;
  no data applied. Policy timing question pending; handoff updating after each
  request remains mandatory.

- 2026-11-09: Read System Manual requirements for activation readiness. New read-only
  `_7` findings: 33 upcoming UG sections below five; 1,000 UG / 738 graduate
  students lack advisor rows. Existing advisor caps/PT restrictions and faculty
  department assignments pass. Recorded scope, deadline ambiguity, and concrete
  cutover gates; no data, schema, configuration, or manual changes.

- 2026-11-09: Completed implemented-portal schema audit: 103 aggregate checks,
  53 tables/76 FKs/6,147 sections. Fixed source-capacity handling and 50 stale
  full-time faculty counters in backed-up `_7`; preserved `_5` and schema.
  Reverified portal/rosters/schedules/all 2,200 degree audits; private seed bundle
  now 25 files/29 tables, final restore `_12` verified. Added audit report and
  corrected stale installation/schedule documentation. See checkpoint for scope.

- **2026-11-09:** Removed repeated Semester and Credits columns from all schedule
  tables per owner feedback. Confirmed credits belong to Course.course_Credits,
  not Course_Section; academic calculations and schema/data remain unchanged.
  Eight-column layout, role actions, semester headings, and exact meeting content
  pass 807 portal / 5,492 master / 12,437 faculty checks on V11 `_7`.

- **2026-11-09:** Checked live Course_Section and related schema, then matched
  student/faculty/master schedule columns to the owner's screenshots. Removed
  capacity/seat-count columns from display while retaining stored seat tracking,
  full meeting patterns, roster/Edit actions, and all schema/data. Shared renderer
  and updated CSS are implemented; 807 portal, 5,492 master, and 12,437 faculty
  checks pass on V11 `_7`, plus navigation/grouping and syntax checks. No cutover.

- **2026-11-09:** Applied the authorized V11 meeting-only correction to both custom
  Fall MC4802 sections using verified two-day periods. All coursework, rosters,
  faculty, rooms, and official offerings remain unchanged. Fresh timetable,
  prerequisite/parity, persisted degree/roster/master/portal checks pass. Regenerated
  23 private seed files / reloaded 27 tables; restored all 53 final-state tables into
  `_restore_2026_10`. Working `_5` and repository seeds unchanged. Created the
  presentation walkthrough and updated this continuation checkpoint.


- **2026-11-09:** Implemented faculty Course Rosters and profile Employment Type.
  Verified all 6,147 sections / 55,830 distinct roster entries with 12,435 checks,
  including ownership, tampering, both faculty types, and unchanged database tables.
  Existing 38 dashboard HTTP checks and navigation units pass. Confirmed both
  features in the owner's test browser. Handoff and faculty dashboard docs updated;
  no database/schema/seed/configuration changes or working cutover.

- **2026-11-09:** Recorded owner approval of student view. Fixed the master schedule
  mixing preserved graduate codes into the reviewed undergraduate catalog view;
  added academic-level selection and verified all catalog rows, scope-preserving
  pagination/search, and unchanged data with 5,447 checks. Refreshed and confirmed
  the actual test browser view. No working cutover or academic data change.


- **2026-11-09:** Completed V10 academic reconstruction in `_7`: zero load gaps,
  independent prerequisite/PHP parity, all-term timetable/resource checks, all
  2,200 persisted degree/minor audits, and authenticated role/privacy checks pass.
  Implemented one-row schedule display and clear Northbridge section labels.
  Fixed multi-student rollback and added repair/solver/math-proficiency regressions.
- **2026-11-09:** Owner clarified **four campus buildings total**. Applied the
  DML-only campus overlay in `_7`, preserving all 725 rooms/capacities and all
  course assignments. Final-state HTTP checks pass; final backup/restore verified
  all 53 tables. Working `_5`, portal configuration, and repository seeds remain
  unchanged. Final private seeds reloaded all 27 tables from 23 files successfully;
  final restoration into `_restore_2026_8` verified all 53 table fingerprints.


- **2026-11-09:** Resolved the six Business and two CIS load gaps through
  validated existing-course moves, compatible section compaction, and limited
  fictional faculty reassignments. V2's superseded slow repair run was stopped;
  V6–V9 were rejected for a rollback/coverage defect. V10 was regenerated from the
  consistent V5 base after the fix. No background repair is running. Use
  `/private/tmp/northbridge-scheduling-venv/` for OR-Tools (system Python lacks it).
  The optional pending-course addition path was not exercised by the final repair;
  it must receive direct coverage before being relied on in a future run.

- **2026-11-09:** Traced the screenshot's `007 · Group 10` Section label to
  `academic_portal_source_labels()` in `includes/academic-portal.php`: it combines
  stored `source_Section` with the locally generated `delivery_Group`. The older
  `schedule_oldwestbury.py` takes source section labels from `sequenceNumber` and
  splits fictional rosters into groups of at most ten, numbering groups per course
  within each term. Thus Group 10 is a local roster identifier, not a source section
  number or student count. Screenshot alone does not identify the course, source
  offering, or whether it used a historical/alias template. No portal/database code
  or data changed during this explanation.

### Earlier execution log — 2026-11-09 (superseded by the checkpoint above)

1. **Authorization recorded:** owner approved the full historical test rebuild;
   working database/schema/identities/accounts/graduate data remain protected.
2. **Inputs inspected:** re-read this handoff and current planner/evidence code.
   The earlier baseline has 14 terms and uneven ECI/ECII evidence, as recorded above.
3. **Planner generalized:** `tools/build-purposeful-schedules.py` now supports
   historical source terms, retains original same-course/term grades where possible,
   and otherwise labels reuse from the original fictional student/term grade pool.
   Current/future grades remain null. It now returns candidate/schedule results for
   chronological orchestration and carries the complete set of scheduled terms.
4. **Attribute handling tightened:** planning uses reviewed source properties;
   courses lacking reviewed liberal-arts/classification facts do not receive guessed
   liberal-arts/classification credit in planning.
5. **Historical template handling added:** `in_person_sections.py` allows verified
   historical four-credit templates with at least 180 weekly minutes, preserving
   their actual meetings. The current/next-term template default remains 200.
6. **Evidence reconstruction made explicit:**
   `database/generation/historical_planning.py` binds owner-approved fictional basic
   entry placement to each admission term and records original/replacement evidence.
   Simulated Composition II eligibility follows completed Composition I at C or
   better; it grants no course credit or writing-completion waiver. These are
   documented simulation decisions, not authentic exam scores or transfer approvals.
   Independent boundary/parity checks are still required before loading anything.
7. **Chronological driver added:** `tools/rebuild-purposeful-history.py` reconstructs
   all 14 terms in order, saves a checkpoint after each term, and writes final private
   candidate/schedule/summary files. It does not connect to or modify the database.
8. **First run started:** `/private/tmp/northbridge-history-20261109-v1/`.
   This is an unfinished, unvalidated run. Earlier candidate paths remain historical
   diagnostics, not the final replacement. Next: inspect per-term results, correct
   blockers, run independent academic/timetable checks, and prepare DML-only test
   database materialization.
9. **Template regression:** seven existing in-person template tests passed after
   historical support was added. Initial chronological results had zero underloads
   in Fall 2020 and Fall 2021 through Spring 2024. Spring 2021 had no on-campus
   source offerings, so its 222 plans received no classes and that run is blocked.
   These results are diagnostics, not approval.
10. **Historical no-campus case handled:** Northbridge-only templates can now borrow
    verified periods from the nearest available in-person source term while using
    the actual target-term source date range. Template term/original meetings are
    retained separately, and official CRNs remain null. This is explicit fictional
    Northbridge reconstruction, not a claim that Old Westbury offered in-person
    Spring 2021 classes. Independent cross-term verification is still required.
11. **Checkpoint restart:** started `/private/tmp/northbridge-history-20261109-v2/`
    from the valid Fall 2020 checkpoint. The superseded v1 process was stopped by
    exact process match; no database was touched. Eight template tests passed,
    including preservation of borrowed periods and separation of target-term dates.
12. **Second chronological run finished:** all 14 terms were generated. Spring 2021
    now has zero underloads. Other gaps remain, including 11 Fall 2026 and 26 Spring
    2027 plans; this candidate is blocked and has not been loaded into any database.
13. **Additional causes identified:** some inactive longer histories finish all
    coursework before their original enrollment horizon; others exhaust teaching
    capacity in their required department. Added reviewable fictional full-time
    cross-appointments using existing `Faculty_Department` rows and the manual's
    three-department limit. Primary appointments, identities, and graduate section
    assignments remain preserved. This assumption must be reported and independently
    validated; it has not been written to a database.
14. **Load smoothing added:** planning now reserves useful coursework across each
    original enrollment horizon, using remaining requirement-credit budgets and
    existing minimum/maximum loads. It does not change graduation requirements or
    invent extra courses just to fill a semester. A new run is still required.

- **2026-11-09:** Saved this continuation prompt as `PROJECT_HANDOFF.md` and added
  root `AGENTS.md` instructions to maintain it after meaningful project work and
  before ending a work session. This was a documentation change only; no database,
  academic record, or portal configuration changed.
