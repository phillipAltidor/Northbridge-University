# Read-only academic evaluation preview

Implemented November 7, 2026, inside the existing Northbridge PHP Admin portal. No separate website or hosted Site was created.

## Open the preview

Sign in as an existing administrator and open **Majors & Minors → Degree Evaluation Preview**:

[Local Admin preview](http://127.0.0.1:8000/admin/degree-preview.php)

Choose any of the 12 majors or 12 minors, then an available track/path and one of three synthetic examples:

- **In-progress example:** a partial course history, two pending courses, outstanding requirements and missing approvals.
- **Complete example:** the previously tested synthetic complete case for that program/path.
- **Transfer review example:** one course changed to transfer credit with no approved equivalent, demonstrating the review gate.

The screen shows requirement-group completion, earned degree/applied minor credits, pending credits, review counts, counted courses, still-required fixed courses, elective/path options, other academic checks and synthetic course history. Historical Education titles and catalog versions are displayed separately from current same-code meanings. A completed minor is not shown as whole-degree graduation approval.

## Access and read-only behavior

The route requires both an authenticated Admin session and a database-backed Admin role check. Students, Faculty and StatStaff cannot view it; forging a session role without an Admin record is insufficient. The page accepts only GET/HEAD requests with predefined program, path and example values. It does not accept student IDs, catalog overrides, uploaded attempts, JSON approval records or write forms.

The existing portal connection continues to use `northbridge_university` for authentication/layout. A separate preview PDO selects only `northbridge_ow_2026_test`, starts a server-enforced read-only transaction, and is never assigned to the portal's global PDO. The route rolls back the read transaction after building its view model. Cache-Control is no-store/private.

Preview package ID 7 and SHA-256 `af788468dafce856c4ef61e4d428a42730e488ec3e9e96f969b0861f0ec2b032` are pinned explicitly. The route verifies package identity and the stored evaluator runtime hashes. A changed or missing package returns an unavailable message; it does not silently select a newer catalog or expose database errors/credentials.

All visible student histories and approvals come from predefined synthetic fixtures. No existing student is evaluated under a guessed course mapping. Source gaps, real approval evidence and the 469 prerequisite review gates remain governed by the [all-program report](academic-all-programs.md).

## Existing-student impact inventory

A separate CLI-only tool produced a private row for each of the **3,338 existing students**:

- **2,200 undergraduates** need individual program selection and legacy course-version/equivalency review before a new-catalog audit.
- **1,138 graduates** are marked excluded from this undergraduate migration; shared legacy major IDs must remain intact.
- **300 students** have declared minors requiring overlap review.
- All **37,187 Student_History rows** and **44,433 Enrollment rows** are accounted for independently, without double-counting them as earned credit.
- All **240 legacy course definitions** retain their current meanings. No course equivalency, program assignment or graduation verdict is approved by this inventory.
- Primary major declarations have **zero differences** against Student_Major membership in this snapshot.

Each private row contains its student ID, level, legacy major/minor declarations, history/enrollment counts, affected legacy course IDs and required review notes. It contains no names, emails, credentials, faculty identities or individual grades. The JSON and CSV are outside the web root and repository in the directory recorded by the [aggregate impact summary](../database/staging/academic-alignment/student-impact-summary.json). Directory permissions are 0700 and report permissions 0600. This is temporary local storage, not a durable backup.

This is a complete per-student **review inventory**, not a completed equivalency/advising review. Proposed assignments and new-catalog degree results remain null. Nothing was migrated.

## Verification

**367 HTTP/read-only checks pass across 111 preview pages** (37 program/path cases × 3 example modes), including:

- Admin authentication/authorization, forged-role rejection, logged-out redirects and non-Admin denial;
- invalid/array/injected parameters and student/catalog overrides rejected;
- POST rejected and SQL writes rejected by the preview connection;
- all 24 program options, complete/pending/review displays and historical Education titles;
- no credentials or editing controls in markup;
- unchanged row counts/checksums for all 47 working and copied legacy tables, plus unchanged checksums for all seven academic tables.

The updated Admin navigation tests pass. Detailed impact report validation confirms unique/full student coverage, graduate exclusions, null proposed assignments/verdicts and private file permissions. Browser visual inspection was unavailable: browser access was not connected and native Chrome access was not approved. HTTP/DOM checks were completed; no screenshot-based layout claim is made.

```sh
php tests/admin-navigation.php

# Local server on 127.0.0.1:8000 and database access required:
php tests/academic-preview-http.php
php tools/report-academic-student-impact.php
```

The report tool uses a read-only transaction and verifies all source fingerprints before/after. Re-running creates a new private directory and updates only the aggregate report artifact.

## Next step

Review individual undergraduate program choices and legacy course equivalencies using this inventory, then build the protected real-student registrar adapter. Official semester schedules, restoration rehearsal and synchronized installation seeds remain necessary before any working data migration. The preview adds no registration, transfer-approval or degree-awarding action.
