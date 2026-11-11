# Promote the verified V11 data to the working portal

Applied and verified November 11, 2026: local main now uses _8. The earlier
preparation and command details below are retained as execution context; pending
approval/main-switch statements describe the pre-activation stage, not current
state. See academic-rebuild-status.md and PROJECT_HANDOFF.md for the applied result.
The temporary _8 preview on port 8004 was retired after main-port tests passed.
Matching V11 academic seeds and fresh-install verification are now prepared on
release/v11-main; see v11-teammate-setup.md. Publication/PR status is in the handoff.
Immediate working rollback snapshot was refreshed to restore _15 under
`/private/tmp/northbridge-rebuild-snapshot-95ab5f19d3e2/`.

## Rehearsal result

Dedicated working-release copy `northbridge_ow_rebuild_2026_8` and restore `_13`
match all 53 tables in verified source `_7`. The main portal remains `_5`.
Release preview: http://127.0.0.1:8004/ . Direct _8 checks pass: 5,492 master,
908 portal, 94 degree HTTP, 12,437 faculty-roster checks and all 2,200 persisted
degree evaluations. Schema (103) and advisor (five) checks also pass. Runtime
hashes are stored in the direct receipts; code changes invalidate those receipts.

New `tools/activate-v11-release.php` supports guarded preflight, private-file
rehearsal, apply and rollback. Fourteen configuration/target checks pass. Invalid
plan approval and missing backup both reject without changing real .env. A full
switch/rollback was rehearsed on a temporary private environment file; real main
routing has not been switched. Existing app data and schema were unchanged.

Working _5 has a freshly restore-verified backup into `_14`:
`/private/tmp/northbridge-rebuild-snapshot-1071d2a0c78d/`. Apply requires a backup
less than one hour old that still matches all working tables; refresh it if the
owner reviews later. It also rejects any working-data change since the reviewed
rebuild baseline, so drift must be reconciled before promotion.

Private evidence and the exact plan: `/private/tmp/northbridge-v11-release-20261111/`.
Public aggregate receipt: database/staging/academic-alignment/v11-release-preflight.json.
The script and runtime hashes, data fingerprints and private environment hash
are bound into that plan. Final owner faculty/admin review and explicit local
main connection approval remain pending. Do not treat the rehearsed apply path
as an actual activation or a post-switch smoke-test result.

## Commands and final cutover controls

Preflight and private-file rehearsal:

```sh
php tools/activate-v11-release.php --release-directory /private/tmp/northbridge-v11-release-20261111 --candidate-directory /private/tmp/northbridge-history-20261109-v11 --rehearse
```

Only after approval, stop/drain the main web server's writes, refresh the private
working snapshot if needed, and rerun preflight. Apply uses the exact plan-file
SHA256 printed by that final preflight:

```sh
php tools/activate-v11-release.php --release-directory /private/tmp/northbridge-v11-release-20261111 --candidate-directory /private/tmp/northbridge-history-20261109-v11 --apply --approved-plan-sha256 APPROVED_PLAN_HASH --working-backup PRIVATE_WORKING_RECEIPT --writes-drained
```

`--writes-drained` is an operator assertion, not an automatic maintenance mode.
Stop/drain requests before setting it. The command changes only DB_NAME, preserves
unrelated environment settings, and rolls back on detected drift or receipt-write
failure. It leaves database records and schema untouched. Main-port checks must
then run against real main routing on port 8000 with no database override. The
master/roster suites support explicit `--main-smoke` only when real .env selects
_8. Save post-switch receipts separately and record their actual results.

Rollback changes only the connection back to preserved _5:

```sh
php tools/activate-v11-release.php --rollback-receipt /private/tmp/northbridge-v11-release-20261111/activation-receipt.json
```

Do not overwrite current unrelated .env settings with a whole old configuration
file. Preview _8 runs via a process-owned override; successful preview checks do
not verify the main server's connection after a switch.

## Current verified state

The normal local portal configuration selects `northbridge_ow_rebuild_2026_5`.
The reviewed preview on port 8003 selects `northbridge_ow_rebuild_2026_7`, holding
V11 plus the four-building and faculty-class-count overlays. No switch occurred.

A fresh read-only check found all 25 private V11 seed hashes unchanged. All 53
tables in `_7` and `northbridge_ow_restore_2026_12` still match the final restore
receipt in `/private/tmp/northbridge-rebuild-snapshot-f1b601f6a21b/receipt.json`.
These matches identify a concrete release; they do not approve its activation.
The private seed bundle is under `/private/tmp/northbridge-history-20261109-v11/seeds/`.

## Cutover checklist and scope

1. Update the guarded activation path. The existing
   tools/activate-academic-portal.php expects the original database configuration,
   old receipts, old section totals and unchanged campus/appointment data. It
   cannot certify the current full-history V11 release. Preserve its guards;
   implement a version-specific preflight with dry-run output binding the exact
   candidate, schedule, campus overlay, count repair, seeds and all-table hashes.
2. Prepare a dedicated working release copy of verified `_7`, with the same schema
   and data. Keep `_7` available for isolated testing and keep `_5` for rollback.
   Check the new database name is unused before creating it. Verify every copied
   table and trigger. Inherited test evidence must explicitly name its source and
   prove copy equality; do not relabel old receipts as tests of a new database.
3. Finish owner review of the final faculty/admin views and present the exact
   dry-run plan, target connection and rollback instruction for cutover approval.
   Student review and earlier master first-glance review do not establish final
   faculty/admin approval. Do the reversible preparation before asking to switch.
4. Immediately before the approved switch, freeze writes during the short cutover,
   back up and restore-check current working `_5`, and privately preserve the old
   DB_NAME setting. Compare shared account/resource tables to catch changes since
   the test clone; reconcile any differences explicitly. Do not discard newer
   account changes or import passwords blindly. Rerun release fingerprints.
5. Switch only the main portal database connection to the verified working copy.
   The physical name does not have to be northbridge_university. Verify Student,
   Faculty and Admin profiles, schedules, roster ownership, seat counts and degree
   audits through the normal portal. Roll back the connection if checks fail.
   Code publication has already happened; this step activates data locally.
6. After approved activation, synchronize the reviewed installation bundle and
   teammate setup instructions as a separate GitHub change. Verify clean fresh
   installation and same release markers, including faculty appointments, FT/PT
   subtype counters, Building and Room overlays. Existing accounts and credentials
   are separate from the fictional academic seed bundle; private backups stay
   outside Git. Each teammate uses their own local database and private .env.

## Scope retained after activation

The 33 small Spring sections stay open during preregistration by owner decision.
The presemester review deadline is still unset. Registration/drop/grading windows
and cancellation notifications remain unfinished features; activation does not
enable their writes or invent calendar boundaries. All current/next undergraduate
students already have advisors. Historical-only advising and graduate provisioning
remain separately scoped. Preserve the existing schema.

The working database is a local development baseline. Hosting or deploying a
shared remote database would require its own target configuration and deployment
plan; no hosted target has been identified in this request.
