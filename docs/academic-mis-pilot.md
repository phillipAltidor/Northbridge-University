# MIS degree-evaluation pilot

> Historical MIS milestone: all 24 evaluators have since been implemented. See the [all-program report](academic-all-programs.md) for current version, coverage and limitations. The counts below describe the earlier MIS milestone.

Implemented November 7, 2026, in `northbridge_ow_2026_test`. Working portal configuration, student programs, accounts, grades and legacy tables are unchanged.

## What changed

The MIS B.S. now has a tested backend degree evaluator. It shares institutional credit, GPA, transfer, proficiency and GE evaluation code with CIS, while separate entry points reject the wrong program. Other 22 program evaluators remain review-required.

The MIS evaluator checks five major groups:

| Requirement | Credits / choice |
|---|---|
| Required computer science | Nine courses / 36 credits |
| Required business | Four courses / 14 credits; BU3502 and BU3511 remain 3 credits each |
| Economics and analytics | Any two distinct courses from MA2701, PE2420, PE2430 / 8 credits |
| First math group | One of MA2300, MA2310, MA3030 / 4 credits |
| Statistics group | One of MA2000, MA3210 / 4 credits |

The major requires 66 credits, with C or better in each counted required course. The full degree still requires 120 credits, institutional GPA 2.0, 40 local credits, 45 upper-division credits, 60 liberal-arts credits, an 80-credit transfer maximum, upper-major requirements, writing/math proficiency and reviewed GE evidence. Transfer admission also requires 28 local upper-level major credits across seven courses. Sources are retained in the [MIS program evidence](../database/staging/academic-alignment/source-pdfs/program-management-information-systems-b-s.pdf) and staged institutional rules; the economics selection is the user's approved project decision.

Repeats cannot fill two economics slots. A passed major group is not the same as completing the degree. In-progress work is reported but earns no completed credit. Transfer equivalencies, course attributes, credit exceptions and proficiency claims need scoped recorded approval; the pilot trusts a reviewed registrar adapter, not browser-supplied approval objects.

MA2701's supplied description explicitly requires MA2000 AND MA2300 with C or better in each. The source-checked transcription now represents that rule instead of a parser review gate. A valid alternative in a degree group does not automatically replace the named prerequisite for another course. The original source text and hash remain in the prerequisite record. Business courses listing “EMS” still require explicit scoped proficiency evidence; the service does not guess that Composition II alone satisfies that token.

## Version and validation

The current immutable package is catalog ID **4**, SHA-256 `246c38706b372892dc54352ce0af90134f624ed10d2ee9d5e5667978dc0dd9a3`. IDs can have gaps because reruns use insert-if-absent; do not infer the number of packages from the ID. The earlier package at catalog ID 1 is retained, including its previous MA2701 review gate and MIS evaluator status. No historical definition was rewritten. Rerunning the new import retained ID 4 and did not duplicate its 24 programs or 87 groups.

- **65 MIS scenario checks pass:** all 18 combinations of economics pair and math/statistics choices; C-minus rejection in all 17 counted courses; exact C boundary; repeated economics courses; mixed 3-/4-credit values; 118 versus 120 total credits; residency boundaries; approved transfers; writing; unknown attributes; MA2701 prerequisites; MIS standing; and EMS evidence.
- **61 CIS regression scenarios pass** after extracting the shared computing-major engine.
- **1,849 database checks pass:** full current-package round trips, both synthetic degree evaluations, the updated analytics prerequisite, earlier-package retention and unchanged working/copy fingerprints.
- **174 rule-package checks pass.** There are now 411 fully transcribed prerequisite records and 469 with review gates; the six missing credit values and EL3811 metadata gap remain.

Synthetic fixtures contain invented approvals and course attributes to test behavior. They are not official GE rulings, actual course classifications or student evaluations. No synthetic person/account/transcript rows were inserted into either database. No live registration or portal integration was added.

## Run checks

```sh
php tests/academic-cis-pilot.php
php tests/academic-mis-pilot.php
python3 database/staging/academic-alignment/validate_rules.py

# Requires access to the local test database:
php tools/load-academic-test.php --all-programs
php tests/academic-database.php --all-programs
```

The repository requires an explicit catalog ID; it never silently moves a student to a newer package. See the [current implementation receipt](../database/staging/academic-alignment/test-implementation-receipt.json) and [original CIS milestone](academic-cis-pilot.md) for the input contract and broader test architecture.

Next, expand the program-specific evaluator tests to the remaining majors and minors before connecting a protected registrar adapter and portal preview. Source review, official term schedules, individual impact reports, restoration rehearsal and synchronized installation seeds remain required before working migration.
