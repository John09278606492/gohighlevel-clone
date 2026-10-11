#!/usr/bin/env bash
# One-click test: installs dependencies if needed, then runs the automated tests.
set -e
cd "$(dirname "$0")"
[ -d vendor ] || composer install
[ -f .env ] || { cp .env.example .env; php artisan key:generate; }
[ -f public/build/manifest.json ] || { npm install --ignore-scripts; npm run build; }
composer run test
