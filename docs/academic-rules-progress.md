# Academic rules — current progress

Updated November 7, 2026. Full staged coverage is ready for isolated testing with explicit review gates. Official source questions are not all resolved. The working database and portal remain unchanged.

The [replacement-data rehearsal](academic-rebuild-status.md) now contains all
fourteen independently checked semester schedules in the isolated test database.
The next step is resolving applicable academic conditions and evidence, course
classifications, and actual degree/minor results before portal integration.

See the [current checklist](academic-rules-checklist.md) for program coverage, limitations and next steps.

## Decisions completed

| Item | Northbridge rule | Evidence / approval |
|---|---|---|
| CS major missing core course | Require CS3911, 4 credits; no CS3910 alternative. | User approved. |
| MIS economics/analytics | Any two DISTINCT courses from MA2701, PE2420 and PE2430, totaling 8 credits. | User approved using catalog course names and official advising choice structure. |
| Marketing minor | Require BU3800, BU4851, BU4820 and BU5880; choose one of BU5841/BU5891. Five courses, 20 credits. | User approved the clearer official program-page structure. |
| Education core missing 8 credits | Add ED4050 and ED5925 from the 2020–2022 course versions, 4 credits each, producing nine core courses / 36 credits. | User approved an explicit project exception. Older ED5925 title is Building a Classroom Community for All Learners, distinct from the newer course definition. |
| Three missing credit labels | AS4402, AS5412 and BU5171 are 4 credits each. | Corroborated in the supplied 2026–2027 program PDFs; no project exception needed. |
| General Business minor total | Keep minimum 18 credits AND the five-course rule; the listed 15-credit core plus a 4-credit elective yields 19. | Minimum means a floor, not an exact total to force. |
| Recommendations | MA2320 is recommended for MA3210; do not require it as a prerequisite. | Selected course description. |
| Psychology B.S. liberal-arts minimum | 60 credits; retain inherited Psychology major groups and B.S. math/science additions. | User approved the standard B.S. threshold as an explicit project decision. |
| CS5810 prerequisites | CS4550 AND either MA3210 or MA2000 AND English Composition II proficiency. | User explicitly approved the official advising-guide rule to fill the catalog omission. |
| CS5910 writing prerequisite | Completed English Composition I and II with C or better, or approved equivalent/placement evidence satisfying the full writing proficiency, plus the listed CS prerequisites. | User approved; original truncated source condition is retained as evidence. |
| Transfer grade token TC | TC represents a transferred C-grade result; still requires an approved equivalent course. | Official degree-audit guide, page 6. The separate CS5910 writing requirement is now resolved by explicit user approval. |

## Work produced

All 24 program definitions are in `rules/curricula.json`. There are 879 normalized course metadata records, 880 prerequisite version records (411 fully transcribed and 469 containing review gates), 535 GE approval rows and 40 explicit former-code relationships. Historical Education versions remain separate. The full build passes 174 checks.

The separate `northbridge_ow_2026_test` database is created and verified against all 47 source tables and three triggers. Seven additive versioned academic tables are installed; all 24 definitions are loaded. All 24 program evaluators are implemented, covering 37 track/path scenarios and 306 boundary assertions. CIS/MIS regressions pass 61/65 checks and database verification passes 1,928 checks. Source/evidence review gates remain. See [implementation details](academic-all-programs.md). [Verification receipt](../database/staging/academic-alignment/test-database-receipt.json).

## Remaining work

The Admin-only synthetic read-only preview is implemented and verified across 111 pages (367 checks). A private impact inventory covers all 3,338 existing students. See [preview and inventory](academic-preview.md). Review mappings individually and implement the protected real-student registrar adapter next. Resolve affected review gates before unattended registration or graduation decisions. Six credit values and EL3811 metadata remain incomplete; matching original CS/MA PDF provenance has not been reverified. Official semester schedules, individual student impact review, restore rehearsal and synchronized installation seeds remain later migration steps.

Psychology progression uses the supplied individual-course prerequisites, per the user’s clarification. No additional Psychology policy document is requested. Use the collected B.A. supporting PDF for inherited requirements and the approved 60-credit B.S. liberal-arts floor.

## Rebuild

```sh
python3 database/staging/academic-alignment/build_all_rules.py
```

This command does not connect to a database or network. It runs normalization, full curriculum construction, prerequisite transcription, institutional/GE finalization and validation in dependency order. Running only the older normalization script does not reproduce the final package.

## Comprehensive course/student review and semester import

All 240 legacy course definitions and all 2,200 undergraduates have a candidate/gap review; 1,138 graduates remain excluded. The 3,852 candidate program/path diagnostics are limited projections, not verified degree audits. Placeholder descriptions prevent approval of equivalents (238 of 240 descriptions are generic). All 913 official Fall 2026 sections and 936 meetings are in inactive test tables, excluding real faculty/contact/location data. Checks pass: 15,254 mapping and 6,471 official section comparisons. Working data is unchanged. See [the current report](academic-migration-review.md).
