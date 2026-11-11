> Historical pilot / first-pass collection. The current 24-program review and corrected course extraction are in [academic-alignment](../academic-alignment/README.md); use the [November 7 migration plan](../../../docs/academic-data-migration-plan.md) for current scope. Earlier generated comparisons and entry counts are not approved import definitions.

# Computer Science Pilot Comparison

Reviewed October 27, 2026. Reference files only; no database import or test
database has been created. Every mapping below is a topic-based proposal, not
an approved equivalency, prerequisite rule, transfer decision, or rename.

## Scope

| Measure | Northbridge | Old Westbury reference |
| --- | --- | --- |
| Courses reviewed | 18 undergraduate CS courses | 21 CS offerings plus 5 relevant math courses |
| Fall 2026 sections | 36 undergraduate CS sections | 26 CS sections plus 8 relevant math sections |
| Required courses | 13 courses, minimum C | Screenshot model: 10 core, 3 electives, 4 math courses |
| Major requirement credits | 52 credits in the existing flat list | 68 in the unverified screenshot model |
| Curriculum status | Existing project data | Catalog year and authoritative rules still pending |

The 21 CS offerings include courses outside the undergraduate pilot. A subject
search is not a complete degree catalog, and a missing Fall offering does not
mean a course is discontinued or not required.

## Candidate Topic Matches

| Our course | Required here? | Possible reference counterpart | Review concern |
| --- | --- | --- | --- |
| CS1101 Introduction to Programming | Yes | CS2510 Computer Programming I | Verify language, coverage and prerequisites |
| CS1102 Object-Oriented Programming | Yes | CS2511 / CS3910 / CS3911 | Ambiguous; cannot select automatically |
| CS1103 Data Structures | Yes | CS3810 Data Structures & Algorithms | Shared candidate with our Algorithm Design |
| CS1104 Database Systems | Yes | CS4550 Database Management Systems | Verify coverage and prerequisites |
| CS1105 Computer Networks | Yes | CS5710 Computer Networks | Reference numbering does not establish graduate level |
| CS1106 Software Engineering | Yes | CS4501 Software Engineering | Verify coverage and prerequisites |
| CS1107 Introduction to Computing | Yes | No candidate yet | Need catalog descriptions |
| CS1108 Web Development | No | CS4720 Internet & Web Tech | Related topic, potentially different scope |
| CS1109 Computer Organization | Yes | CS3620 Computer Architecture I | Organization/architecture overlap only |
| CS1110 Operating Systems | Yes | CS5610 Operating Systems | Not in Fall snapshot |
| CS1111 Algorithm Design | Yes | CS3810 Data Structures & Algorithms | Partial overlap; no double counting |
| CS1112 Introduction to Cybersecurity | No | CS4705 Introduction to Computer Security | Not in Fall snapshot; verify content |
| CS1113 Human-Computer Interaction | No | No candidate yet | Need catalog descriptions |
| CS1114 Mobile Application Development | No | No candidate yet | Need catalog descriptions |
| CS1115 Data Science Fundamentals | No | No candidate yet | Do not equate with a graduate data course |
| CS1116 Programming Languages | Yes | No candidate yet | Need catalog descriptions |
| CS1117 Software Testing | Yes | No candidate yet | Need catalog descriptions |
| CS1118 Computer Science Capstone | Yes | CS5910 Systems Design & Implement | Verify capstone and project requirements |

## Requirement Differences

The screenshots describe a 40-credit core, 12 credits chosen from an elective
list, and 16 mathematics credits including MA3160 OR MA4100. Our current major
list has no requirement groups or math courses, and treats all 13 rows as required.
Matching topics alone will not produce a correct degree audit.

CS4100 Technical Communications has no proposed counterpart among our 18 CS
courses. On November 7, 2026, the user approved CS3911 (4 credits) as Northbridge's
tenth core requirement, without a CS3910 alternative. This project decision does
not establish equivalency with an existing Northbridge course or verify the
missing entry in the pasted catalog edition. Generated comparisons need rebuilding.

The screenshot also includes degree-wide credit/GPA and residency requirements.
The program overview mentions science/engineering credits. These must be checked
against a single agreed catalog year before implementing any evaluation.

## Scheduling and Schema Differences

- Our integer section numbers cannot preserve labels like BL1 or leading zeroes.
- Our fixed 10-seat rule must not be replaced with real enrollment snapshots.
  Capacity policy needs approval; our fictional rosters stay separate.
- Our 90/120/180-minute editor rules differ from the official 100-minute regular
  undergraduate blocks. Graduate/summer/individual sections require separate review.
- Multiple periods per slot do not solve arbitrary meeting arrangements: our
  queries pair every slot day with every slot period and keep one room/faculty.
- Delivery methods, term-specific meeting dates, parts of term, grouped electives,
  catalog years and transfer/repeat policies are not fully represented.

## Next Review

1. Confirm the professor's intended scope and catalog year.
2. Obtain official descriptions/prerequisites; retain the approved CS3911 project decision.
3. Review course mappings and requirement groups, including math and general education.
4. Approve necessary schema and scheduling policy changes.
5. Build an isolated test database; preserve all existing student history and users.

See `review.json` for source URLs and `generated/manifest.json` for the source
checksum and actual counts. All generated comparisons reflect the current local
academic database at build time and should be rebuilt if that data changes.
