# Live undergraduate degree audits

Degree Audit is available in **Student → My Records → Degree Audit**. Students
can view only their authenticated record. **Faculty → Advising → Student Degree
Audit** provides a name/ID search and a read-only undergraduate audit selector.
Graduate degree rules remain out of scope at the owner's explicit request.

The feature implements use case 10, **View Degree Audit**, and use case 46,
**View a Student's Degree Audit**, from the supplied
`/Users/phillipaltidor/Downloads/System Manual.docx`. The manual requires an
authenticated student to retrieve/display their own audit and an authenticated
faculty member to select a student and retrieve/display their audit. It forbids
students viewing other users' information. Audit views cannot edit records.

## Schema and source of truth

The base relational structure is in `database/schema.sql`. The versioned
academic additions are in
`database/migrations/2026-11-07-portal-academic-rebuild.sql`.

- `Student` and `User` establish student level, primary program and identity.
- `Student_Major`/`Major` and `Student_Minor`/`Minor` supply declared programs.
  Additional declarations are evaluated separately; an unconfigured program or
  concentration requires review instead of inheriting another program's rules.
- `Student_History`, `Enrollment`, `Course_Section` and `Semester` supply actual
  attempts and grades. Completed history is deduplicated against retained
  enrollment rows. Future registration is excluded from degree completion.
- `NB_AcademicCourse` supplies versioned course meanings, credit values,
  prerequisite expressions and reviewed classifications.
- `NB_AcademicProgram` supplies the full AND/OR program requirements, including
  elective choices, grade rules, concentrations and minor overlap policies.
- `NB_AcademicStudent` supplies scoped academic evidence. A grade or a page view
  never fabricates advisor, fieldwork, transfer or graduation approval.

Audits are computed from these records in a read-only transaction rather than
copied into a second mutable audit table. The previously verified evaluator is
unchanged. Current grades and approved evidence are re-read for each request.

## What students see

The page separates earned credits, current credits, planned credits and GPA.
Each declared program shows required courses, eligible alternatives, course
versions, applicable minimum grades, allocated courses and remaining checks.
College checks cover total/upper-division/liberal-arts credits, residency,
writing, mathematics, general education and other applicable program policies.
General education domains and the complete recorded course list are shown.
Minor credits and overlap use the completed-course major allocation.

The worksheet follows the supplied visual blueprint with a compact student summary,
collapsible requirement cards, course tables and a short status legend. Empty
history sections and redundant controls are omitted. Screenshots supply layout
only; their personal records are not imported.

Uncompleted courses without a current or future registration show prerequisites
in small text. Clicking any course opens an accessible dialog with its verified
description, prerequisite/corequisite expression, source attributes and general
education domains. Historical versions without a verified description say so
instead of displaying a newer course's description. Escape closes the dialog;
focus returns to the course button. Current and preregistered credits are shown
in parentheses and never become earned credits. Repeated courses remain visible
in history; liberal-arts and upper-division lists use latest final attempts.

`Needs review` preserves unresolved evidence. Passing an academic audit is not a
formal degree award. All declared major and minor results must be complete
before the overall page displays that requirements are met.

## Validation

- `php tests/degree-audit-data.php` recalculates and renders all 2,200
  undergraduate audits, compares results with the previously verified registrar
  evaluator, checks the programming/algebra progression case, and fingerprints
  academic/account tables to verify no mutations.
- `php tests/degree-audit-http.php` checks the local server on port 8000:
  all 12 majors, authentication, student isolation, faculty selection/search,
  malformed selectors, HTML escaping, read-only methods and graduate scope.
- Existing navigation and curriculum boundary tests cover the new links and
  the unchanged rules. The page has been inspected at desktop and a 390-pixel
  mobile viewport; wide course tables scroll inside their own containers.

Aggregate verification receipts are under
`database/staging/academic-alignment/student-degree-audit-*-verification.json`
and `student-degree-audit-verification.json`. No schema migration or academic
data rewrite is required for this feature.

## Entry placement policy

The owner approved writing and mathematics entry placement for every fictional
undergraduate on November 8, 2026. `config/entry-proficiency.php` records this
simulation policy; the worksheet displays both entry checks as satisfied. It
is not an imported high-school transcript or actual exam result. It grants no
credits, grades, Composition I/II equivalents, or specific math-course waivers.
College writing/math completion checks remain in the degree block and the
unchanged evaluator continues enforcing course prerequisites. Graduate records
are unaffected.

The degree summary shows minimum GPA and linked proficiency, general education,
major/minor, liberal-arts and upper-division statuses. Credit totals stay in
the header. Residency, transfer limits and the earned-credit check are retained
in a collapsed Additional degree requirements section. College writing/math
course checks appear separately below entry proficiency, with plain-language
requirements. A student without graded work sees GPA as not yet available.

General education uses the blue half-circle in the summary and section header
when qualifying domain courses are currently in progress, or preregistered when
only future courses qualify. The legend explicitly says requirements remain.
This presentation does not change the evaluator's earned-credit checks or
graduation result; completed domain rows still require passing final work.

The owner restricted the undergraduate schedule rebuild to verified On Campus
sections on November 8, 2026. The source-offering filter excludes hybrid, remote,
online and unknown modalities. Original source files and official meeting times
remain unchanged. Offering gaps are recorded in
`database/staging/academic-alignment/in-person-offering-review.json`.
This filter alone does not migrate current enrollments.

Missing general education rows display “Still need 4 credits in” followed by
the current Old Westbury Banner attribute and domain. Codes are taken from
the imported official attribute records: DVRS, MATH, COMW/COMO, ARTS, USCV,
GLBL, WLNG, NSCI, HUMN and SOCS. GMAT was not found in those source records.
This wording does not change the evaluator or assign credits to pending work.

Missing-domain attribute codes are links opening a dialog with the domain and
approved courses from the reviewed general-education mappings. The row lists
course codes only after the dash. The dialog provides titles and clickable
course details; membership does not promise eligibility or section availability.
