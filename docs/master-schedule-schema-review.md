# Fall 2026 master schedule: schema mapping review

Historical source review. For the later persisted V11 checks and fixes, see
[schema alignment audit](schema-alignment-audit.md).

Reviewed November 8, 2026. No database or enrollment changes made.
Source: two owner-supplied Banner downloads, 913 unique semester/CRN pairs;
550 On Campus sections. Sanitized extraction and pattern report are in
`database/staging/academic-alignment/fall-2026-*.json`.

| Source field | Existing destination | Review result |
| --- | --- | --- |
| Subject + course number, title | Course; NB_AcademicCourse versioned definition | Supported; retain historical meanings rather than rename old credits |
| Credit hours / low / high | Course.course_Credits; versioned definition | Fixed integer credits fit; variable/fractional credits need explicit handling, never silently default to four |
| Term | Semester; NB_AcademicSection.source_Term | Supported |
| Official CRN | NB_AcademicSection.official_CRN | Supported with term; Course_Section.CRN remains a unique local relational key |
| Alphanumeric section label | NB_AcademicSection.source_Section | Supported; base section_No is integer and must not replace the source label |
| Meeting days and exact start/end | NB_AcademicMeeting; legacy Day/Period/Time_Slot bridges | Supported by academic meeting rows; preserve each row/day pairing |
| Date range | NB_AcademicMeeting.start_Date/end_Date | Supported for timed meetings |
| Modality and part of term | NB_AcademicSection.source_JSON | Source metadata supported; rebuild now selects only On Campus |
| Schedule type / meeting component | source_JSON and staged meeting rows | Preserve original components; no normalized lecture/lab registration relationship |
| Attributes | Reviewed mapping package and course properties | Used by audit; section-specific attribute differences require explicit reconciliation |
| Capacity | source_JSON.maximumEnrollment | Base schema has only available_Seats; source capacity is not automatically the Northbridge capacity |
| Enrollment / seats remaining | Enrollment and derived availability | Use fictional local enrollment; do not import SUNY enrollment counts |
| Cross-listing / combined capacity | Staging only | No normalized shared-capacity group or enforcement identified |
| Linked lecture/lab sections | Staging only | No normalized required-section pairing or enrollment enforcement identified |
| Restrictions | Prerequisite rules / scoped evidence where reviewed | Major/standing/permission rules need explicit mapping; do not infer from titles |
| Faculty and room | Existing fictional Faculty and Room assignments | Do not import real identities or SUNY rooms |

## Important schema constraints

- Base Time_Slot_Day and Time_Slot_Period are independent many-to-many lists.
  They cannot express which period belongs to which day for irregular meetings.
  NB_AcademicMeeting supplies that exact pairing; legacy consumers must not form
  a Cartesian product or invent extra meetings.
- Unknown/TBA meetings cannot be inserted as fake midnight times into
  NB_AcademicMeeting, which requires valid non-null times and date ranges.
- Course descriptions over 500 characters need the versioned definition;
  Course.course_DSC must not silently truncate the authoritative description.
- Keeping a field in JSON does not mean registration logic enforces it.
- In-person filtering is implemented in the generator, but existing active
  sections and student enrollments have not yet been migrated by that change.

## Next execution gates

1. Compare active local sections by source term/CRN against the fresh sanitized
   downloads, including every meeting row, credit value and modality.
2. Resolve linked sections, cross-list capacity and section-level restrictions
   before enrolling students in affected offerings. Reuse reviewed catalog rules
   for course-level prerequisites; no new schema migration has been applied.
3. Review 60 flagged in-person offerings individually; timing flags are not
   findings of institutional noncompliance. Labs, placements and variable credits
   need context. Exclude unresolved/TBA offerings from automatic timed assignment.
4. Rebuild prerequisite-first undergraduate plans in an isolated database, then
   validate seats, room/faculty/student conflicts, audits and install seeds.
5. Apply the same source/schema review to Spring 2027 before rebuilding it.

This is a source-to-schema review, not a claim that a fresh live-database
comparison or migration has already passed.
