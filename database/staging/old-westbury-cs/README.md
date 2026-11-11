> Historical pilot / first-pass collection. The current 24-program review and corrected course extraction are in [academic-alignment](../academic-alignment/README.md); use the [November 7 migration plan](../../../docs/academic-data-migration-plan.md) for current scope. Earlier generated comparisons and entry counts are not approved import definitions.

# Computer Science Reference Pilot

Status: draft, review only. Nothing in this directory is an import or migration.
Northbridge's Computer Science major is being compared with Old Westbury's
Computer & Information Science B.S., not assumed to be the same curriculum.
Start with [COMPARISON.md](COMPARISON.md) for the reviewed course-by-course summary.

## Build

```sh
php database/staging/old-westbury-cs/build.php /private/tmp/old-westbury-fall-2026-complete.json
php tests/old-westbury-cs-staging.php
```

The builder validates the complete 913-section public Fall 2026 source, extracts
all CS offerings plus the mathematics courses mentioned in the supplied requirement
screenshots, and reads our academic tables in a read-only database transaction.
It never creates a database, imports courses, changes seed files, or reads student
profiles, enrollment records, grades, passwords, or accounts. An optional second
argument selects a different output directory. Generated files are ignored by Git.

## Artifacts

- `review.json`: manually reviewed candidate topics and provisional requirements.
- `generated/manifest.json`: counts, source checksum, provenance, missing offerings.
- `generated/reference-sections.json`: original public section records for this pilot.
- `generated/reference-courses.json`: course summary, decoded titles and section CRNs.
- `generated/northbridge-academic-snapshot.json`: current CS courses, requirements,
  prerequisites, Fall sections, and periods; no personal records.
- `generated/candidate-mappings.json`: proposed counterparts, all unapproved.

Keep the complete original download separately; generated files are reproducible
only with that input and the current Northbridge academic database. The checksum
identifies the original snapshot. Seat counts are historical reference values,
not live Northbridge seat counts, and must never overwrite our enrollments.

## Review Findings

1. Topic matches are not academic equivalencies. Programming II versus OOP is
   ambiguous; our Data Structures and Algorithm Design both overlap CS3810.
   Neither mapping authorizes renaming or double-counting completed courses.
2. CS subject results include offerings outside this undergraduate pilot. Do not
   import every CS offering or infer degree level from course number alone.
3. On November 7, 2026, the user approved CS3911 as Northbridge's tenth required
   core course (4 credits). CS3910 is not an approved alternative. This is a project
   decision; the linked catalog edition's missing core entry remains unverified.
   Existing generated artifacts predate this decision and need rebuilding.
4. The current program overview links to catalog `catoid=11`, whereas the current
   Courses & Catalogs index links to `catoid=19`. The catalog could not be fetched
   during this review. Confirm the catalog year and official program requirements
   before treating the screenshot's 68-credit model as approved audit logic.
5. A course absent from Fall offerings may still be required or offered another
   semester. Missing offerings are not deleted requirements.
6. Our editor currently assumes 90/120/180-minute meetings; Old Westbury's regular
   undergraduate 100-minute blocks need revised validation after approval.
7. `section_No` is an integer, capacities are fixed at 10, and modality is not
   modeled. These differ from real section labels, capacities and delivery modes.
8. A section has one room and faculty member. Separate day and period lists create
   a Cartesian product in schedule queries; they do not describe arbitrary
   day/time/location-specific meeting blocks. Review this before importing.
9. Flat Major_Requirements rows cannot directly express choose-three electives,
   OR choices, catalog versions, general education and overlapping credit rules.

## Approval Gates

Confirm professor scope and catalog year; verify course descriptions, prerequisites,
degree groups and minimum grades; approve mappings and any schema changes; then
create an isolated test database with fictional assignments and enrollments.
Only after testing and explicit approval should a backed-up working database change.
Existing students, grades, historical CRNs, users and other majors remain unchanged.
