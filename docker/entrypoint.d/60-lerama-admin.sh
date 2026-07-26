#!/bin/sh
# Runs after 50-laravel-automations.sh (migrations) so the users table exists.
set -e

php "${APP_BASE_DIR:-/var/www/html}/artisan" lerama:setup-admin --no-interaction \
    || echo "[lerama] admin not configured (ADMIN_PASSWORD missing or too weak)"
