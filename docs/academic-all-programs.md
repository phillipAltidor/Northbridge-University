# All selected major and minor evaluators

Implemented November 7, 2026, in `northbridge_ow_2026_test`. All **12 selected majors and 12 selected minors** now have backend evaluation implementations. The working portal, accounts, people, grades, schedules, enrollments and historical course meanings remain unchanged.

## Program coverage

| Program | Implemented and tested behavior |
|---|---|
| Computer and Information Science B.S. | Required core including approved CS3911, three distinct CS electives, math choices, C grades, residency and full degree requirements |
| Management Information Systems B.S. | CS/business core, mixed 3-/4-credit values, two distinct economics/analytics choices, math/statistics alternatives, C grades and residency |
| Business Administration B.S. | Management, General Business and Business Analytics tracks; choose one, prevent cross-group reuse, enforce published local track minima |
| Accounting B.S. | Separate accounting/business/supporting groups, 2-credit electives, 126-credit degree and 46-credit institutional residency |
| Mathematics B.S. | Required core, programming alternatives, selector-based electives excluding already-used core courses, C grades and residency |
| Biological Sciences B.S. | Both complete physics/lab paths, distinct upper-elective distribution, six-course AND 24-credit floor, additional lab-bearing elective, approved low-grade exceptions and repeat limits |
| Physics B.S. | All four concentrations, complete lecture/lab core, numeric selectors and distinct additional courses |
| English B.A. | All three specializations, distinct traditions groups, historical/British/U.S./World classifications, one C-minus, transfer/repeat limits and 90 liberal-arts credits |
| Psychology B.S. | Eleven-course allocation across distinct foundation domains, local upper elective, highest-grade department alternatives, reviewed math/four-semester science plan and 60 liberal-arts credits |
| Childhood Education 1–6 B.S. | Approved historical course versions, B student teaching, formal admission evidence, field completion approval and the staged Mathematics concentration with transfer limits |
| Public Health B.S. | Biology choice using actual credits, required core, electives and documented 90 practice hours |
| Media & Communications B.A. | Study/production/elective groups, approved categories, local production, grade/GPA/transfer/VA/lower-level limits and capped credits applied to the major |
| Computer Information Science minor | Core, eligible elective exclusions, C grades, outside-major credits, transfer cap and scoped prerequisite substitutions |
| General Business minor | Five-course structure; actual 19 credits satisfy the 18-credit minimum |
| Accounting minor | All three emphasis paths, credit-based choices, outside-major credits and transfer cap |
| Marketing minor | Approved four required courses plus one elective, five courses and 20 credits |
| Mathematics minor | Distinct core/elective union, C grades, outside-major credits and transfer cap |
| Environmental Studies minor | Science and humanities/social-science coverage, lecture/lab units, four distinct elective units and approved home-department independent study; no literal XX9990 course |
| Physics minor | Both complete foundational paths; astronomy lecture/lab is one unit, with at least two upper elective units |
| Multicultural U.S./British Literature minor | Five separate groups and distinct courses; source-listed EL3811 remains unavailable without metadata review |
| Psychology minor | Foundation groups, local 4000-level elective and 2.5 minor GPA |
| Public Health minor | Required core, approved elective selector, C grades, maximum one shared major course and maximum one transfer |
| Media and Communications minor | Required foundation, two studies and two listed production courses; no invented VA substitutions |
| Visual Arts minor | All three Art History, Fine Arts and Electronic Media paths, with classification evidence for Art History |

This covers all tracks/paths currently staged for these programs. Education still offers the previously recommended Mathematics concentration only; no other Education concentration was silently invented or activated.

## Allocation and prerequisites

The allocator searches distinct course combinations across nested groups. It can backtrack when an early flexible group would otherwise consume a course needed by a later restricted group. Complete lecture/lab bundles count as one elective unit while contributing their actual credits. Additional eligible electives may fill a credit deficit where explicitly allowed. Reaching the 30,000-state search bound returns review-required, never automatic completion.

