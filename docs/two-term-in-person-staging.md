# Fall/Spring in-person staging — academic planning under review

**The earlier technical verification below is not academic planning approval.**
The owner identified redundant mathematics and poor degree progression in that
candidate. It is not approved for migration. The replacement planner separates
required courses, elective choices, prerequisite preparation, GE, college-wide
distributions and residual general electives. See
[the planning review](academic-planning-review.md) for current coverage and
unresolved loads. The active database and permanent schema remain unchanged.

## Earlier technical rehearsal (superseded as a planning candidate)

The previous Spring timeout and fixed-course infeasibility have been resolved by
choosing courses, official meeting patterns, faculty and rooms jointly. This
supersedes the blocked diagnostic candidates; it does not activate portal data.

- Fall 2026: 431 sections / 3,903 assignments.
- Spring 2027: 404 sections / 3,740 assignments.
- All 1,140 active undergraduates meet their full-time/part-time minimum loads,
  within the planned 16/8 credit maxima. A minimum load is not a promise of
  graduation on a particular date.
- Every selected section is in person. Official dates, times, credits and source
  section labels are preserved. Approved historical/alias conversion templates
  remain explicitly labeled and never receive an invented official CRN.
- Fresh Fall and Spring linking/cross-list metadata was checked. Unresolved
  linked/shared-seat sections are excluded, not silently treated as independent.
- An independent full-date checker found no student/faculty/room overlap,
  faculty workload, roster coverage or seat-capacity errors in 835 sections and
  7,643 assignments. It includes preserved graduate resource reservations and
  checks recorded room/source capacity. Graduate academics are unchanged.
- All 34,682 completed attempts are unchanged. All 25 CS freshmen have MA1020
  in Fall and CS2510 in Spring. Placement permits entry to algebra; Spring
  programming remains conditional on algebra completion.
- Independent prerequisite engines agree on all 42,325 attempts. Spring has
  1,597 conditional attempts, which are not unconditional registration rights.
- Recalculation/comparison of 2,200 majors and 300 declared minors found no
  earned degree-result changes. Current/future grades remain null.

## Next gate

Rehearse materializing this candidate into an isolated copy using the existing
schema. Version and load the staged MA1020 placement rule/evidence through the
existing academic JSON tables; update corresponding installation seeds and test
portal schedules/audits against that isolated copy. No CREATE/ALTER TABLE change
is required. No active database switch, active enrollment rewrite or seed export
has been performed for this candidate yet.

Private final candidate:
`/private/tmp/northbridge-spring-joint-rehearsal-20261108-v2/`.
Aggregate receipts:
`database/staging/academic-alignment/two-term-in-person-rehearsal.json` and
`database/staging/academic-alignment/two-term-timetable-validation.json`.

Reproducible independent checker: `tools/validate-in-person-staging.py`.
The Spring planner includes pending new sections when checking faculty workload
inside a corequisite bundle; the independent checker caught and verified this
boundary before the final candidate passed.

## Isolated database rehearsal completed

An exact 53-table clone of the active database was made as
`northbridge_ow_rebuild_2026_6`. DML-only materialization replaced 835 current/next
undergraduate sections and 7,643 registrations, with 1,693 exact meeting rows.
All 76 foreign keys, seats, source labels, resources and persisted prerequisite
results passed. Permanent schema definitions are unchanged. Original people,
accounts, graduates and all completed-history records were preserved.

The portal rehearsal passed 500 schedule/role HTTP checks and 94 audit/access
checks. All 2,200 undergraduate audits were evaluated and rendered, with zero
earned degree-result mismatches. Twenty installation seed files covering 24
tables were exported privately and SQL reload-verified in the isolated database.
Active installation seed files have not been replaced.

The real-world timeline was explicitly checked for undergraduate and graduate
students in both active and rehearsal databases: Fall 2026 and Spring 2027 each
have zero final grades and zero completed-history rows. Fall remains in progress;
Spring remains preregistered, including conditional prerequisites. No future
work earns credits or creates a GPA.

The isolated preview is `http://127.0.0.1:8002/`; the active server on port 8000
still uses the previous database. No active switch or external tunnel was made.
Aggregate final receipt:
`database/staging/academic-alignment/two-term-rehearsal-verification.json`.
