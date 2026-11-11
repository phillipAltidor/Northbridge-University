# Northbridge academic data alignment plan

> Current implementation status (November 7, 2026): the owner authorized rebuilding fictional undergraduate histories. The corrected isolated draft covers 2,200 undergraduates and 43,153 attempts, with all fourteen semester schedules independently checked (4,939 sections). All 47 working portal tables remain unchanged. Academic evidence and portal/seed integration still block activation. See the [current rebuild status](academic-rebuild-status.md). The earlier legacy-equivalency candidate review remains an archival diagnostic, not the replacement-data strategy.

Reviewed November 7, 2026. Planning and source staging only: no working database, application behavior, accounts, enrollments, grades, or historical records were changed by this review.

## 1. Outcome and agreed scope

Use the 2026–2027 Old Westbury undergraduate catalog for the 12 selected majors and 12 selected minors. Use the actual schedule for each imported semester for course offerings, section labels, CRNs, and every meeting day/time. Catalog requirements do not establish semester offerings.

Keep Northbridge branding, fictional students, faculty, staff, accounts, and personal details. Assign our fictional faculty and rooms to the official meeting patterns. Do not import real instructors, student records, emails, or contact information. A reference source can contain such fields; the importer must allow only approved academic fields.

The user approved **CS3911, C++ and Object-Oriented Programming, 4 credits**, as Northbridge's tenth CS core requirement. CS3910 is not an approved alternative. This is a documented project decision, not a claim that the catalog's nine-item core list has been corrected by Old Westbury.

The user also approved two explicit Northbridge decisions on November 7, 2026: MIS economics/analytics requires any two distinct courses from MA2701, PE2420 and PE2430 (8 credits); Marketing minor requires BU3800, BU4851, BU4820 and BU5880 plus one of BU5841/BU5891 (five courses, 20 credits). These decisions are recorded in `rules/project-decisions.json` and do not establish historical course equivalencies.

The 12 undergraduate majors are CIS B.S.; MIS B.S.; Business Administration B.S.; Accounting B.S.; Mathematics B.S.; Biological Sciences B.S.; Physics B.S.; English B.A.; Psychology B.S.; Childhood Education 1–6 B.S.; Public Health B.S.; and Media & Communications B.A. The 12 minors are CIS; General Business; Accounting; Marketing; Mathematics; Environmental Studies; Physics; Multicultural U.S./British Literature; Psychology; Public Health; Media and Communications; and Visual Arts.

Source collection is substantially complete for this undergraduate scope. **Collection is not verification of every rule or readiness for import.** Individual source defects, prerequisite dependencies, concentration rules, and semester schedules still need work. Graduate curricula were not supplied and are outside this undergraduate migration.

## 2. What the review established

| Working database, read-only audit | Count |
|---|---:|
| Students | 3,338: 2,200 undergraduate; 1,138 graduate |
| Faculty / accounts | 350 faculty; 3,723 user and login records |
| Existing courses | 240; all currently assigned 4 credits |
| Sections / semesters | 4,854 across 14 semesters |
| Enrollments / student history rows | 44,433 / 37,187 |
| Attendance rows | 16,616 |
| Departments / majors / minors | 10 / 12 / 12 |

The old PDF parser counted some prerequisite links as course headings. The corrected review dataset has **879 source entries, 878 distinct course codes across 20 prefixes**. SY2222 occurs twice with different titles. These are candidate source entries, not 878 approved courses to import. The corrected parser also preserves suffixes such as ED5940B.

Forty-two matching PDFs have been archived with source hashes. The original CS and MA course-description PDFs were overwritten at a shared Downloads filename; their earlier extracted text and hashes survive. Re-archive matching originals before final source verification. The ten official general education approval tables were fetched and retained separately, including current versus legacy columns and effective-date text.

The [program review and decision register](academic-data-review.md) describes every selected major/minor and the source defects. The machine-readable audit and source register are in `database/staging/academic-alignment/`.

## 3. Migration strategy: build beside the current data

Create a separately named local test database, proposed `northbridge_ow_2026_test`. Keep the current portal on its working database until the trial migration, restore rehearsal, and portal checks pass. Migration tools must reject an unexpected database name and default to a dry run.

Preserve the current schema/data as a legacy academic version. Add versioned program and course structures; do not change the meaning of old IDs by renaming them in place. Graduate students currently share all 12 major records with undergraduates, so an in-place major rename would change their displayed academic identity too.

