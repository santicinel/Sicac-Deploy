#!/bin/sh
set -eu

cd /var/www/html

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
  db_file="${DB_DATABASE:-/var/lib/sicac/database.sqlite}"
  mkdir -p "$(dirname "$db_file")"
  touch "$db_file"
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/app/private bootstrap/cache
chmod -R 775 storage bootstrap/cache

php artisan config:cache

queue_connection="${QUEUE_CONNECTION:-database}"
queue_names="${QUEUE_NAMES:-mail-notifications,default}"
queue_sleep="${QUEUE_SLEEP:-1}"
queue_tries="${QUEUE_TRIES:-3}"
queue_timeout="${QUEUE_TIMEOUT:-120}"

exec php artisan queue:work "$queue_connection" \
  --queue="$queue_names" \
  --sleep="$queue_sleep" \
  --tries="$queue_tries" \
  --timeout="$queue_timeout" \
  --no-interaction
