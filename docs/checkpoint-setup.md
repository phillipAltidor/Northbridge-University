# Admin Dashboard and Master Schedule Checkpoint

## Existing Installation

Pulling the branch changes code, not your local or hosted database. Do not replace
an existing database with seed SQL: that can lose account/password changes and
audit records. Back up your database first and keep the backup outside Git.

1. Install PHP dependencies with `composer install --no-dev` if not already done.
2. Keep your own private `.env`. Never commit database or mail credentials.
3. Confirm you are targeting the intended local database. These migration
   scripts intentionally require `DB_HOST=127.0.0.1` and
   `DB_NAME=northbridge_university`. Stop and review other deployments separately;
   do not remove safety checks merely to run against a remote database.
4. Audit the timeline first:

   ```sh
   php database/migrations/align_academic_timeline.php
   ```

5. Review its proposed changes, then apply:

   ```sh
   php database/migrations/align_academic_timeline.php --apply
   ```

6. Audit the Master Schedule changes next:

   ```sh
   php database/migrations/master_schedule_requirements.php
   ```

7. Review those changes, then apply:

   ```sh
   php database/migrations/master_schedule_requirements.php --apply
   ```

8. Verify:

   ```sh
   php tests/academic-timeline.php --database
   php tests/master-schedule-data.php
   php tests/master-schedule.php
   php tests/admin-navigation.php
   node tests/admin-navigation.cjs
   ```

The scripts create private SQL and seed backups under `/private/tmp` and print
their paths. Retain approved backups in durable private storage. The isolated
test suite requires create/drop-database and trigger privileges; the migration
also requires trigger privileges. Trigger installation follows the committed
data transaction because MySQL trigger DDL is not transactional. If installation
fails, resolve the reported issue and rerun the Master Schedule migration.
After restoring a timeline-only backup, rerun that migration to reinstall seat
triggers. Never share backups or live Password_Reset rows in a pull request.

For a fresh installation, use `database/LOAD_ORDER.md`. Import seed rosters
before installing the seat triggers; sections already have populated seat counts.
Phase 2's password-reset migration and private mail setup remain separate
requirements described in `docs/password-reset.md`.

## What Changes

- Admin Home and grouped navigation, with unfinished actions disabled.
- An explicit October 22, 2026 snapshot: Fall 2026 in progress, Spring 2027
  upcoming, with no current/future final grades or completed histories.
- Historical enrollment retained from completed student history, preserving
  recorded grades. Existing accounts and passwords are not rewritten.
- Fictional Fall attendance corrected to meeting weekdays, duplicates combined,
  and future dates excluded. This is not verified real attendance.
- Schedule columns, filters, 25/50-row pagination, sticky headers, and synchronized
  horizontal scrolling. Saving uses the full semester, not a displayed page.
- Fixed section capacity of 10; roster-derived displayed seats and transactional
  stored-seat updates for registration, drops, and transfers.

## Not Completed

Student/Faculty dashboards and registration screens, prerequisite/hold/credit
validation, registration windows, section creation, Spring 2027 schedule saving,
and remaining faculty department/load rules still need implementation against
the manuals. Complete historical attendance and holiday validation require
official semester calendars. This checkpoint does not complete all Phase 3 or
Master Schedule use cases. See `docs/master-schedule.md` for details.
