#!/bin/sh
set -eu
project_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
private_dir="$project_dir/.local"
if [ ! -d "$private_dir/mysql-data/mysql" ]; then
  echo 'The local demo database has not been initialized yet.' >&2
  exit 1
fi
if [ -f "$private_dir/mysql.pid" ] && kill -0 "$(cat "$private_dir/mysql.pid")" 2>/dev/null; then
  echo 'The dedicated local database is already running.'
else
  /opt/homebrew/bin/mysqld --no-defaults --datadir="$private_dir/mysql-data" --socket="$private_dir/mysql.sock" --pid-file="$private_dir/mysql.pid" --log-error="$private_dir/mysql.log" --port=3307 --bind-address=127.0.0.1 --mysqlx=0 --daemonize
fi
printf '%s\n' 'Database ready. In another terminal run:' "cd \"$project_dir\"" 'php -S 127.0.0.1:8000 tools/local-router.php' 'Then visit http://127.0.0.1:8000. Private logins: .local/demo-logins.txt'