Retain fictional person IDs, passwords, roles and profiles. Any private clone or backup containing authentication records stays outside committed artifacts. Only academic source files and aggregate audit results belong in this staging directory.

Use separate, explicitly labeled synthetic test students first. Existing fictional students transition only after an individual impact report and reviewed mappings. Shared course titles alone do not prove equivalency.

## 4. Ordered implementation steps

### Step 1 — Finish the source and decision register

Already done: inventory 24 program PDFs; extract course references; archive available matching sources; collect GE tables; audit working academic counts and code assumptions.

Next outputs:

- Resolve each blocking item in the review register, recording source, exact wording, decision, scope, and status.
- Restore the matching original CS/MA PDFs to immutable source filenames; do not ask the user to recopy every course.
- Obtain separate Education disciplinary-concentration rules ; Psychology uses the supplied course prerequisites for its progression reference, as clarified by the user.
- Review AS/MC aliases, missing prerequisite codes and duplicate SY2222. Treat numeric ranges and XX9990 as selectors/placeholders, not new catalog courses.
- Obtain term-specific schedule sources. The retained pilot has only 34 CS/math section records from an older full-download manifest; the full 913-section input is no longer present locally.

Exit condition: each record is either approved for a stated use or explicitly excluded/pending; no unresolved rule silently becomes an executable rule.

### Step 2 — Build curriculum definitions before changing students

Create one reviewed curriculum definition for each major/minor, with degree type, catalog edition, concentrations, required groups, alternatives, credit/course minimums, grade conditions, residency, transfer limits, and overlap rules.

Do not flatten elective lists into required courses. Encode nested AND/OR, choose-N, minimum-credit groups, lecture-plus-lab bundles, and category restrictions. Track program concentrations separately from the 12-major count. A recommended concentration for initial fixtures can be selected later; do not assign all students to one by assumption.

Maintain course-level classification and GE/liberal-arts attributes from sources. A business course's BU prefix alone does not decide whether credits qualify as liberal arts; program headings and approved attributes need review. A 5000 course is not automatically graduate: several supplied B.S. requirements explicitly include these courses. Conversely, an undergraduate catalog filter includes some advanced Education entries whose degree eligibility still needs checking.

Exit condition: a human-readable matrix and machine-readable rules agree, including exceptions and source decisions.

### Step 3 — Extend the schema and compatibility layer in the test database

| Area | Required change |
|---|---|
| Catalog and provenance | Add catalog versions, immutable source records, review status, aliases/equivalencies, and effective dates. Preserve official code separately from an internal ID/version. |
| Courses | Use full descriptions (`TEXT`); allow fixed/variable credit bounds and section/attempt credit snapshots; retain full official names and alphanumeric codes. Current descriptions are limited to 500 characters. |
| Programs | Add degree type, academic level, curriculum version, concentration and student-program assignments. Separate undergraduate versions from existing graduate/legacy records. |
| Requirements | Add nested requirement groups, eligible course sets, counting/grade policies, residency/transfer/overlap conditions and approval evidence. Keep legacy flat lists for compatibility until queries migrate. |
| Prerequisites | Store rule trees for course/grade conditions, AND/OR, standing, program admission, placement/proficiency, concurrent study and permission. Preserve original source text. |
| Course attributes | Store versioned GE domains, liberal-arts classification, lab status and relevant distribution categories. |
| Sections | Add internal section ID; preserve official CRN as text; enforce uniqueness within the source term/context. Preserve text section labels, modality, part-of-term dates, status and actual registered credits. |
| Meetings | One record per exact day/date/time combination; support multiple blocks, no scheduled time, online and TBA. Keep local room/faculty assignments separate from imported public meeting data. |
| Academic attempts | Establish a single attempt identity referenced by enrollment/history/attendance; keep original course/version/credits and all grades. Include origin/residency, transfers, repeats and approved substitutions. |
| Calendar and approvals | Store term and registration boundaries in America/New_York; log overrides, substitutions, placement results and manual completion evidence. |

Keep the 10 Northbridge departments initially. Add a reviewed many-to-one mapping from Old Westbury subject prefixes to these departments. A prefix is not itself a department; replace the current one-prefix-per-department assumption in `config/course-prefixes.php`. Keep office/contact/chair data fictional. Verify fictional faculty teaching eligibility and workload after academic reassignment.

