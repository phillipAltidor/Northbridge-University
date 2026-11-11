# Spring 2027 minimum enrollment review

Reviewed November 9, 2026 against V11 in test database `_7`.

All 439 rebuilt Spring sections match the V11 database source metadata and roster counts. There are 33 sections below five registrations, across 31 courses. The manual calls for a presemester review and cancellation/notification of sections below five; it gives no review date. This is a review of preregistration, not a finding that a cancellation deadline was missed.

A private same-course rebalancing proposal resolves 17 sections with 51 roster moves. It preserves each student’s course choices and term credits, every section’s faculty/room/meetings/capacity, and passes an independent student-conflict check. The solver returned a feasible proposal, not proof of the fewest possible moves. No proposed changes have been loaded. Full migration validation remains required before any application.

| Category | Courses | Underfilled sections | Status |
| --- | ---: | ---: | --- |
| Existing-section rebalancing | 17 | 17 | Staged proposal brings these sections to at least five |
| Entire course below five | 12 | 13 | Cannot resolve through same-course roster moves |
| Too few students for current section count | 2 | 3 | Consolidation or course-plan review required |

Remaining courses needing a different solution:

| Course | Total course enrollment | Current group sizes | Reason |
| --- | ---: | --- | --- |
| AS2640 | 2 | 2 | course-demand-below-five |
| BS2410 | 1 | 1 | course-demand-below-five |
| BS2411 | 1 | 1 | course-demand-below-five |
| CS2000 | 12 | 10, 1, 1 | consolidation-review |
| ED4121 | 3 | 3 | course-demand-below-five |
| ED4200 | 1 | 1 | course-demand-below-five |
| ED4220 | 6 | 5, 1 | consolidation-review |
| EL4800 | 3 | 3 | course-demand-below-five |
| PH3325 | 2 | 2 | course-demand-below-five |
| PH5920 | 4 | 4 | course-demand-below-five |
| PX5100 | 4 | 4 | course-demand-below-five |
| PY3010 | 3 | 3 | course-demand-below-five |
| PY3410 | 3 | 2, 1 | course-demand-below-five |
| VA2510 | 1 | 1 | course-demand-below-five |

BS2410 and BS2411 are the associated science lecture and lab; any replanning must preserve their reviewed pairing. No student should receive a filler course simply to increase enrollment.

Owner-approved approach: keep preregistration open and enforce the minimum at an agreed presemester review deadline. The date remains unset. Keep the staged rebalancing proposal unapplied. See advisor-readiness-review.md for the subsequent independent coverage review.

Three regression tests pass: same-course roster preservation, conflict avoidance with unchanged courses, and refusal to invent demand or cancel low-demand sections. Python syntax passes. The initial syntax command could not write to the host Python cache; rerunning with a permitted temporary cache succeeded.

Private proposal: `/private/tmp/northbridge-spring-minimum-review-20261109-v2/`. This is not a replacement candidate or a ready-to-load schedule. Earlier non-v2 exploratory output is superseded. Aggregate course/section counts only are published in `database/staging/academic-alignment/spring-minimum-enrollment-review.json`. The working database, test database, schema, seeds, and portal configuration are unchanged.

Policy approved November 9: the owner accepted keeping sections open during
preregistration and enforcing the minimum at a defined presemester review deadline.
At review, evaluate consolidation and academically valid alternatives, preserve
degree progress and lecture/lab pairing, and apply the manual's cancellation and
notification requirement where applicable. The deadline and calendar boundaries
still need definition. No cancellation workflow, scheduled task, notification, or
roster change was implemented by this policy decision.
