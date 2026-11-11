# Academic alignment review — November 7, 2026

Planning inputs only. These files do not change the working database and are not executable approval of course equivalencies, prerequisite rules or degree requirements.

- `source-register.json`: all 24 program sources and 20 course-description sources, SHA-256 provenance and archive status. Forty-two matching PDFs are archived in `source-pdfs/`. The original CS/MA PDFs were overwritten at a reused Downloads filename; earlier text/hashes survive and matching originals are needed before final import verification.
- `program-review-inputs.json`: full text of all 24 selected program PDFs from the matching archived versions.
- `reviewed-course-extraction.json`: 879 source entries, 878 distinct codes, 20 prefixes. Current PDF heading extraction uses font evidence to exclude prerequisite cross-reference links; preserves suffixes such as ED5940B. CS/MA still use retained earlier extracts.
- `source-audit.json`: reproducible first-pass dependency/credit/coverage audit plus aggregate read-only database baseline. Does not establish complete prerequisite logic, academic levels, or source approval.
- `general-education-source-tables.json`: academic table rows from ten official pages, including both cohort columns and dated wording. No personal-contact fields are normalized into these tables.

The earlier `old-westbury-cs` directory retains collection history and obsolete first-pass counts. It is not the current canonical import model. Review sources are kept separate from eventual approved curriculum definitions.

## Reproduce the audit

```sh
python3 database/staging/academic-alignment/audit_sources.py
```

This reads retained JSON only, updates `source-audit.json`, and never connects to a database or network. It verifies source count invariants and checks that known inline-reference false positives are excluded. Missing references can be selectors, aliases, typos or real gaps; no missing code is automatically inserted.

## Re-extract PDF sources on macOS

```sh
swift database/staging/academic-alignment/extract_pdf_courses.swift path/to/courses.pdf > /tmp/course-extraction.json
swift database/staging/academic-alignment/extract_pdf_pages.swift path/to/program.pdf > /tmp/program-pages.json
```

Both use Foundation/PDFKit; font-aware course extraction also uses AppKit. Compare the source hash against `source-register.json` before replacing any retained extraction. Do not overwrite the whole reviewed dataset with one PDF. Course boundaries, truncated source conditions and ambiguous prose still need review.

See [the plan](../../../docs/academic-data-migration-plan.md) and [program review](../../../docs/academic-data-review.md).

## Full rule package and isolated database

Rebuild with:

```sh
python3 database/staging/academic-alignment/build_all_rules.py
```

This local-only pipeline builds all 24 definitions in `rules/curricula.json`, 879 metadata records, 880 course/version prerequisite records, institutional rules and 535 GE mappings, then runs 174 validation checks. Do not run `normalize_rules.py` alone to rebuild the final package; it is only the intermediate pilot layer.

Eight user decisions and recommended interpretations are recorded separately from official evidence. There are 469 prerequisite records with review gates, six unknown credit values and one missing course option, EL3811. Unknown values are never inferred to be satisfied. Full coverage means every selected program has a definition, not that every source question is verified or every condition can run automatically.

`test-database-receipt.json` records the verified separate `northbridge_ow_2026_test` snapshot: 47 tables and three triggers match the working `northbridge_university` database. The working portal configuration was not changed. Seven additive academic tables are installed and all 24 definitions loaded; All 24 program evaluators cover 37 staged track/path scenarios; 306 boundary assertions, 61 CIS/65 MIS regressions and 1,928 database checks pass. Source/evidence gates remain review-required. An Admin-only synthetic read-only preview is integrated in the existing PHP portal (367 HTTP/read-only checks across 111 pages). No production registration or real-student academic mapping is enabled. See [preview and impact inventory](../../../docs/academic-preview.md). See [implementation and commands](../../../docs/academic-all-programs.md) and `test-implementation-receipt.json`. Private dumps stay outside the repository; temporary snapshots are not durable disaster-recovery backups.

See the [current checklist](../../../docs/academic-rules-checklist.md), [progress](../../../docs/academic-rules-progress.md) and `rules/phase-one-status.json`. Psychology progression uses supplied prerequisites; no extra policy PDF is requested.

`rules/evaluator-capabilities.json` binds all program registrations to the evaluator version and runtime hashes. Rebuild it with the full pipeline after code changes; the loader rejects stale runtime hashes.

## Legacy mapping review and official sections

`legacy-mapping-review.json` covers all 240 original courses and 24 old program definitions. `all-student-mapping-review-summary.json` describes the private 3,338-student review and 3,852 limited diagnostic scenarios. No academic equivalents/assignments/verdicts are approved. `official-sections-fall-2026.json` is the complete allowlisted academic projection (913 sections, 936 meetings), imported into three inactive test tables; all real faculty/contact/room data was discarded. See [review details and reproduction commands](../../../docs/academic-migration-review.md). New checks pass: 15,254 mapping assertions and 6,471 official section/source checks.
