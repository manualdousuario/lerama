#!/bin/sh
set -e

php "${APP_BASE_DIR:-/var/www/html}/artisan" lerama:prepare-migrations --no-interaction \
    || echo "[lerama] legacy migrations table not checked; migrate will report the cause"
