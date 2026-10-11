#!/usr/bin/env bash
# One-click setup for Mac/Linux: installs everything, loads demo data and opens the app.
# Run it again any time to reset the demo data.
set -e
cd "$(dirname "$0")"

for cmd in php composer npm; do
  command -v "$cmd" >/dev/null 2>&1 || { echo "Missing: $cmd. Install PHP 8.3+, Composer and Node.js, then run this again."; exit 1; }
done

missing=""
for ext in bcmath intl zip pdo_sqlite mbstring; do
  php -m | grep -qi "^$ext\$" || missing="$missing $ext"
done
if [ -n "$missing" ]; then
  echo "PHP is missing these extensions:$missing"
  echo "Enable them in php.ini (or install php-<name>), then run this again."
  exit 1
fi

composer run setup

echo ""
echo "Done! Log in at http://127.0.0.1:8000/app/login"
echo "  admin@example.com / password  (agency + every sub-account)"
echo "  staff@example.com / password  (one sub-account)"
echo ""
./start.sh
