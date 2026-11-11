# Academic Data Alignment Plan

Status: planning and reference staging only, October 27, 2026.
No production import, schema migration, or student record changes are authorized
by this plan. Execution proceeds through the review gates below.

## Scope

Retain Northbridge's existing 12 majors, 12 minors, 10 departments, branding,
role portals and fictional users as the working scope. There is no verified
requirement in the reviewed local docs to reproduce every Old Westbury program;
the professor's project manual and meeting transcript remain authoritative.
Program count alone does not establish requirements compliance.

| Major ID | Major | Department ID |
| --- | --- | --- |
| 1 | Computer Science | 1 |
| 2 | Cybersecurity | 1 |
| 3 | Business Administration | 2 |
| 4 | Accounting | 2 |
| 5 | Mathematics | 3 |
| 6 | Biology | 4 |
| 7 | Engineering | 5 |
| 8 | English | 6 |
| 9 | Psychology | 7 |
| 10 | Education | 8 |
| 11 | Health Sciences | 9 |
| 12 | Media Studies | 10 |

Existing minors: Computer Science, Cybersecurity, Business Administration,
Marketing, Mathematics, Environmental Science, Engineering, Creative Writing,
Sociology, Education, Public Health, and Digital Media. Do not derive minor
requirements from major requirements or assume all minors are required to graduate.

Keep IDs stable. The broad Engineering, Education and Health Sciences names
need specific curriculum/degree definitions. Every other program also needs a
verified counterpart; similar names do not establish equivalency. Where a direct
Old Westbury counterpart is unavailable, obtain approval for an alternative
reference or a clearly defined Northbridge curriculum. Do not invent equivalencies.

## Pilot and Boundaries

First pilot: undergraduate Computer Science, with its mathematics dependencies,
applicable degree-wide rules, and the Computer Science minor as a separate
requirements model once an official source is available. A CS student is not
automatically assigned a CS minor; same-field major/minor restrictions need review.
Use separate fictional test profiles for minor scenarios as appropriate.

Other programs stay unchanged during the pilot. Shared courses and prerequisites
must be checked against affected programs, particularly Cybersecurity. Graduate
programs remain unchanged and must not inherit undergraduate degree rules.

## Source and Decision Register

| Decision | Current position | Required before import |
| --- | --- | --- |
| Programs | Existing 12 majors and 12 minors | Verify project scope with professor/manual |
| Pilot | Undergraduate CS | Review exact reference program and degree |
| Catalog year | Unconfirmed | Approve one catalog edition and source URL |
| Semester reference | Public Fall 2026 schedule downloaded | Keep retrieval provenance; obtain other terms separately |
| Degree rules | Include major and applicable school-wide requirements | Approve exact rules, exceptions and category overlap |
| Minor policy | Evaluate separately when declared | Verify overlap, minimum grades and graduation policy |
| Course mappings | All draft/unapproved | Descriptions, credits, levels and prerequisite review |
| CS3910/CS3911 | User approved CS3911 as Northbridge's tenth required core course on November 7, 2026; CS3910 is not an approved alternative | Record as a project decision, not a verified correction to the catalog |
| FT/PT credit limits | Preserve existing 16/8 project limits | Change only if professor approves |
| Capacity | Existing fixed 10; real capacities differ | Decide whether fixed 10 remains or becomes configurable |
| Faculty rules | Preserve original workload, department and advising limits | Verify assignments per semester |
| Historical records | Preserve grades and original identifiers | Approve explicit equivalencies/versioning if needed |
| Class standing | Stored year retained for now | Approve credit thresholds and calculation policy |

The schedule does not supply a complete catalog, prerequisites, official degree
level for every offering, transfer policy or graduation rules. No degree-level
classification may be inferred solely from course number. Screenshot examples
are evidence for review, not approved policy.

## Phase 1: Catalog and Requirement Mapping

Deliverables: source register, course comparison, prerequisite graph and program
requirement matrix. For every course record its code, title, description, credits,
level, department, source/catalog version and approval status. Distinguish required
courses from courses offered this semester.

For each major/minor record required groups, elective counts/credits, OR choices,
minimum grades and permitted overlap. Record total degree credits, GPA, general
education, upper-division, liberal arts and residency rules only where applicable
to that degree. Specify transfer, repeat, withdrawal and in-progress treatment.

Gate: approve curriculum and mappings; no automatic title-based course replacement.

## Phase 2: Schema and Impact Review

Deliverables: proposed schema changes and per-table impact report. Review:

- Course identifiers/versioning and how completed records retain their meaning.
- Requirement groups, alternatives, catalog years and credit classification.
- Alphanumeric sections and CRN uniqueness across semesters.
- Meeting-specific days, periods, dates, room, instructor and modality.
- Fixed versus variable capacity; seat calculations and triggers must agree.
- Academic calendar, registration/drop/grading windows and time zone.
- Transfer/repeat handling and approved requirement overlap.

