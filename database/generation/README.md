# Fall 2022 Cohort Rebuild

After any approved data rebuild/import, run the timeline audit in
`database/migrations/align_academic_timeline.php`. Its `--apply` mode aligns the
snapshot in `config/academic-timeline.php`, backs up the database, and synchronizes
the four academic seeds. The October 22, 2026 snapshot requires Fall 2026 to be
ungraded/in progress and Spring 2027 to remain planned, not completed.

This is realistic generated university data, not real student records. The
Fall 2026 study plans supply a comparable scale, while the original Fall 2022
students retain their courses and grades. Generated historical profiles have
locked accounts with random hashed passwords. Existing accounts are unchanged.

The rebuild targets Fall 2022 only. Supporting prerequisite records are added
before that term; those are not complete earlier-semester cohort transcripts.
Spring 2023 and later schedules are preserved and remain separate work.
Generated electives are balanced against department teaching capacity. Meeting
assignments use OR-Tools to avoid student, faculty, and room conflicts.

## Validation And Application

The scripts are CLI-only and restricted to the local `northbridge_university`
database. The source snapshot and proposed plans contain account information:
keep them private, never commit them. The generator expects the original
1,600-student population and deliberately refuses a second run after application.

```sh
python3 -m venv /private/tmp/northbridge-data-venv
/private/tmp/northbridge-data-venv/bin/pip install -r database/generation/requirements.txt
php database/generation/snapshot.php
/private/tmp/northbridge-data-venv/bin/python database/generation/rebuild_fall_2022.py --source /private/tmp/northbridge-source.json --output /private/tmp/northbridge-fall2022-plan.json
php database/generation/stage_fall_2022.php
```

Staging does not change the active database. Review its audit before explicitly
applying:

```sh
php database/generation/apply_fall_2022.php
```

Application verifies that the active database still matches the source snapshot,
backs up the database and affected seeds, applies within a transaction, reruns the
audit, and synchronizes repository seeds. Failure before commit rolls back the
database and restores seeds. The successful staging database is then removed.
The audit report is written to `database/reports/fall_2022_rebuild_audit.json`.
The cohort manifest identifies historical profiles separately from current ones.

## Remaining Semesters

`rebuild_remaining_semesters.py` builds Spring 2023 through Spring 2027, excluding
Fall 2026. It preserves the completed Fall 2022 rebuild and existing students'
recorded courses. Historical cohorts continue within their study duration; new
entrants fill the remaining places. Each semester has a 1,600-profile population
and 1,520 registered students. Valid earlier 16-credit loads can require more
sections than the Fall 2026 baseline. Spring 2027 uses planned enrollment, not
completed grades, and removes generated future attendance.

```sh
php database/generation/snapshot.php /private/tmp/northbridge-semesters-source.json
/private/tmp/northbridge-data-venv/bin/python database/generation/rebuild_remaining_semesters.py --source /private/tmp/northbridge-semesters-source.json --manifest database/data/historical_cohorts.json --output-dir /private/tmp/northbridge-semesters-plan
php database/generation/apply_remaining_semesters.php --stage-only
```

Staging is the default and does not change active data or seed files. Review the
per-semester results in `/private/tmp/northbridge-semesters-stage-audits.json`
and obtain approval before explicitly applying the approved plans:

```sh
php database/generation/apply_remaining_semesters.php --apply
```

Each term is staged, tested through exported SQL, backed up, committed separately,
and audited again after commit. Any failure stops the sequence. Earlier successful
terms remain committed. Fall 2026 sections, enrollment, history, attendance,
saved schedules, and section logs are compared exactly after every audit.
Existing personal and login rows are preserved, including password hashes.
Committed reports are stored in `database/reports/semester_*_rebuild_audit.json`.

For a reviewed partial application, `--terms=4` applies only Spring 2023. A later
resume requires a new private snapshot of the current database supplied through
`--source`, plus the explicitly selected remaining term IDs through `--terms`.
The script refuses protected term IDs, missing staging approvals, and stale data.
