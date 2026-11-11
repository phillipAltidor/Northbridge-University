# Academic source review and decision register

> Current implementation status (November 7, 2026): comprehensive candidate/gap review covers all 240 legacy courses and 2,200 undergraduates, with 1,138 graduates preserved. 3,852 mapping-limited diagnostics are not verified degree verdicts. All 913 official Fall 2026 sections and 936 meeting blocks are imported into inactive test tables. No equivalencies/program assignments are approved and no working records changed. See [current review and limits](academic-migration-review.md).

Reviewed November 7, 2026. Source and schema review only. No working data changes.

This review covers all 24 selected program PDFs, the collected descriptions across 20 prefixes, the supplied academic policies, the ten official GE approval tables, and the current repository/schema plus a read-only local database audit. Full source text is retained. Automated dependency scanning is a first pass, not verification of every prerequisite expression. Pending rules are deliberately not approved import data.

## Major-by-major review

| Program | Required model / reviewed facts | Remaining conditions or issues | Source |
|---|---|---|---|
| Computer and Information Science, B.S. | 40 core + 12 CS electives + 16 math = 68 major credits; 120 degree credits. C minimum and 28 upper-level major residency credits for transfers. | Approved Northbridge CS3911 decision fills the missing 4-credit core slot; full GE and prerequisite rules remain separate. | [PDF](../database/staging/academic-alignment/source-pdfs/program-computer-and-information-science-b-s.pdf) |
| Management Information Systems, B.S. | Stated 66 major credits: 36 CS, 14 business, 8 economics/analytics, 8 math. Math has alternative groups. | RESOLVED BY USER: any two DISTINCT courses from MA2701, PE2420 and PE2430, 8 credits. This is an explicit Northbridge project decision, not a MA3250 equivalency. | [PDF](../database/staging/academic-alignment/source-pdfs/program-management-information-systems-b-s.pdf) |
| Business Administration, B.S. | 50-credit core plus a 20-credit track: Management, General Business, or Business Analytics. 120 degree credits. | Store one selected track; preserve track residency clauses. Do not require the union of all tracks. | [PDF](../database/staging/academic-alignment/source-pdfs/program-business-administration-b-s.pdf) |
| Accounting, B.S. | 40 accounting + 26 business + 22 named supporting credits; degree requires 126 credits. | Apply 46-credit institutional residency from academic policies. Accounting electives use credit minimums (some courses are 2 credits), not one universal course count. | [PDF](../database/staging/academic-alignment/source-pdfs/program-accounting-b-s.pdf) |
| Mathematics, B.S. | 40 core + 4 programming alternative + 12 electives = 56 major credits. C minimum; 120 degree credits. | Clarify placement of CS3810 in the elective group across the page break. Do not let a core course also fill an additional-elective slot without permission. | [PDF](../database/staging/academic-alignment/source-pdfs/program-mathematics-b-s.pdf) |
| Biological Sciences, B.S. | 66 departmental credits; separate lecture/lab courses; upper-level distribution and lab-bearing elective requirements. | C in biology; up to two D grades may satisfy specified chemistry/physics/math requirements. A Biology course may be repeated at most once. Preserve combined physics-track options. | [PDF](../database/staging/academic-alignment/source-pdfs/program-biological-sciences-b-s.pdf) |
| Physics, B.S. | 54 core credits + a concentration: General 14; Health Sciences 21; Quantitative Finance 31; Applied/Computational 19. | Store concentration and course/lab bundles. Level selectors such as MA4000 are not literal course codes. | [PDF](../database/staging/academic-alignment/source-pdfs/program-physics-b-s.pdf) |
| English, B.A. | 44-credit major; linguistic foundation, analysis, traditions, specialization, seminars; 120 degree credits and 90 liberal-arts credits. | At most one C-; transfer and repeat limits. Introductory text says two specializations, detailed section lists three. EL3811 appears in requirements but not supplied descriptions. Preserve distribution requirements across groups. | [PDF](../database/staging/academic-alignment/source-pdfs/program-english-b-a.pdf) |
| Psychology, B.S. | Core/foundation/elective structure plus pre-calculus or higher and four semesters of qualifying science. | Same-edition B.A. PDF received: 2.5 Psychology GPA using highest grades in eleven courses OR C in each of all eleven. User clarified the progression reference means the supplied course prerequisites; no extra department-policy document is required. User approved 60 liberal-arts credits for the B.S.; inherited major groups and B.S. math/science additions are retained. Math/science condition says GPA 2.5 based on highest grades OR C in every course; preserve exact scope and advisor approval. | [PDF](../database/staging/academic-alignment/source-pdfs/program-psychology-b-s.pdf) |
| Childhood Education 1-6, B.S. | 32-credit disciplinary concentration; stated 36 education credits; 12-credit ED5940 field experience with B minimum. | CORE RESOLVED BY USER: retain the seven listed courses plus the 2020–2022 versions of ED4050 and ED5925, 4 credits each, for 36 core credits. Separate concentration/admission/progression and historical ED5925 prerequisite issues remain. | [PDF](../database/staging/academic-alignment/source-pdfs/program-childhood-education-1-6-b-s.pdf) |
| Public Health, B.S. | Biology choice + eight core PH courses + three electives; 90-hour applied practice requirement. | Text says minimum 11 courses but listed groups require 12 if all apply. Biology heading says 3 credits while BS2100 is 4. Preserve senior research pre/corequisites and evidence of practice completion. | [PDF](../database/staging/academic-alignment/source-pdfs/program-public-health-b-s.pdf) |
| Media & Communications, B.A. | 48-credit grouped major, with studies, production, writing/research, electives, internship and capstone. | Major GPA 2.0; one low grade only by approval; one VA maximum; five transfer courses maximum and local production requirement. Only 4 internship credits apply to major. AS/MC alias differences need review. | [PDF](../database/staging/academic-alignment/source-pdfs/program-media-communications-b-a.pdf) |

