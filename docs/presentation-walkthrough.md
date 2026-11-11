# Northbridge University presentation walkthrough

Use the test portal at **http://127.0.0.1:8003/**. Allow about 8–10 minutes.
Prepare one fictional Student, Faculty, and Admin account in advance. Keep
credentials out of slides and screenshots.

1. **Problem and design — 1 minute.** Explain that Northbridge is a fictional
   university registration portal with role-based access and a shared relational
   database. Undergraduate course and degree rules use reviewed SUNY Old Westbury
   sources. Show the ERD briefly: User → role records; Course → Course Section;
   Enrollment connects students and sections. The existing schema has 53 tables.

2. **Student workflow — 2 minutes.** Show the personalized Home page, current Fall
   2026 schedule, and Spring 2027 preregistration. Open Degree Audit and explain
   completed, in-progress, preregistered, and still-required work. Open one course's
   prerequisites. Pending coursework does not count as earned credit.

3. **Faculty workflow — 2 minutes.** Show Employment Type in My Profile, Semester
   Schedule, then View Roster for an assigned class. Point out student count and
   student academic/contact basics. Explain that the server restricts rosters to
   the authenticated instructor's own sections, even if a URL is altered.

4. **Admin workflow — 2 minutes.** Select Fall 2026 in Master Schedule. Show the
   Undergraduate default, a course/department search, pagination, and available
   seats. Explain Graduate / All Levels selection: preserved graduate offerings
   use the existing catalog. Point out the four campus buildings through the
   schedule locations. Saving a semester snapshot includes both academic levels.

5. **Verification and scope — 1 minute.** Summarize 2,200 undergraduate audits,
   45,821 undergraduate assignments across 14 terms, source-backed prerequisites,
   conflict checks, faculty roster isolation, and verified backup/restoration.
   Say these checks passed on the test candidate; they do not award degrees or
   certify all graduate academic requirements.

If explaining class times, use the course-specific source pattern. Official
Old Westbury offerings may meet once or twice weekly. Clearly distinguish
Northbridge-only sections that borrow verified periods when suitable official
offerings are unavailable. The corrected MC4802 custom sections meet twice for
100 minutes each; this is not a universal rule for every official course.

Keep the live walkthrough on implemented read-only features. List registration/
drop workflows, attendance, grade assignment, and remaining disabled navigation
as future work. Avoid making unplanned edits during the demo.

Finish with: “The core role-based portal and undergraduate academic checks are
implemented and tested. The current demonstration runs in an isolated test
database while final owner review precedes a working-portal cutover.”
