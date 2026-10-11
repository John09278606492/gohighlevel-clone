#!/usr/bin/env bash
# Starts the app (web server + background worker for workflows) and opens the browser.
cd "$(dirname "$0")"
php artisan queue:work --quiet &
QUEUE=$!
php artisan schedule:work --quiet >/dev/null 2>&1 &
SCHED=$!
trap 'kill $QUEUE $SCHED 2>/dev/null' EXIT
( sleep 2; (command -v open >/dev/null && open http://127.0.0.1:8000/app/login) || (command -v xdg-open >/dev/null && xdg-open http://127.0.0.1:8000/app/login) ) >/dev/null 2>&1 &
echo "LeadEngine is running at http://127.0.0.1:8000  (press Ctrl+C to stop)"
php artisan serve --host=127.0.0.1 --port=8000
