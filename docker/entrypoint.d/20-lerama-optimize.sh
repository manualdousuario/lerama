#!/bin/sh
set -e

base="${APP_BASE_DIR:-/var/www/html}"

php "$base/artisan" config:cache --quiet
php "$base/artisan" route:cache --quiet
php "$base/artisan" event:cache --quiet
