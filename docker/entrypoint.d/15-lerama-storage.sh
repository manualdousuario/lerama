#!/bin/sh
set -e

base="${APP_BASE_DIR:-/var/www/html}"

mkdir -p \
    "$base/storage/app/public/thumbnails" \
    "$base/storage/framework/cache" \
    "$base/storage/framework/sessions" \
    "$base/storage/framework/views" \
    "$base/storage/logs"
