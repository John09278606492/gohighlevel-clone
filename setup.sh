#!/usr/bin/env bash
# One-click setup for Mac/Linux: installs everything, loads demo data and opens the app.
# Run it again any time to reset the demo data.
set -e
cd "$(dirname "$0")"

for cmd in php composer npm; do
  command -v "$cmd" >/dev/null 2>&1 || { echo "Missing: $cmd. Install PHP 8.3+, Composer and Node.js, then run this again."; exit 1; }
done

php scripts/check-php.php || exit 1

composer run setup

echo ""
echo "Done! Log in at http://127.0.0.1:8000/app/login"
echo "  admin@example.com / password  (agency + every sub-account)"
echo "  staff@example.com / password  (one sub-account)"
echo ""
./start.sh
