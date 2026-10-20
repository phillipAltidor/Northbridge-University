# Password Reset Setup and Verification

## Setup

Requires PHP 8.1+, PDO MySQL, OpenSSL, Composer, and MySQL/MariaDB with InnoDB.

1. Run `composer install --no-dev` from the project directory. Commit the
   Composer manifest and lockfile, not the generated `vendor/` directory.
2. Back up the database. Import `database/migrations/password_reset.sql` once
   after creating the Schema 2.3 Login table. This adds supplemental storage;
   it does not alter existing academic, personal, or login records.
3. Configure the `MAIL_*` settings from `.env.example` in your private `.env`.
   For Yahoo, use `smtp.mail.yahoo.com`, port 465, `ssl`, the full sender email,
   and its generated app password. Never use the regular account password.
   SMTP credentials and sender identity are independent of recipient accounts.
4. Run `php tools/test-mail.php` to check encrypted SMTP authentication.
5. Run `php tools/test-mail.php --send` to send one setup message to the sender's
   own inbox. SMTP acceptance does not prove inbox delivery; check inbox/spam.

Switching sender later requires changing only private mail configuration.
Other teammates configure their own private `.env` on their machines or host.
This implementation is compatible with AWS hosting; it does not require SES.

## Recovery Policy

- Student, Faculty, Admin, and Statistics Staff can recover their own accounts.
- Normally codes go to the registered Login email for the selected portal.
  For fictional demo accounts only, set `MAIL_DEMO_RECIPIENT` in private `.env`
  to a real inbox and set `APP_ENV=local` or `demo`. Reset codes and password-change
  notifications then go only to that inbox, marked `[DEMO]` and naming the
  requested account. Login addresses and account-specific verification stay
  unchanged. Unknown accounts and wrong-role requests still send no email.
  This lets the demo inbox owner reset any fictional account; never enable it
  with real users. A configured override in any other environment blocks mail.
  Leave the override blank for normal delivery. Teammates must configure it
  separately in their private environment; no routing address is committed.
- A code is six random digits, valid for ten minutes, stored only as a password
  hash. Five invalid attempts invalidate that request without locking Login.
- Code verification issues a separate random, session-bound grant. It lasts at
  most five minutes and never outlives the original code expiration.
- New passwords must be 12-72 bytes, with matching confirmation, and are stored
  using PHP password hashing. The existing legacy plaintext-login compatibility
  cannot authenticate by presenting a stored password hash as the password.
- Only a successful verified password change clears `no_Of_Tries` and `lock_var`.
  Requesting an email or entering a code does not unlock the account.
- Completion invalidates all outstanding requests for that account. Changing
  the account's email, password, or role also invalidates old requests/grants.
- Existing authenticated sessions must sign in again on their next protected
  request after a password change. Pre-upgrade sessions without a password
  version also need to sign in once. Reset completion never logs the user in.
- A separate confirmation email is attempted after completion, with no password
  or code. Failure to deliver that notification does not undo the password change.
- Requests have a 60-second per-address cooldown, a six-per-address hourly limit,
  and a twenty-per-source-IP hourly limit. The application does not trust proxy
  headers; configure client-IP handling deliberately if deploying behind a proxy.
- Unknown emails, wrong-portal requests, and delivery failures receive neutral
  request results. Codes are not logged or displayed. SMTP errors are logged
  without credentials, messages, or recipient addresses.
- Request, verify, restart, and complete forms require session CSRF tokens.

Do not publicly expose `.env`, database SQL/backups, or development tools. Keep
the PHP development server on loopback only; use HTTPS and a configured production
web server for deployment. Public hosting still needs its own security review.

Expired requests can be pruned by an operator after an appropriate retention
period, for example `DELETE FROM Password_Reset WHERE created_At < UTC_TIMESTAMP()
- INTERVAL 7 DAY`. Do not include recovery rows in shared seed data or GitHub.

## Tests

These commands default to the configured loopback database:

```sh
php tests/password-reset.php
php tests/auth-sessions.php
php tests/password-reset-http.php
php tests/mail-routing.php
```

The service tests create and drop an isolated temporary database and use captured
email callbacks, not real recipients. The HTTP tests target the local server,
create only disposable unknown-address requests, and delete their own rows.
Neither suite changes real account credentials. Tests need a local database user
that can create/drop a test database. HTTP tests require the dev server on port
8000; set `NORTHBRIDGE_TEST_URL` for a different loopback port.

Automated service and form tests do not replace a human inbox check. Before
claiming presentation-ready end-to-end delivery, use an approved demo account
with a deliverable address (or explicit demo routing) to request a code, reject an incorrect code, verify
the received code, change the password, and log in with the new password.

## References

- [PHPMailer](https://github.com/PHPMailer/PHPMailer)
- [Yahoo SMTP settings](https://help.yahoo.com/kb/SLN4724.html)
- [OWASP password-reset guidance](https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html)