Major evaluation also checks institutional credit, GPA, residency, upper-division, liberal-arts, transfer, writing/math proficiency and reviewed GE requirements. Minor evaluation returns program completion separately: a completed minor does not mark a whole degree graduation-ready. Minor outside-major/overlap rules require the reviewed actual major allocation; there is no automatic mapping of existing student programs or joint optimization across multiple declared programs.

Applied program credits are separate from earned degree credits. For example, an 8-credit completed internship can earn all 8 degree credits while only 4 apply to the Media major; split internships share that cap. Courses cannot fill required slots with a zero-credit allocation. Institutional GPA and credit calculations continue to use the actual registrar transcript.

The prerequisite service supports source AND/OR choices, grades, standing, writing/proficiency evidence, explicit permission nodes and concurrent-course wrappers. Same-term in-progress work can satisfy a source corequisite condition conditionally; it does not satisfy an ordinary prior-course prerequisite. CIS minor math alternatives apply only in that program context and do not create degree/transcript equivalencies. Unsupported or missing source conditions remain review-required.

## Evidence and limits

This is backend test implementation, **not production portal or registration activation**. Inputs must come from a protected registrar adapter. Approval objects are evidence records; their structural validation is not an authentication system and they must not be accepted from browser/request JSON.

Manual conditions are enforced as evidence gates, including literature/course classifications, lab-bearing electives, production categories, home-department independent study, transfer equivalencies, exceptional grades, course permissions, major overlap, Education admission/field completion and Psychology science planning. Public Health practice hours and the structured Education/Psychology evidence are checked as well as the approval scope. Departmental GPA with transferred courses requires a reviewed include/exclude policy; the engine does not assume a source rule that was not supplied.

There are still **469 prerequisite records with review gates**, six missing credit values and the EL3811 metadata gap. Original matching CS/MA PDF provenance has not been reverified. These are source/evidence limits, not 469 confirmed errors. Unresolved options are not treated as no prerequisite, zero credits or an automatic equivalency. All official source questions are not resolved merely because every program has executable evaluation coverage.

## Validation and versioning

- **37 complete-case scenarios** cover every selected program and staged track/path.
- **306 all-program/boundary assertions** test completion, empty/failed/in-progress work, credit bounds and specific program restrictions, including additional Biology electives, Media credit caps and scoped prerequisite permissions.
- **61 CIS and 65 MIS regression checks** continue to pass after the shared institutional logic was extracted.
- **1,928 database checks** pass, including all 37 cases from stored rules, definition round trips, actual evaluator-code hashes, previous package retention and unchanged source/copy fingerprints.
- **174 source/rule-package checks** pass.

Fixtures contain invented approval records and course attributes. They demonstrate logic; they are not actual student evaluations, official course classifications, real GE rulings or permission to award a degree. No fixture account or transcript records were inserted into either database.

Current test package: catalog ID **7**, SHA-256 `af788468dafce856c4ef61e4d428a42730e488ec3e9e96f969b0861f0ec2b032`. It contains 24 programs, 87 groups, 880 course/prerequisite versions, 535 GE source rows and four policy documents. All program registrations use `curriculum-v1`. The new capabilities document binds registration to the tested evaluator source hashes. Changing that code requires rebuilding capabilities, retesting and importing a new immutable package. Repository callers specify a catalog ID explicitly; no student silently follows a new package.

All 47 working and copied legacy tables match the original private snapshot by row counts and extended checksums. Earlier catalog packages remain intact. The portal still uses `northbridge_university`.

## Reproduce

```sh
python3 database/staging/academic-alignment/build_all_rules.py
php tests/academic-cis-pilot.php
php tests/academic-mis-pilot.php
php tests/academic-program-boundaries.php
php tests/academic-all-programs.php

# Local test database only:
php tools/load-academic-test.php --all-programs
php tests/academic-database.php --all-programs
```

[Coverage results](../database/staging/academic-alignment/all-program-evaluation-coverage.json) · [Implementation receipt](../database/staging/academic-alignment/test-implementation-receipt.json)

Next: build the protected registrar adapter and a read-only portal preview, then import official term schedules, review individual student impacts, rehearse restoration and synchronize installation seeds before changing the working data.
