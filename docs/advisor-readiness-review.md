# Undergraduate advisor readiness review

Reviewed November 9, 2026 on V11 plus campus and faculty-count overlays in
`northbridge_ow_rebuild_2026_7`. This was a read-only review; no records changed.

All **1,140 undergraduates registered for Fall 2026 or Spring 2027 have advisors**.
The previously reported 1,000 undergraduates without advisors have no registration
in either term and their registrar profile has activeOriginalCohort=false. Another
60 undergraduates without current/next registration do retain advisor assignments.
Absence of registration does not establish formal inactive or graduated status.
Historical advising coverage therefore needs its own policy if a later feature
requires it; it is not a gap in current/next registration coverage.

Existing advisors satisfy the manual's full-time-only and maximum-15-advisee
requirements. Existing undergraduate advisors are also members of their student's
primary-major department; that is an additional project consistency check. There
are 250 full-time and 100 part-time faculty, with 2,150 unused full-time advising
places in total. This aggregate does not guarantee spare capacity per department.
The 738 preserved graduate students without advisors remain a separate scope.

`tests/advisor-readiness.php` passes five checks: current/next undergraduate
coverage, full-time eligibility, max-15 advisees, major-department compatibility,
and unchanged fingerprints for every one of the 53 database tables. Its output
contains aggregate counts only. The check uses configured current/next semester
names and will identify new coverage gaps as registrations change.

Data readiness does not implement the currently disabled advisor/advisee views or
assignment workflow. Those features still need role/ownership and write checks.
No schema, database, installation seed or portal configuration changes were made.
The next readiness item is defining calendar and operation-window policies.