Exit condition: migration DDL is rehearsed on a disposable database, all references are migrated consistently, and compatibility queries preserve existing portal behavior.

### Step 4 — Implement prerequisite eligibility

Return explicit results: eligible, conditionally eligible, ineligible, or needs review. Include the exact missing rule and evidence for every result.

Examples from the supplied sources:

- CS3620/CS3810/CS3911: CS2511 **and** MA3030; apply grade thresholds only from the applicable reviewed rule.
- CS4501/CS4720: C or better in CS2511 **and** C or better in either CS3810 **or** CS3611.
- CS4100: junior standing in CIS **or** MIS, **and** ECII.
- CS5910: retain course-grade alternatives C/TC. The user approved completed English writing proficiency (Composition I and II with C or better, or approved equivalents/placement) to replace the incomplete writing condition; preserve the source fragment in provenance.

The supplied academic policy allows prerequisites to be in progress during advance registration and allows instructor permission to supersede stated prerequisites. Model this as documented conditional eligibility and authorized overrides with rechecks before the target term; do not mark an in-progress course completed or silently grant permission. A missing prerequisite statement is not proof that a course has no prerequisites.

Exit condition: reviewed fixtures cover AND/OR, grades, repeats, transfer equivalents, placement, standing, concurrency, conditional enrollment and permission outcomes.

### Step 5 — Implement degree evaluation

Produce separate completed, in-progress, planned, remaining, and needs-review results. The overall result must reflect the entire degree, not just the major.

Evaluate: exact program/concentration groups; declared minors only; total earned credits; major and institutional GPA; upper-division and liberal-arts totals; GE and local proficiencies; residency and transfer caps; repeats and overlap; required internship/fieldwork/approvals.

Use applicable exceptions: Accounting specifies 126 total credits and institutional policy specifies 46 residency credits; B.A. liberal-arts minimum is 90 versus 60 for B.S.; Psychology minor GPA is 2.5; English permits at most one C- in the major; Biology has a limited D-grade exception for specified non-biology requirements. These rules must not become university-wide defaults.

GE requires at least 30 distinct earned credits as well as approved domain coverage. A course may satisfy approved multiple domains, but its credits count once in the degree total. GE D-/CR satisfaction is separate from C-level math/writing proficiency and program minimum grades. Honor approval effective dates and student-entry cohorts. Preserve the AA/AS GE waiver separately from local proficiency requirements.

Keep all transcript attempts. Institutional GPA generally uses the latest repeated grade; Psychology's B.S. math/science clause explicitly references highest grades for its particular calculation. Do not reuse one repeat policy for both. Reconcile Enrollment and Student_History so one completed attempt is not counted twice.

Exit condition: hand-calculated expected evaluations match the application, and no program with an unresolved mandatory rule can show an unconditional complete result.

### Step 6 — Import official sections and assign fictional resources

Start with Fall 2026 CIS and supporting courses. Then expand to all selected undergraduate programs; obtain Spring 2027 independently. A course can belong in the catalog without having a section that term.

For each section preserve the official course identifier/title context, CRN, section label, actual meeting days/start/end times, modality and date ranges. Import all blocks, linked lecture/lab relationships, cancellations and cross-list groups where applicable. Decide whether section-specific titles supersede catalog titles for schedule display without rewriting the catalog record.

Assign fictional instructors/rooms without changing official times. If resources are insufficient, produce an unassigned/conflict report or add explicitly fictional resources; do not import real faculty or silently move meetings. Check dates as well as weekly times so non-overlapping parts of term do not produce false conflicts.

Retain the project's current 10-seat capacity and 16/8 credit-limit rules pending an explicit policy decision. Old Westbury public capacities, enrollments and available-seat snapshots must not overwrite Northbridge rosters. Cross-listed sections require a shared-capacity policy if offered. Faculty workload and advising rules also remain project policies unless deliberately changed.

The existing 90/120/180-minute validation must be replaced with source-backed meeting validation. Update the Master Schedule editor, schedule queries, student/faculty views, Week at a Glance, overlap checks and seat triggers together. The existing day-list × period-list joins can invent meetings; switch to exact meeting rows.

Exit condition: normalized source-to-database comparison has zero unexplained mismatches and all three role views show the same meetings.

### Step 7 — Reconcile existing students and histories

For each undergraduate produce a private impact report: old/new program, curriculum/concentration, mapped and unmapped completed courses, retained grades/credits, remaining requirements, valid current/future registrations, conflicts, holds and derived standing.

