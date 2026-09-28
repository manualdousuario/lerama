#!/bin/sh
set -e

base="${APP_BASE_DIR:-/var/www/html}"

mkdir -p \
    "$base/storage/app/public/thumbnails" \
    "$base/storage/framework/cache/data" \
    "$base/storage/framework/cache/locks" \
    "$base/storage/framework/sessions" \
    "$base/storage/framework/views" \
    "$base/storage/logs"

if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data "$base/storage" "$base/bootstrap/cache"
fi