Trace dependencies through Course_Prerequisite, Major_Requirements,
Minor_Requirements, Course_Section, Enrollment, Attendance, Student_History,
Faculty_History, Student, Student_Major and Student_Minor. Assess saved schedule
snapshots, update logs, seed exports, tests and all portal queries as well.

Gate: professor/schema approval where required and a concrete rollback design.

## Phase 3: Isolated Database and Master Schedule

Create a distinctly named test database, never overwrite the working database.
Check its name explicitly before every write. Prefer synthetic student fixtures;
if an academic snapshot is necessary, exclude or neutralize credentials and email
delivery, keep it private, and never commit personal or authentication records.
Do not point the normal portal at this database during the experiment.

Build catalog and requirement records first, then Fall 2026 sections using verified
meeting patterns. Preserve original source identifiers in staging, and assign
fictional Northbridge faculty/rooms with conflict checks. Public instructor and
seat records are reference data, not authority to replace our fictional assignments.
Other semesters require their own verified schedule or an explicitly approved
synthetic schedule; do not copy Fall offerings into every term.

Gate: validate individual sections, meeting rendering, room/faculty constraints
and seat calculations before adding test enrollments.

## Phase 4: Student Alignment

Create scenarios for beginning, mid-program, near-complete and completed CS
students, including failed/repeated courses, unmet prerequisites and declared
minors. Keep earned credits separate from in-progress and future registrations.

For any eventual existing-student migration, produce a per-student impact report
before modifying data: applicable curriculum, preserved history, approved course
equivalencies, earned-credit recalculation, remaining requirements, current/next
enrollment validity and potential class-standing changes. Do not regenerate grades,
erase history or change students in unaffected programs merely because new courses
were added. Stored earned-credit inconsistencies require an agreed policy first.

Gate: review representative student reports and every unresolved exception.

## Phase 5: Validation

- Schema/FK integrity, valid references and no prerequisite cycles.
- Course-level eligibility, minimum grades, holds and 16/8 credit limits.
- Capacity, full-section rejection, transactional seat updates and rollback.
- No student, room or faculty time conflicts, including multi-block meetings.
- Faculty section, department and advising limits.
- Actual add/drop, attendance and grading window boundaries, including time zone.
- Transcript/history preservation and correct repeat/transfer/withdrawal handling.
- Correct completed, in-progress and remaining requirements; no credit double counting.
- Major completion alone cannot claim all academic graduation requirements complete.
- Existing role permissions, profile pages and unaffected programs still work.

Record pass/fail evidence and unresolved gaps. Planned/disabled workflows cannot
be counted as tested end-to-end until implemented in the isolated environment.

## Phase 6: Explicit Migration Approval

After successful tests, review the exact changed rows and tables, obtain explicit
approval, take verified private backups, rehearse restore, and apply a reversible
migration. Check for changes since staging; reject stale plans. Reconcile seed
exports and documentation, rerun integrity and role tests, then expand program by
program. Keep schema DDL recovery separate from transactional data rollback.

## Current Progress

Completed: full public Fall 2026 reference download; isolated CS staging files;
18 local undergraduate CS courses compared; 26 reference CS sections and 8 math
sections extracted; draft mappings and discrepancy report; 55 staging checks.

Not completed: confirmed catalog year, approved course mappings, authoritative
prerequisite/minor/school-wide matrices, schema approval, test database, changed
master schedule, student migration, or functional degree audit.

Next input: professor meeting transcript and the exact catalog program/year link.
Next work: resolve the undergraduate CS curriculum and prerequisite matrix before
proposing schema or working-data changes.

See [CS pilot comparison](../database/staging/old-westbury-cs/COMPARISON.md) and
[staging instructions](../database/staging/old-westbury-cs/README.md).

## Approved CS Core Decision — November 7, 2026

The user approved CS3911 — C++ and Object-Oriented Programming (4 credits)
as Northbridge's tenth required undergraduate CS core course. CS3910 is not
an approved alternative. The pasted program page
(https://catalog.oldwestbury.edu/preview_program.php?catoid=19&poid=1614&returnto=512)
contains nine core courses despite its 10-course/40-credit heading; its sample
sequence includes CS3911. This is an explicit Northbridge project decision, not
a claim that the linked catalog's missing entry has been independently verified.

The adopted core totals 40 credits; with 12 CS elective credits and 16 mathematics
credits, the major totals 68 credits. Prerequisites, general education and other
degree rules still require review. This approval does not authorize existing-course
equivalencies or changes to working student records. No database migration has
been applied. Generated staging artifacts predate this decision and must be rebuilt
from the original complete schedule source before being used for import review.
