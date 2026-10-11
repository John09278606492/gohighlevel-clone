@echo off
REM One-click test for Windows: double-click this file.
cd /d "%~dp0"
title LeadEngine tests
if not exist vendor call composer install
if not exist .env (copy .env.example .env >nul & php artisan key:generate)
if not exist public\build\manifest.json (call npm install --ignore-scripts & call npm run build)
call composer run test
echo.
pause
