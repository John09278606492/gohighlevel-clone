@echo off
REM One-click setup for Windows: double-click this file.
REM Installs everything, loads demo data and opens the app. Run it again any time to reset the demo data.
cd /d "%~dp0"
title LeadEngine setup

where php >nul 2>nul || (echo Missing PHP. Install PHP 8.3+ ^(Laragon or Herd is easiest^), then run this again. & pause & exit /b 1)
where composer >nul 2>nul || (echo Missing Composer. Install it from getcomposer.org, then run this again. & pause & exit /b 1)
where npm >nul 2>nul || (echo Missing Node.js. Install it from nodejs.org, then run this again. & pause & exit /b 1)

REM Check PHP and switch on any missing extensions in php.ini automatically.
php scripts\check-php.php --fix
php scripts\check-php.php >nul
if errorlevel 1 (
  echo.
  php scripts\check-php.php
  echo PHP is still not ready. See the message above.
  pause
  exit /b 1
)

call composer run setup
if errorlevel 1 (echo. & echo Setup failed. Scroll up to see the error. & pause & exit /b 1)

echo.
echo Done! Log in at http://127.0.0.1:8000/app/login
echo   admin@example.com / password  (agency + every sub-account)
echo   staff@example.com / password  (one sub-account)
echo.
call start.bat
