# Set up the shared V11 development database

Use the reviewed release branch until it is merged into main. This package
reproduces the activated V11 academic data: 25 updated seed files, 29 academic
tables, 53 total schema tables, and the existing seat triggers. It includes the
approved campus and faculty-count changes. All people and academic seed records
are fictional. Existing account seed files have not been regenerated.

## New local installation

1. Pull the reviewed branch and install PHP dependencies with `composer install`.
2. Copy `.env.example` to your private `.env` and enter your own local MySQL
   credentials. Keep its DB_NAME different from the new target during installation.
   No existing database is needed for the installer to connect to MySQL.
3. Pick an unused local database name. The example below uses
   `northbridge_ow_rebuild_2026_9`. The installer rejects existing targets and
   reserves Phillip's _5, _7 and _8 names; it never changes .env.
4. Preview the verified load plan:

   ```sh
   php tools/install-v11-development.php --target northbridge_ow_rebuild_2026_9
   ```

5. Create and verify that new database:

   ```sh
   php tools/install-v11-development.php --target northbridge_ow_rebuild_2026_9 --create
   ```

6. After it reports `fresh-v11-install-verified`, set only DB_NAME in your private
   .env to that new name, then start the local portal:

   ```sh
   php -S 127.0.0.1:8000 -t .
   ```

   Open http://127.0.0.1:8000/ and check your assigned role's pages. The account
   records come from the existing fictional account seeds; do not share another
   developer's credentials or database/mail settings. Registration and other
   unfinished actions remain disabled as documented in PROJECT_HANDOFF.md.

The installer verifies every input file hash, loads the existing base schema and
supplemental tables in dependency order, keeps foreign keys enabled, loads final
rosters before triggers, then verifies schema/trigger fingerprints and all 29
academic tables. The old hardcoded USE line is removed only in memory for the
new target; the base schema definition is unchanged. The package was verified on
MySQL 9.6.0. Other server versions and MariaDB have not been certified; a fingerprint
failure needs investigation rather than bypassing the check. A failed new install
is retained for inspection and cannot be overwritten by rerunning the command.

Git attributes preserve LF endings for SQL and the trigger source so hashes stay
consistent across checkouts. No Python planner, private candidate files, local
database dumps or /private/tmp paths are required for the normal installer.

## Existing installation

Do not run the seed SQL over an existing database. Back up your existing data
privately. Install this release into a new database, review the result, and switch
your local connection only when ready. Preserve any changed accounts or academic
records explicitly. The V11 installer never migrates or overwrites an old target.
The older timeline/master-schedule repair scripts are not the V11 installer.

Operational logs, password-recovery requests and Phillip's main-server settings
are not copied by this seed package. It reproduces the academic release and
shared fictional reference data, not every local operational record.

## Work on your feature

Create a branch for your section of the project and submit a pull request into
main after the release is merged. Coordinate shared includes, CSS, navigation,
migrations and the handoff. See team-workflow.md. Do not regenerate or overwrite
shared academic seeds as a side effect of ordinary feature work.
