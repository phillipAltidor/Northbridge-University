# Schedule display and schema

The owner-approved order is shared by the student semester schedule, faculty
semester schedule, and admin master schedule:

CRN → Course → Course Name → Section → Professor → Days → Time → Building / Room.

Faculty adds its Roster link. As revised by the owner on November 11, 2026,
master schedule adds Total Seats and Available Seats after Building / Room and
before its Edit action when editable. Student and faculty semester schedules
retain their existing columns. Stored availability and enrollment enforcement
remain unchanged.
The semester appears in the table heading rather than repeating in every row.
Credits remain stored in `Course.course_Credits` for degree audits and credit-load
rules, but are omitted from these schedule tables at the owner's request.

The actual test database was checked on November 9 and November 11, 2026. `Course_Section` contains
`CRN`, `course_ID`, `section_No`, `faculty_ID`, `time_Slot_ID`, `room_ID`,
`semester_ID`, and `available_Seats`. Its primary key is CRN, with foreign keys to
Course, Faculty, Time_Slot, Room, and Semester. Display order does not change these
columns or relationships.

| Display | Source |
| --- | --- |
| CRN | Course_Section identity, with separate reviewed source/local CRN labels |
| Section | Course_Section.section_No as a plain integer (1, 2, 3); no NB prefix or zero padding |
| Course / Course Name | Course and reviewed version metadata |
| Semester heading | Semester through Course_Section.semester_ID |
| Professor | Faculty/User through Course_Section.faculty_ID |
| Days / Time | Exact academic meeting records for rebuilt sections; legacy Time_Slot relationships otherwise |
| Building / Room | Room/Building through Course_Section.room_ID |
| Total Seats (master only) | Existing rebuilt section source_JSON.capacity; legacy ten-seat limit; no separate Course_Section total-seat column |
| Available Seats (master only) | Capacity minus distinct roster membership, audited against Course_Section.available_Seats |

Student schedules filter through the authenticated student's Enrollment rows.
Faculty schedules filter the authenticated faculty member's Course_Section
assignments. Master schedule filters the selected semester and academic level.
Each section is one row, preserving all actual day/time/date/location groups.
Local CRNs remain the relationship keys even when an official source CRN is shown.
