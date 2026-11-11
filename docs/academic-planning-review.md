# Academic planning review

The earlier rehearsal met technical constraints but could select a course because
it appeared in a combined target list, without distinguishing required work from
an elective alternative or preparation. It is not approved for migration.

`database/generation/academic_planning.py` now compiles the reviewed requirements
for all 12 majors and 12 minors, including 37 concentration/path combinations.
The supplied CIS, MIS and Mathematics pages publish sample sequences. For other
programs, planning is derived from prerequisites and requirements; it is not
presented as an official published semester sequence.

Each proposed enrollment records a purpose: required major/minor course, a needed
elective choice, preparation for a required course, college writing, missing GE,
an outstanding liberal-arts/upper-division distribution, or a residual degree
credit budget. A load shortage alone does not justify a filler course.

Required courses reserve distinct elective allocation correctly; allocation
parents share child credits. One prerequisite route is prepared for a required
choice, rather than preparing every optional alternative. Entry placement opens
MA1020 without treating MA2000 as its automatic prerequisite. An auxiliary math
course is not added beside required major math merely to fill GE or minor prep.
Two mathematics courses that actually serve required major progression remain
possible, subject to their prerequisites. Composition I and II remain sequential.

The planner reserves pending work for planning purposes only. Fall 2026 remains
in progress and Spring 2027 remains preregistered; neither receives final grades
or earned credits. The independent prerequisite and degree engines remain gates.

The current aggregate report is
`database/staging/academic-alignment/academic-planning-review.json`.
It explicitly lists incomplete minimum loads. Structural rule coverage and
valid resource assignments are not approval of every student's academic plan.
No new trial candidate has been written to either portal database or installation
seeds. No schema change has been made.

The latest private two-term trial has 802 sections and 7,654 assignments with no
independent timetable/resource errors. PHP and Python prerequisite results agree
on all 42,336 attempts; all 34,682 past attempts are preserved. Thirteen planning
tests, 22 eligibility tests and eight entry-placement checks pass. However,
22 Fall plans and 132 Spring plans remain below their recorded enrollment-type
minimum load. These are unresolved planning/resource gaps, not evidence that
the official schedule cannot support a solution. The candidate remains blocked.

## Friday and single-day sections

The screenshot's ART10501 and ART10506 Friday rows are preserved legacy graduate
sections, outside the undergraduate rebuild. Their four-credit, two-hour meeting
patterns have not been verified against an official source and require separate
graduate review. They must not be treated as proof that the undergraduate
alignment is complete.

By comparison, the supplied Fall source has on-campus AS2901 CRN 92282 on Tuesday
09:50–13:10 and CRN 92283 on Wednesday 14:40–18:00. Those are complete 200-minute
single-day patterns. Validation must compare all meetings and the instructional
method with the source, rather than reject a section just because it meets once
per week. Graduate sections have not been edited during this review.

2026-11-09 correction: the owner approved retaining verified official course
patterns while preferring two 100-minute Mon/Wed or Tue/Thu periods for custom
four-credit lectures. Both Northbridge-only Fall MC4802 sections previously borrowed
a Friday block from VA2010; they were replaced in V11 with verified two-day periods.
Those remain explicitly fictional Northbridge offerings, not official MC4802
sections. Official meetings and documented lab/studio patterns were preserved.