Proposed subject changes such as Cybersecurity → MIS and Engineering → Physics are program changes, not equivalent curriculum renames. The same applies to changed minors. Never apply them simply because the old and new records occupy the same numeric slot. Resolve `Student.major_ID`, `Student_Major`, `Student_Minor`, subtype department and advisors consistently.

Calculate earned credits from the reconciled attempt ledger; classify freshmen 0–31, sophomores 32–56, juniors 57–87 and seniors 88+ if adopting the supplied standing policy. Do not replace stored standing until credit reconciliation is complete.

Do not rewrite historical CRNs or attach prior grades to newly named courses. Preserve 14-term legacy history; label its source/version. Matching those historical schedules to Old Westbury requires each term's real source and a separate reviewed migration. No Fall schedule may be copied into the historical terms. Keep 1,138 graduate students on legacy records until a graduate curriculum plan is supplied.

Exit condition: every student is either explicitly migrated with a reviewed impact report or remains on a clearly identified legacy version; there is no mixed, misleading degree evaluation.

### Step 8 — Validate, rehearse rollback, and cut over

Run schema/FK and attempt-integrity checks; source comparison; prerequisite/degree fixtures; no-time/irregular/linked/cross-listed schedule tests; faculty/room/student conflict checks; capacity/concurrency/rollback checks; login and role-authorization regression; and existing profile/schedule tests adapted to the new schema.

Compare protected identity/account records before and after using private checksums without logging credentials. Reconcile counts and every planned changed row. Verify calendar boundaries, attendance cutoffs, ungraded in-progress terms and future terms without fabricated results.

Before changing the working database, review the exact dry-run diff and rehearse a full restore in a separate database. MySQL DDL can auto-commit: use a tested backup/schema restore or blue/green database switch, not a promise that a transaction reverses all schema changes. Keep a cutover window and roll-back trigger; reject stale migrations if the working data changed after the approved snapshot.

After cutover, update schema, academic seeds, supplemental migrations, `database/LOAD_ORDER.md`, documentation and tests as one version. Install/reconcile seat triggers at the documented point in the load sequence; preserve protected account state. Verify a fresh installation produces the same academic data as the migrated one.

## 5. Recommended first implementation milestone

Build **one isolated CIS major/minor pilot** using the approved CS3911 decision, supporting math/writing rules, synthetic test students, a degree evaluation and authentic Fall 2026 sections. This validates the architecture before expanding to the other 11 majors/minors.

In parallel, resolve remaining source defects in Education and other affected rules. Psychology uses the supplied prerequisite definitions for the progression reference. MIS economics/analytics and Marketing group contradictions now have explicit user-approved decisions. An unresolved item blocks the affected rule/program, not unrelated source cleanup or the entire pilot. Remaining course sources are requested only when the dependency audit proves they are necessary.

The next work is source normalization and a concrete schema proposal. This document does not authorize applying a working-data migration; it makes that later change specific and reviewable.

## 6. Supporting review artifacts

- [Program review and decision register](academic-data-review.md)
- [Machine-readable source audit](../database/staging/academic-alignment/source-audit.json)
- [Source register and archive status](../database/staging/academic-alignment/source-register.json)
- [Corrected course extraction](../database/staging/academic-alignment/reviewed-course-extraction.json)
- [Official GE approval tables](../database/staging/academic-alignment/general-education-source-tables.json)
- [Previous planning notes](academic-data-migration-plan-previous.md)

## Phase 1 progress — November 7, 2026

The staged rules and status are in `database/staging/academic-alignment/rules/`. The user approved MIS economics/analytics as any two distinct courses from MA2701/PE2420/PE2430 (8 credits) and Marketing minor as BU3800/BU4851/BU4820/BU5880 plus one of BU5841/BU5891 (20 credits), in addition to CS3911. Three unlabeled course-credit values were corroborated from selected program PDFs: AS4402, AS5412 and BU5171 are each 4 credits. Six course-credit gaps remain.

The official degree-audit guide identifies the T prefix on transfer grades. The user separately approved completed English writing proficiency to replace CS5910's truncated writing condition. The Education concentration/admissions and Psychology BA policy sources have been archived for reconciliation. The user approved restoring the Education core to 36 credits with the 2020–2022 versions of ED4050 and ED5925. These versions are preserved separately; concentration and prerequisite reconciliation remain pending. No test database or working-data migration has been created during this phase.
