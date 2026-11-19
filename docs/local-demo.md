# Ruba's local demo

This setup uses the repository's 46 installation SQL files, in the documented load
order. It is separate from Phillip's private database and does not reproduce the
latest private V11 state. Existing account identities/academic records are imported
from fictional project seeds. Three selected Login rows receive local demo aliases
and freshly generated passwords; no repository seed is rewritten.

The dedicated MySQL instance listens only on `127.0.0.1:3307`, with database
`northbridge_ruba_demo_20261119`. Its files are in ignored `.local/mysql-data`.
The application DB user has only SELECT/INSERT/UPDATE/DELETE on that database;
root credentials are saved separately for local administration. No public database
port or remote server is involved. The private `.env` selects this instance and
labels the signed-in portal "Ruba local demo". One local admin is enabled for
Department writes. SMTP is not configured; password-recovery email is unavailable.

## Open it after installation

From the project folder:

```sh
sh tools/start-local-demo.sh
php -S 127.0.0.1:8000 tools/local-router.php
```

Open http://127.0.0.1:8000 and select Login → Admin, Faculty, or Student.
The three test emails/passwords are in **`.local/demo-logins.txt`**. Never commit
that file, `.local/demo-accounts.json`, `.local/mysql-admin.cnf`, database files,
logs or `.env`. Each role needs its own browser session; sign out to switch roles.
The router blocks HTTP access to private files, SQL, configuration, tests and tools.
Do not run a public server from this development directory.

Stop the PHP server with Control+C. To stop the dedicated database:

```sh
/opt/homebrew/bin/mysqladmin --defaults-extra-file="$PWD/.local/mysql-admin.cnf" shutdown
```

## Fresh setup safeguards

`tools/setup-local-demo.php --initialize` is an initial-setup tool, not a migration.
It requires a newly initialized dedicated MySQL process on port 3307, and refuses
an existing `.env`, account file or database. It installs the original schema,
repository seeds, existing supplementary tables and seat triggers on this fresh
instance only. It never imports over an existing working database or invokes the
older data-rewriting migration scripts. `demo_seed.sql` is not mixed with the full
seed set. Composer dependencies are installed from the lock file.

The owner-confirmed semester dates and published no-class days are configured
for regular attendance, which can be saved only during an actual meeting today.
Grading rosters are viewable; grade saving still requires per-section approved
final-exam end times and permitted grades. The configured exam ranges are December
10–16, 2026 and May 12–18, 2027; saving opens at exam end and closes 48 hours later.
The local accounts do not bypass those restrictions.