## Minor-by-minor review

| Program | Required model / reviewed facts | Remaining conditions or issues | Source |
|---|---|---|---|
| Computer Information Science, Minor | 20 credits: four required courses + one eligible 4000/5000 CS elective; exclude CS4100 and CS5620. | C minimum; 16 credits outside major; at most two transfer courses. Math substitutions have stated minor-page scope. | [PDF](../database/staging/academic-alignment/source-pdfs/program-computer-information-science-minor.pdf) |
| General Business, Minor | Five courses, stated minimum 18 credits; four fixed courses and one elective. | Listed fixed credits sum to 15; usual listed 4-credit elective gives 19, not 18. Elective heading says four options but only three are listed. Do not invent a fourth option or force credits to 18. | [PDF](../database/staging/academic-alignment/source-pdfs/program-general-business-minor.pdf) |
| Accounting, Minor | 26 credits: 18-credit five-course core + 8 credits in an emphasis. | 20 credits outside major; at most two transfers. Options I and II list 10 candidate credits each: select approved 8, not all options. | [PDF](../database/staging/academic-alignment/source-pdfs/program-accounting-minor.pdf) |
| Marketing, Minor | Page says five courses; foundation heading says four; foundation list contains five. | RESOLVED BY USER: BU3800, BU4851, BU4820 and BU5880 plus ONE of BU5841/BU5891: five courses / 20 credits. Uses the clearer official program-page rule as an explicit Northbridge decision. | [PDF](../database/staging/academic-alignment/source-pdfs/program-marketing-minor.pdf) |
| Mathematics, Minor | 24 credits: four required math courses + two eligible electives. | C minimum; 16 credits outside major; at most two transfers. Count distinct courses when explicit options also match level selectors. | [PDF](../database/staging/academic-alignment/source-pdfs/program-mathematics-minor.pdf) |
| Environmental Studies, Minor | 20 credits: HI3001 core + four electives, including science and humanities/social-science coverage. | Do not count HI3001 twice because it also appears in elective list. Model lecture/lab bundles, variable credits, and department-specific independent study instead of literal XX9990. | [PDF](../database/staging/academic-alignment/source-pdfs/program-environmental-studies-minor.pdf) |
| Physics, Minor | At least 17 credits: 8-credit foundational physics/lab track + three electives, at least two upper-level. | Track alternatives must stay bundled. Astronomy lecture/lab pair is 4 credits; clarify how that pair counts toward the three-course elective condition. | [PDF](../database/staging/academic-alignment/source-pdfs/program-physics-minor.pdf) |
| Multicultural U.S./ British Literature, Minor | 20 credits: one course from each of five groups, ending in EL4800. | EL3811 missing in course descriptions. Check EL4800 prerequisites against the minor’s alternative analysis group; do not waive prerequisite courses to make the minor appear self-contained. | [PDF](../database/staging/academic-alignment/source-pdfs/program-multicultural-u-s-british-literature-minor.pdf) |
| Psychology, Minor | Five groups/approximately 20 credits; approved methods alternatives and foundation-domain selections. | Minor GPA 2.5; one 4000-level Psychology elective must satisfy residency. Degree page refers to department foundation lists. Keep course prerequisites separate. | [PDF](../database/staging/academic-alignment/source-pdfs/program-psychology-minor.pdf) |
| Public Health, Minor | 20 credits: four PH core courses + one eligible elective. | C in each course; at most one course shared with major; at most one transfer. Preserve hosted PY3020 exception to PH elective selection. | [PDF](../database/staging/academic-alignment/source-pdfs/program-public-health-minor.pdf) |
| Media and Communications, Minor | 20 credits: MC2802 + two studies electives + two production electives. | Production text says no more than one VA course but this minor’s production list shows no VA choices; do not import the major’s VA pool automatically. | [PDF](../database/staging/academic-alignment/source-pdfs/program-media-and-communications-minor.pdf) |
| Visual Arts, Minor | 20 credits: VA2020/2030/2045 plus one two-course option (art history, fine arts, or electronic media). | Art-history option uses an approved classification/level selector; it is not every VA3000+ course. Keep options separate. | [PDF](../database/staging/academic-alignment/source-pdfs/program-visual-arts-minor.pdf) |

