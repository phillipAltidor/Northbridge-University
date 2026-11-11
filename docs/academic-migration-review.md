# Comprehensive migration review

Reviewed November 7, 2026. All requested course/program/student review scopes have been inventoried and compared. The full official Fall 2026 semester is imported into inactive test tables. **No working record was changed and no academic equivalency, student assignment or graduation result was approved.**

## Course and program review

| Scope | Result |
|---|---:|
| Legacy courses reviewed | 240 |
| Undergraduate courses compared | 180 |
| Graduate courses excluded/preserved | 60 |
| Generic placeholder descriptions | 238 |
| Undergraduate courses without a supported topic candidate | 30 |
| Legacy major/minor definitions reviewed | 24 |
| Explicit topic/credit assumptions usable for limited diagnostic scenarios | 29 |
| Verified/approved academic equivalents | 0 |

See the [complete course candidate table](legacy-course-candidate-review.md), [program choices and incompatibilities](legacy-program-candidate-review.md), and [detailed source comparison](../database/staging/academic-alignment/legacy-mapping-review.json).

The original database genuinely contains descriptions such as “Study of [course title], including foundational concepts, practical applications, and analysis of relevant problems.” Such a placeholder cannot establish equivalent content, assessed depth, lab completion or a target prerequisite chain. The two substantive descriptions also lack enough complete evidence to certify the entire target course. All candidate entries therefore retain original descriptions/credits/prerequisites, target source evidence/rule trees, credit mismatches, possible lecture/lab bundles and unresolved conditions.

Matching a title or lexical score is not approval. A legacy four-credit course is not changed into a three-credit course or split into a lecture/lab pair without evidence. CS2511 and CS3911 remain distinct alternatives for ambiguous object-oriented content; rejected CS3910 is not revived. Graduate courses are not converted merely because some undergraduate requirements use 5000-level codes.

All old program labels were considered individually. Cybersecurity has no same-program target among the selected 12 majors; CIS/MIS are possible program choices, not a rename. Engineering is not automatically Physics. Several old minors have no like-for-like target, and new Accounting/Psychology/Visual Arts minor IDs cannot replace old unrelated minors. All such declarations remain protected until a deliberate choice is reviewed.

## Every existing student reviewed

The protected report covers all **3,338 students**: **2,200 undergraduates** and **1,138 excluded graduates**. It reviews all **300 undergraduate minor declarations** and the primary/additional major declarations.

**3,852 candidate program/path diagnostics** were run through the test database's stored evaluators for the undergraduate candidates. Only 29 narrow topic/credit hypotheses were projected for those diagnostics. Unmapped original courses remain preserved and unevaluated; no general-education, attribute, proficiency, admissions, transfer or overlap approvals were fabricated. A completed historical enrollment is deduplicated against its matching history row; current/future ungraded enrollments remain in progress.

The diagnostic engine reports incomplete requirements under these deliberately limited inputs. **This does not mean that 3,852 students failed, lost credit, or received a final graduation deficit.** It means the projected subset is not a complete approved transcript mapping. Verified degree results and proposed assignments remain null for every student. No source title/grade is overwritten. History/enrollment grade-course comparisons found zero conflicts, and the narrow projected assumptions produced zero different-course target collisions.

Detailed inputs and outputs contain student IDs and academic grades, so they remain in the private 0700 directory identified by the [aggregate summary](../database/staging/academic-alignment/all-student-mapping-review-summary.json), with 0600 files outside the repository/web root. The public summary contains no individual student IDs or grades. Output is streamed to bound memory use. The directory is temporary local storage, not a durable backup.

## Official semester sections imported

The [official public class search](https://owsis.oldwestbury.edu/StudentRegistrationSsb/ssb/term/termSelection?mode=search) was queried for **Fall 2026 / term 202609**. Both result pages were retrieved: 500 + 413 = **913 sections**, including **936 meeting blocks**. Raw response hashes and paging provenance are retained in the [sanitized academic source](../database/staging/academic-alignment/official-sections-fall-2026.json).

The importer created three additive test tables: `NB_SectionImport`, `NB_OfficialSection`, and `NB_OfficialMeeting`. All original CRNs, string section labels with leading zeros, course titles, part-of-term values, exact times, dates and individual meeting-day booleans are preserved. Multiple blocks remain separate; no day-list × period-list cross product is invented, and the old 90/120/180-minute assumption is not applied to official meetings. The **176 sections without scheduled times** remain no-time/TBA source records.

**742 sections** link by exact code to a collected catalog version; **171** remain outside the collected catalog and explicitly require review. A code link alone does not certify academic level or program eligibility. The whole source semester is retained as reference; this is not a claim that all sections belong to the selected undergraduate programs. Older terms and Spring 2027 were not imported or fabricated from this snapshot.

Real faculty, IDs, names, emails, contacts, source buildings and source rooms are discarded before persistence. Working schedules, rosters and seat triggers are untouched. New test sections retain Northbridge capacity 10 and registration disabled. No fictional teacher/room assignment has been made: verified teaching eligibility, workload policy, suitable lab/room resources and conflicts against the preserved timetable must be resolved first. Official meeting times were not moved to hide these pending assignments.

See the [import receipt](../database/staging/academic-alignment/official-section-import-receipt.json).

## Validation

- **15,254 mapping review checks pass:** all course/program/student coverage, preserved source descriptions, graduate exclusions, no academic approvals, private file permissions and no verified student verdicts.
- **6,471 official schedule checks pass:** every section/meeting source comparison, string CRN/label identity, exact time/date/day values, duplicate/unsafe-field rejection, inactive registration, null faculty/room assignments and unchanged working/copy data.
- All 47 original and copied legacy tables continue to match their snapshot by exact counts/checksums. No migration was applied to the working portal.

```sh
# Read-only local database exports/projections:
php tools/export-academic-mapping-inputs.php
python3 database/staging/academic-alignment/review_legacy_mappings.py
php tools/project-student-mapping-review.php
php tests/academic-mapping-review.php

# Public source retrieval, then isolated test import/verification:
python3 tools/fetch-official-sections.py
php tools/import-official-sections.php
php tests/academic-official-sections.php
```

Re-exporting selects a new private report directory. Re-fetching a changed official source creates a new inactive import version rather than rewriting the earlier source snapshot. These tools do not approve a course equivalency or student transition.

## What is still required

1. Obtain meaningful prior course content or record a deliberate Northbridge transition policy before approving equivalents. Keep unverified legacy credits/history under their original meanings. Do not convert the top-ranked candidates automatically.
2. Review undergraduate major/minor/concentration choices and evidence individually. A verified real-student degree audit can follow only the approved mappings; diagnostic projections are not that approval.
3. Establish fictional teaching eligibility, workload and room suitability, resolve conflicts, and review the 171 outside-catalog/no-time cases before activating imported sections.
4. Complete restoration rehearsal, compatibility/permissions/schedule/seat checks and installation seed synchronization before a working migration.

Recommendation: keep existing students on their preserved legacy academic meanings while the official new curriculum and offerings are tested prospectively. Transition individuals only when their course and program mappings are supported; graduate records remain outside this undergraduate change.
