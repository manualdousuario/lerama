#!/bin/sh
# Recreate the writable storage tree. Needed because storage/app/public is a
# bind mount, which hides whatever the image baked in.
set -e

base="${APP_BASE_DIR:-/var/www/html}"

mkdir -p \
    "$base/storage/app/public/thumbnails" \
    "$base/storage/framework/cache" \
    "$base/storage/framework/sessions" \
    "$base/storage/framework/views" \
    "$base/storage/logs"
