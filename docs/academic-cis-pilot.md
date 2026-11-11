# Versioned curriculum and CIS pilot

> Historical CIS milestone: MIS has since been implemented and the current package is catalog ID 4. See the [MIS implementation report](academic-mis-pilot.md) for current counts and the 22 remaining program evaluators. The counts below describe the earlier CIS-only milestone.

Implemented November 7, 2026, in `northbridge_ow_2026_test` only. The portal remains on `northbridge_university`. No existing student has been moved to a new curriculum.

## Installed data

Seven additive `NB_` tables store an immutable, content-hashed catalog package, course versions, program versions, ordered requirement groups, prerequisite trees, general education source approvals and academic policies. They do not reference or rename legacy major, course, transcript or person IDs.

| Item | Loaded |
|---|---:|
| Catalog packages | 1: 2026–2027, catalog ID 1 |
| Program definitions | 24: 12 majors and 12 minors |
| Requirement groups | 87 |
| Course versions | 880 |
| Prerequisite definitions | 880 |
| GE source approval rows | 535 |
| Policy documents | 3 |

The 879 metadata candidates become 880 course versions after preserving both historical Education versions and treating the canonical ED4050 lookup as the approved historical version rather than a duplicate definition. ED5925 retains separate current and historical identities. Missing credit values remain null. Source review gates survive import.

The importer first loaded CIS only. After its scenario and database checks passed, the remaining program definitions were loaded. Only the CIS B.S. is labeled with the pilot degree evaluator; the other 23 degree definitions remain `review-required`. The pure prerequisite service understands the tested node types and returns review-required for unsupported or incomplete nodes.

## Pilot behavior

- Prerequisites preserve AND/OR alternatives and stated minimum grades. A known OR alternative may satisfy a rule even if another alternative needs review. Unspecified prerequisite grade thresholds use the institutional passing floor; the CIS graduation C minimum is a separate check.
- In-progress prerequisites can produce conditional eligibility only when the supplied target term is later than their completion term. Recheck before registration becomes final. Same-term work does not satisfy a prior-course condition.
- Transfer courses require scoped, recorded equivalency approval. A TC result is treated as transferred C only with that approval. CR can earn ordinary credit but does not prove a C minimum.
- Writing proficiency requires distinct Composition I and II evidence. Approved placement/equivalent evidence is scoped to its component. A junior standing prerequisite means junior or higher, using the reviewed completed-credit standing context.
- The CIS audit checks its core/elective/math groups, distinct completed credit, C minimum, local and upper-level residency, the transfer cap, institutional GPA, math/writing proficiency and reviewed GE evidence. In-progress work never earns completed degree credit.
- Repeated credits count once. Qualification and institutional GPA use the latest final attempt; I/W/NR do not erase a prior final grade. A previous pass followed by a failure is flagged for earned-credit review rather than inventing a repeat-credit policy. Transfer grades do not enter institutional GPA.
- Credits must match staged catalog bounds or have a scoped credit exception. Upper-division and liberal-arts attributes require reviewed evidence; they are not inferred from an unverified field.

## Input contract and limits

This is a **backend pilot**, not a student-facing feature or permission to graduate students. `includes/academic/evaluator.php` accepts reviewed registrar data. Do not pass browser/request JSON into it. `Evidence::approved` checks the structure and scope of an approval record; it is not an authentication or authorization system. A later registrar adapter must retrieve approvals from protected records and verify the approver’s authority.

Attempts use canonical versioned course meanings, completed/in-progress status, actual credits, local/transfer origin and explicit integer term/attempt ordering. The later legacy adapter must review equivalents and build these records; raw legacy course IDs are never automatically mapped. Course attributes and proficiency evidence are supplied by that reviewed adapter.

GE rows are imported with their source conditions intact. The pilot does **not** automatically approve all 535 rows. Its GE evaluator consumes separately reviewed, course-scoped evidence identifying domains, cohort/effective-date review and a source reference. A waiver requires a documented AA/AS award from SUNY/CUNY and scoped approval; it does not waive local writing or math proficiency.

Synthetic fixtures use invented approval records and attributes to exercise the engine. They are not official classifications for the sample courses, real transfer rulings or an actual student graduation evaluation. No synthetic accounts or transcript rows were added to either database.

The 470 prerequisite review-gated records, six missing credit values, EL3811 metadata gap and CS/MA PDF provenance limitation described in the [rules checklist](academic-rules-checklist.md) remain. They have not become verified merely by being loaded.

## Validation

- **61 scenario checks:** grade boundaries, alternatives, conditional and same-term eligibility, chronology, repeat grades and credits, transfer equivalencies, scoped writing/placement evidence, standing, credit bounds, residency, local GPA, unknown attributes, GE overlap/waivers and unsupported rules.
- **1,844 database checks:** full source-to-database round trips for all 880 course versions and prerequisite records, all programs/groups/policies/GE rows, database-backed synthetic CIS graduation and CS5910 eligibility, plus unchanged working/copy legacy data.
- Repeated import retained catalog ID 1, all 24 definitions and 87 groups without duplication.
- All 47 original and copied legacy tables still match the original snapshot by row counts and extended checksums. Portal configuration was not changed.

The earlier rule-package validation also passes 174 structural/source checks; it is separate from the pilot scenarios and database round trips.

## Commands

```sh
# No database access:
php tests/academic-cis-pilot.php
python3 database/staging/academic-alignment/validate_rules.py

# Local test database only; requires local MySQL access:
php tools/load-academic-test.php --all-programs
php tests/academic-database.php --all-programs
```

The loader rejects an unexpected working connection and connects explicitly to the test database. Changed source packages receive a new catalog package hash instead of overwriting old definitions. The read-only repository requires an explicit catalog ID; it never silently chooses the newest version for a student.

The original private snapshot remains outside the repository. This additive migration is not a completed restoration rehearsal or a durable production backup strategy.

## Next work

Implement and test the remaining program-specific evaluators, a protected registrar/legacy adapter, and portal read-only previews. Then import official semester schedules, produce individual student impact reports, rehearse restoration and update installation seeds before any working migration. Keep schedule CRNs/meetings separate from catalog course requirements.

See the [implementation receipt](../database/staging/academic-alignment/test-implementation-receipt.json).