## Decisions and missing-source work

| Priority / item | Recommended handling | What it blocks |
|---|---|---|
| P0: source immutability | Matching copies of 42 PDFs were archived. Retrieve original CS/MA description PDFs matching retained hashes; their Downloads pathname was later reused. Retained text is usable for planning. | Final provenance verification of those course imports, not the schema pilot. |
| P0: graduate isolation | Existing 1,138 graduate students share all 12 Major rows. Create versioned undergraduate programs; retain graduate/legacy assignments. | In-place major renaming and indiscriminate academic rewrites. |
| P0: historical course/section meanings | Preserve old identifiers, names, credit snapshots, grades and attempts; use explicit equivalencies and versioned assignments. | Blind rename/reseed migrations. |
| P0: remaining Education contradiction | MIS, Marketing and Education core decisions are now approved alongside CS3911. Education still needs concentration/progression and historical-prerequisite reconciliation. | Education completion evaluation. |
| P0: Education 32-credit concentration | Obtain Disciplinary Concentrations for Education Majors and relevant school admission/progression rules. | Complete Education curriculum and realistic test students. |
| RESOLVED: Psychology progression reference | Use the supplied major requirements and individual-course prerequisites as instructed by the user; no extra department-policy document. | No additional Psychology source request. |
| P0: real sections missing | Recollect official Fall 2026 and Spring 2027 schedules with all blocks and linked/cross-listed metadata; older pilot retained only 34 sections. Full old download is missing. | Institution-wide CRN/meeting alignment. |
| P1: concentration choice | Model and validate all detailed options; select a concentration per new synthetic test profile. Ask user only for actual scope restrictions, not routine data cleanup. | Student-specific audit allocation, not catalog storage. |
| P1: prerequisite abbreviations | Map ECI/EC1, ECII, EMS and placement conditions using policy sources. TC has been identified as a transfer-grade token in the official degree-audit guide. The user approved completed English writing proficiency to replace the unfinished CS5910 writing condition. | Those executable eligibility branches. |
| P1: AS/MC and old-code aliases | Keep source codes distinct until official rename/cross-list evidence supports an alias/equivalency with effective dates. | Confident translation of several media prerequisites and old history. |
| P1: missing credits / duplicate titles | Nine raw entries lack a labeled credit value. BU5171, AS4402 and AS5412 are corroborated as 4 credits from selected program PDFs; six remain unresolved. SY2222 has two different source titles. Do not default credits to 4 or use last-write-wins. | Those affected catalog records. |
| P1: GE cohort and effective dates | Use the current column for Fall 2023+ entrants and preserve dated approvals; store legacy column separately. Do not apply every rule to older students. | Correct general-education evaluation across cohorts. |
| P1: project policy conflicts | Keep 10-seat and 16/8 credit-limit rules until a deliberate project decision. Official math/English and degree policies do not silently override unrelated project constraints. | Any policy change in those areas. |
| P2: legacy schedules and graduate curricula | Need separate official sources and reviewed scope. Existing 14-term history remains identifiable legacy data. | Claiming the entire database, including graduates/past terms, matches Old Westbury. |

