# Academic rules and test database checklist

Updated November 7, 2026.

## Completed for isolated testing

- Defined all 12 selected majors and 12 selected minors under the 2026–2027 catalog version, including program choices, minimum grades, credit rules, transfer/residency limits and overlap conditions.
- Recorded all eight explicit user decisions and the additional recommended interpretations separately from official source text. Historical Education versions remain distinct. No transcript equivalency is inferred from a shared title or former code.
- Prepared 879 course metadata records, 880 course/version prerequisite records and 535 general education approval rows. A missing prerequisite label is not treated as “no prerequisite.”
- Passed 174 structural and targeted rule checks. These validate staged definitions and prerequisite fixtures; they do not establish a working production degree evaluator.
- Created `northbridge_ow_2026_test` from the configured local `northbridge_university` database. All 47 base tables match by exact row count and extended checksum; all three triggers are present. Source data was rechecked unchanged.
- Kept portal configuration, fictional identities, accounts, grades, enrollments and historical meanings unchanged. Seven additive academic tables now hold the versioned curriculum; the 47 copied legacy tables remain unchanged. See the [CIS pilot implementation](academic-cis-pilot.md).

## Recommendations adopted for the test model

Use Mathematics as the initial Childhood Education concentration, with the other collected concentration sources retained for later expansion. No existing student has been assigned this concentration. Preserve minimum credit totals alongside course counts, and count lecture/lab combinations as explicit bundles where specified. Store source title variants and historical course versions without rewriting old records. Conditions requiring advisor approval or missing evidence must return a review outcome.

## Remaining limitations

- **469 of 880 prerequisite records contain review gates.** They include missing labels, unresolved references, permissions and prose that cannot safely be converted automatically. This is a count of course/version records, not 469 confirmed source errors. Known alternatives remain usable when an unresolved reference occurs in a separate OR branch.
- Credit values remain unknown for AS3835, BU4190, ML1100, ML1110, ML2100 and ML2110. Do not invent four-credit defaults.
- EL3811 is listed as a program option but lacks complete course metadata. Keep it unavailable for unattended evaluation until reviewed.
- Original matching CS/MA course-description PDFs were overwritten at reused source paths. Retained earlier extracts and hashes support staging; matching PDF provenance has not been reverified. No repeat upload is required for the current test work.
- Semester-specific official CRNs and meetings still need the later schedule import. No catalog course list establishes an actual semester offering.

These limits block affected automatic academic decisions and working-data migration, not creation of an isolated test environment.

## Current implementation and next step

The versioned schema is installed in the test database. All 24 program definitions, 87 groups, 880 course versions/prerequisite records and 535 GE rows are loaded. All 24 program evaluators now cover 37 staged track/path scenarios; 306 boundary assertions, 61 CIS/65 MIS regressions and 1,928 database checks pass. Source/evidence gates remain explicit. An Admin-only synthetic read-only preview now uses the test database; a private impact inventory covers all 3,338 existing students. No existing-student mapping or production evaluation is enabled. See [preview details](academic-preview.md). See [implementation details and limits](academic-all-programs.md).

Next, review individual program/course mappings and build the protected real-student registrar adapter. Official schedules, student impact review and restoration rehearsal remain required before working migration.

## Selected programs

| Program | Type | Status |
|---|---|---|
| Computer and Information Science, B.S. | major | Staged with review gates |
| Management Information Systems, B.S. | major | Staged with review gates |
| Computer Information Science, Minor | minor | Staged with review gates |
| Marketing, Minor | minor | Staged with review gates |
| Business Administration, B.S. | major | Staged with review gates |
| Accounting, B.S. | major | Staged with review gates |
| Mathematics, B.S. | major | Staged with review gates |
| Biological Sciences, B.S. | major | Staged with review gates |
| Physics, B.S. | major | Staged with review gates |
| English, B.A. | major | Staged with review gates |
| Psychology, B.S. | major | Staged with review gates |
| Childhood Education 1-6, B.S. | major | Staged with review gates |
| Public Health, B.S. | major | Staged with review gates |
| Media & Communications, B.A. | major | Staged with review gates |
| General Business, Minor | minor | Staged with review gates |
| Accounting, Minor | minor | Staged with review gates |
| Mathematics, Minor | minor | Staged with review gates |
| Environmental Studies, Minor | minor | Staged with review gates |
| Physics, Minor | minor | Staged with review gates |
| Multicultural U.S./ British Literature, Minor | minor | Staged with review gates |
| Psychology, Minor | minor | Staged with review gates |
| Public Health, Minor | minor | Staged with review gates |
| Media and Communications, Minor | minor | Staged with review gates |
| Visual Arts, Minor | minor | Staged with review gates |

## Review and reproduce

- [Complete program definitions](../database/staging/academic-alignment/rules/curricula.json)
- [Current rule status and limitations](../database/staging/academic-alignment/rules/phase-one-status.json)
- [Recommended interpretations](../database/staging/academic-alignment/rules/recommended-interpretations.json)
- [Manual review register](../database/staging/academic-alignment/rules/manual-review-register.json)
- [Test database verification receipt](../database/staging/academic-alignment/test-database-receipt.json)

```sh
python3 database/staging/academic-alignment/build_all_rules.py
```

The rule build reads local staged evidence only and has no database or network access. Use this full command: running `normalize_rules.py` alone only rebuilds the earlier pilot layer and overwrites intermediate outputs. Private SQL snapshots and authentication records remain outside the repository in the receipt’s private directory; that temporary directory is not a durable disaster-recovery backup.

## Latest review and source schedule

The [comprehensive migration review](academic-migration-review.md) covers every legacy course, old program declaration and existing student, with a complete Fall 2026 source schedule import. It lists the remaining evidence/policy gaps, zero approved equivalencies and inactive faculty/room assignment status. No working conversion has occurred.
