#!/usr/bin/env bash
# One-command Selenium (WebdriverIO) run for Lendly.
# Builds a throwaway SQLite DB, starts the app on it, runs every spec, then stops the app.
# Your real MySQL database is never touched.
# Usage (Git Bash, repo root):  npm run test:e2e   [extra wdio args, e.g. -- --spec tests/e2e/specs/full-cycle.e2e.cjs]
set -euo pipefail

cd "$(dirname "$0")/../.."
export PATH="/c/xampp/php:$PATH"

# Use another port (E2E_PORT=8001 npm run test:e2e) if 8000 is busy with your own dev server.
PORT="${E2E_PORT:-8000}"
BASE_URL="http://127.0.0.1:$PORT"
export DB_CONNECTION=sqlite
export DB_DATABASE="$(pwd -W)/database/e2e.sqlite"
export APP_URL="$BASE_URL" QUEUE_CONNECTION=sync MAIL_MAILER=log

if curl -s -o /dev/null "$BASE_URL"; then
  echo "Port $PORT is already in use. Stop the other server (php artisan serve) and try again." >&2
  exit 1
fi

# A leftover public/hot (from a Vite dev server that didn't shut down cleanly) makes every page
# load CSS/JS from a dead server. Remove it only if that server isn't actually running.
if [ -f public/hot ] && ! curl -s -o /dev/null --max-time 2 "$(cat public/hot)"; then
  echo "==> Removing stale public/hot (Vite dev server isn't running; using built assets)"
  rm -f public/hot
fi

echo "==> Preparing a fresh test database (database/e2e.sqlite)"
rm -f database/e2e.sqlite && touch database/e2e.sqlite
php artisan migrate:fresh --force -q
php artisan db:seed --class=TestAccountSeeder --force -q
php artisan db:seed --class=CategorySeeder --force -q
php tests/e2e/fixtures/seed.php

echo "==> Starting the app on $BASE_URL (test database)"
# Same as `php artisan serve`, minus the wrapper process, so it can be stopped cleanly.
# Laravel's router expects to run from inside public/.
ROOT="$(pwd)"
pushd public > /dev/null
php -S "127.0.0.1:$PORT" "$ROOT/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php" \
  > "$ROOT/tests/e2e/artifacts/server.log" 2>&1 &
SERVER_PID=$!
popd > /dev/null
trap 'kill $SERVER_PID 2>/dev/null || true' EXIT

for _ in $(seq 1 30); do
  curl -s -o /dev/null "$BASE_URL" && break
  sleep 1
done

echo "==> Running Selenium tests"
echo
E2E_BASE_URL="$BASE_URL" npx wdio run tests/e2e/wdio.conf.cjs "$@"