P0 means resolve before the affected import/cutover, not that every other task must stop. Unknown rules return needs review and cannot yield an unconditional graduation-complete result.

## Course and source audit findings

The first-pass regex extraction reported 899 candidates. Font-aware PDF heading checks excluded inline prerequisite cross-references and retained alphanumeric suffixes. The review dataset now has 879 entries / 878 distinct codes; it is not an approved full-catalog import. Original CS/MA PDFs still need matching-source archival.

- Variable-credit internships/research exist; ED5940 is 12 credits, science lecture/lab pairs are often 3+1, and Business includes 1-, 2-, and 3-credit courses. Current local courses are uniformly 4 credits.
- Some undergraduate-filter PDFs include advanced entries; a number alone must not decide academic level. Proposed course scope is the selected curricula plus their prerequisite/support/approved-elective closure, not every collected course.
- Blank prerequisites differ from explicit None. Permission, program admission, placement, standing, repeatability and term conditions must be represented rather than discarded.
- The description-length scan identifies hundreds of candidate entries exceeding the current 500-character field; change it to TEXT and preserve clean source text alongside display text.
- `EL2200–EL2299` is a range; `MA4000` is used as a level selector; `XX9990` is a placeholder. These must not be fabricated as literal required courses. `EL3811` is a genuine unresolved listed course.

### Missing labeled credit values

Raw gaps were AS3835, AS4402, AS5412, BU4190, BU5171, ML1100, ML1110, ML2100 and ML2110. BU5171, AS4402 and AS5412 are now corroborated as 4 credits from the selected program PDFs. Remaining gaps are AS3835, BU4190, ML1100, ML1110, ML2100 and ML2110. These are source/extraction gaps requiring comparison; absence of a label does not mean zero credits. BU4762 was an earlier false duplicate from a prerequisite link and is no longer in this missing-credit list.

### Unresolved prerequisite references

These are literal references not found among collected course codes. They can be old aliases, typos, out-of-scope graduate dependencies or genuinely missing sources. They are not automatically new courses to create.

| Referencing course | Unmatched codes |
|---|---|
| ED4220 | CE4200 |
| ED7022 | ED7001, ED7002, ED7003, ED7004, ED7005 |
| ED7023 | ED7001, ED7002, ED7003, ED7004, ED7005 |
| ED7026 | ED7001, ED7002, ED7003, ED7004, ED7005 |
| HI3752 | ENG1000 |
| MC4700 | AS3700 |
| MC4729 | AS2226, AS2251 |
| MC4935 | AS3870 |
| MC4940 | AS4442, AS4882 |
| MC4945 | AS4150 |
| MC4965 | AS4852 |
| PY4210 | PY2400 |
| PY5210 | PY2400 |
| BU4149 | BU5110 |
| BU4180 | MA3250 |
| BU4190 | BU3070 |
| BU5790 | BU4792 |
| BU5798 | BU5771 |
| CS5821 | CS4410 |

