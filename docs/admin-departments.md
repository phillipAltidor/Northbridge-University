# Admin department management

Branch scope: `ruba-admin-departments`, manual use cases 54, 55, and 69.
The supplied System Manual PDF (pages 26–27, 30) defines create/update departments
and faculty assignments; page 7 limits full-time faculty to three departments
and part-time faculty to one. The photo identifies the grey sidebar actions.

Existing implementation is in `includes/department-management.php` and
`includes/department-page.php`, with three thin `admin/` entry points. The sidebar
now links to those pages. No schema migration is needed.

## Access and setup

Pages require an authenticated Admin with a matching Admin/User record. Because
Schema 2.3 has no security-level column, department writes use an explicit private
configuration allowlist, `ADMIN_DEPARTMENT_WRITER_IDS`, containing comma-separated
Admin user IDs. Empty or malformed configuration grants no department write access.
Set this in the private `.env` or server process environment; `.env` takes
precedence. This setting covers department pages only, not every admin workflow.
No admin has been granted access by this change.

A Git branch isolates source changes, **not the database**. Test with a separate
database configured in the private environment. Do not point write tests at the
shared site or the historical V11 preview. No database cutover is authorized.

## Manual comparison

| Requirement | Implementation on this branch | Evidence / remaining check |
| --- | --- | --- |
| Use case 54: enter department information and create it | Contact, office and leadership fields; transactional create; confirmation and redirect to the saved department | In-memory create/read-back passed; live MySQL form submission pending |
| Use case 55: select, update and save a department | Department selector, prefilled form, fixed ID, stale-edit rejection | In-memory update and stale rejection passed; live HTTP submission pending |
| Use case 69: select faculty and department, save assignment | Faculty selector, current appointments, department selector, allocation/date, save and return to selected faculty | In-memory assignment/read-back passed; live HTTP submission pending |
| Faculty rules, page 7: FT at most 3 departments; PT at most 1 | Server-side caps, faculty row lock and existing appointment summary | FT/PT cap and duplicate rejection passed in-memory; MySQL concurrency pending |
| Admin rule, page 8: read-only admin cannot update records | Explicit writer allowlist, disabled form and server-side POST rejection | Permission helpers/rendered form checks passed; authenticated HTTP denial pending |

The manual specifies behavior, not a visual design. The forms use the existing
navy/gold university style, action tabs, grouped fields, readable feedback,
keyboard focus indicators, empty states, and a responsive guidance column.

## Behavior

- Create: validate required schema fields, existing office and faculty chair,
  unique department email, and allocate an integer ID transactionally.
- Update: preserve the department ID and reject stale forms using a row snapshot
  and lock. Existing relationships remain attached to the same department.
- Assign: retain appointments, reject duplicates, enforce FT/PT membership caps
  and total allocation at most 100%, and lock the faculty row during the write.
- POST requires write permission and a session form token. Successful submissions
  redirect; invalid submissions show an error without a partial transaction.

The 100% allocation cap and no-future appointment date are existing branch
implementation choices, not additional quoted manual requirements. Chair selection
does not automatically create a faculty appointment.

## Verification and remaining gates

2026-11-17 on the refreshed local branch: 36 permission/input/version checks,
19 in-memory persistence/rollback checks, seven rendered form states, PHP/JS
navigation, and syntax checks pass. Rendered fictional previews passed browser
layout checks at 1440px and 390px; desktop create and mobile assignment screenshots
were visually inspected. These are actual templates with fixture data, not a live
logged-in database session. SQLite fixtures strip `FOR UPDATE`; they cannot verify
MySQL locks, concurrency or deadlock retries. No external database was touched.

Run:

```sh
php tests/department-management.php
php tests/department-persistence.php
php tests/department-view.php
php tests/admin-navigation.php
node tests/admin-navigation.cjs
```

To render standalone, fictional preview pages, give the view test a private output
directory: `php tests/department-view.php /private/tmp/northbridge-department-preview`.
Those static previews cannot save. Production templates remain connected to the
existing PHP controller and SQL helpers.

Owner asked to protect partner work; no database provisioning or private
configuration changes are authorized for this session. The partner must identify
a safe test database and configure actual permitted Admin IDs in
`ADMIN_DEPARTMENT_WRITER_IDS` before testing saves. The allowlist is needed because
the supplied schema cannot distinguish read-only and write-capable admins.

Before merging/deploying, exercise the three forms against a disposable MySQL
database: create/update/read back; duplicate email; nonexistent office/chair;
stale concurrent update; duplicate and over-limit appointments; concurrent faculty
assignments; over-100% allocation; rollback; non-admin and view-only POST denial;
missing/invalid CSRF token; and successful redirect/refresh without duplicate save.
Visually check the forms and sidebar in the authenticated test portal.

Other grey admin actions remain outside this department branch. Existing private
V11 data and old validation receipts described in the handoff are not evidence
that these forms work on this machine.
