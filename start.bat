@echo off
REM Starts the app (web server + background worker for workflows) and opens the browser.
cd /d "%~dp0"
title LeadEngine
start "LeadEngine queue" /min php artisan queue:work
start "LeadEngine scheduler" /min php artisan schedule:work
start "" http://127.0.0.1:8000/app/login
echo LeadEngine is running at http://127.0.0.1:8000  (close this window to stop)
php artisan serve --host=127.0.0.1 --port=8000