### GE mapping coverage

The source tables include approved courses outside our 20 collected prefixes, retired aliases and other dated records. Preserve full rows and only activate verified mappings for imported eligible courses. Never infer approved attributes from a course title or subject. A complete replica of every GE option would require further source collection; the selected-program pilot can use verified covered options.

- [Official LEC overview](https://www.oldwestbury.edu/division/office-academic-affairs/liberal-education-core)
- [Retained ten-domain table snapshot](../database/staging/academic-alignment/general-education-source-tables.json)

The Natural Sciences page has a copied US History heading/introduction despite Natural Sciences course rows. Use table rows and corroborating domain identity; record this source defect rather than copying its introductory text as policy. Written/oral communication mappings contain subdomain wording; verify that the selected courses cover both required outcomes instead of treating every row as identical.

## Current code/database impact

- `Course.course_Credits` is one integer, and every existing course is 4 credits. Add credit bounds and per-section/attempt credits; do not retrospectively recalculate old credits from a new catalog definition.
- `Course.course_DSC` is VARCHAR(500). Major/Minor lack catalog, degree and level; flat requirement and prerequisite tables lack nested conditions and source status.
- `Course_Section.CRN` is a global integer primary key; `section_No` is integer. Migrate references in Enrollment, Attendance, Student_History, Faculty_History, saved Master_Schedule data and Course_Section_Update_Log together when introducing internal section IDs.
- `Time_Slot_Day` and `Time_Slot_Period` are cross-joined in schedule queries. They cannot safely represent different times on different days, date-specific blocks or asynchronous offerings.
- `admin/edit-section.php` enforces 90/120/180-minute rules; `includes/schedule.php` and enrollment triggers assume capacity 10. New meeting/capacity models require coordinated editor/query/trigger changes.
- `Student.major_ID`, `Student_Major`, subtype department and advisor assignments must remain consistent. The current read-only audit found zero primary-major disagreements; retain that invariant.
- `Student.student_Year` is displayed as standing. Switch to earned-credit-derived standing only after resolving attempt credits and repeats; keep graduate year distinct.
- Degree Audit, registration, transcript and course-prerequisite navigation links are disabled. Importing data alone does not implement or test these workflows.
- Timeline configuration still identifies Fall 2026/current and Spring 2027/next without full term/registration boundaries. Preserve real date cutoffs; add verified calendar data before automated eligibility windows.

## Deliverables before a working migration

1. Versioned course/program/rule definitions, source decisions, prerequisite closure, and selected-course GE mappings.
2. Isolated database schema/compatibility migration, dry-run diff, backups and tested restore.
3. CIS pilot with hand-checked eligibility/degree fixtures and official semester meetings.
4. Reviewed student/program/equivalency impact reports; protected graduate/legacy data.
5. Verified remaining program rules and full intended term offerings.
6. Portal/query/trigger changes, source comparisons, regression checks and synchronized install seeds.

See the [ordered migration plan](academic-data-migration-plan.md) for execution details. No working migration was applied during this review.

## Phase-one implementation progress

`database/staging/academic-alignment/rules/` contains 879 normalized candidate course metadata records (including the approved historical ED4050), three source-backed credit corrections, 40 explicit former-code relationships, 27 transcribed pilot prerequisite entries, six program-group definitions (CIS major/minor, MIS major, Marketing minor, Education core, Psychology BS), and the eight explicit user decisions. These are staged definitions; unresolved policies remain inactive, and no database changes have been made.

The official Education concentration and admissions pages, Psychology BA policy page, advising worksheet, and degree-audit guide have been collected in `supplemental-sources/`. Their applicable-year and policy differences require reconciliation; collecting a page does not automatically override the selected catalog.

The user approved CS5910 completed English writing proficiency and CS5810 prerequisites (CS4550 AND either MA3210/MA2000 AND ECII). Both source exceptions are recorded with provenance; neither condition is inferred from missing text.
