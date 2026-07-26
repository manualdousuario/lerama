#!/bin/sh
# Persist a generated APP_KEY when the operator did not supply one, so sessions
# and encrypted values survive container restarts. Written to .env because
# Laravel loads it immutably: real container env vars still take precedence.
set -e

if [ -n "$APP_KEY" ]; then
    exit 0
fi

key_file="${APP_BASE_DIR:-/var/www/html}/storage/app/.app_key"

if [ ! -s "$key_file" ]; then
    php -r 'echo "base64:".base64_encode(random_bytes(32)), PHP_EOL;' > "$key_file"
    chmod 600 "$key_file"
fi

printf 'APP_KEY=%s\n' "$(cat "$key_file")" > "${APP_BASE_DIR:-/var/www/html}/.env"
