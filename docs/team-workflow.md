# Working together on the portal

Read PROJECT_HANDOFF.md first. It identifies the current implementation, approved
decisions, database versions, verification results and unfinished work. Update it
on your feature branch after each request and meaningful change.

## What this checkpoint shares

This GitHub checkpoint shares the current portal code, tests, reviewed source
material, documentation and the existing fictional installation seeds. It does
not switch any database. Pulling code does not copy Phillip's local MySQL data.

The verified preview uses V11 plus campus and faculty-count overlays in a private
test database. Repository installation seeds are an older snapshot, not that
exact preview state. Private dumps, student-level planning artifacts, environment
settings and credentials remain outside Git. Do not activate an older migration
or overwrite an existing database to make it resemble the preview. The matching
V11 installation bundle still awaits the separately reviewed cutover process.

For a fresh disposable database, review database/LOAD_ORDER.md and its snapshot
limitations. For an existing database, preserve it and review the handoff before
applying any migration. Keep a private backup before approved database work.

## One branch for each feature

Start with a clean working tree. Bring your local main branch up to date, then
create a feature branch. For example:

```sh
git switch main
git pull --ff-only origin main
git switch -c feature/faculty-attendance
```

Implement and verify the feature, update the handoff, then commit the specific
files you changed and push the branch:

```sh
git add faculty/attendance.php PROJECT_HANDOFF.md
git commit -m "Implement faculty attendance entry"
git push -u origin feature/faculty-attendance
```

The example filename is illustrative; attendance entry is not implemented by
this checkpoint. Use your actual changed files. Open a GitHub pull request from
your feature branch into main. Another teammate should review its behavior,
manual requirements, tests and database impact before merging. After a merge,
teammates pull main again before starting their next feature.

For an existing feature branch, commit your current work, then incorporate main
without rewriting a shared branch's history:

```sh
git fetch origin
git merge origin/main
```

Resolve conflicts by preserving the intended behavior from both features, run
the relevant checks, and commit the merge. Do not force-push main or discard a
teammate's changes to resolve a conflict.

## Coordinate shared files

Separate role features usually belong under student/, faculty/, or admin/.
Before editing shared includes, navigation, assets/css/style.css, configuration,
schema, migrations or seed files, agree who will make the shared change. One
teammate can prepare a shared helper first and merge it before dependent work.
Use small pull requests with one feature each.

Keep both useful handoff updates when merging that file, then reconcile its
current-state and remaining-work sections. Earlier passing tests do not verify
a later candidate or someone else's database.

Each developer keeps a private .env and their own local database. Install PHP
dependencies with composer install. Python generation dependencies are listed
in database/generation/requirements.txt and belong in an isolated environment;
ordinary PHP feature work does not require regenerating academic data. Do not
share generated caches, vendor directories, database passwords or mail secrets.

## Before merging a feature

- Match the system manual and approved project decisions; leave unfinished
  actions disabled until their checks exist.
- Verify role and record ownership, relevant behavior and error cases.
- Run relevant syntax checks and tests. Database/HTTP tests require their named
  isolated fixture or preview; inspect each test's guards before running it.
- Document any schema/data migration separately. The owner has prohibited schema
  changes; request a decision if a feature cannot fit the existing design.
- Record what was implemented, tested, and actually deployed in the handoff.

Assigning advisors, attendance entry, grading, registration and calendar windows
remain separate features. Their navigation should not imply that pulling this
checkpoint implements them. GitHub publication and database activation are
different operations.
