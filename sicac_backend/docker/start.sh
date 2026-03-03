#!/bin/sh
set -eu

cd /var/www/html

if [ -z "${APP_KEY:-}" ]; then
  echo "APP_KEY no esta definido. Genera uno y colocalo en deploy/.env" >&2
  exit 1
fi

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
  db_file="${DB_DATABASE:-/var/lib/sicac/database.sqlite}"
  mkdir -p "$(dirname "$db_file")"
  touch "$db_file"
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/app/private bootstrap/cache
chmod -R 775 storage bootstrap/cache

php artisan optimize:clear
php artisan migrate --force

if [ "${SEED_DEMO_DATA:-true}" = "true" ]; then
  marker="${SEED_MARKER_PATH:-/var/lib/sicac/.seeded}"
  mkdir -p "$(dirname "$marker")"
  if [ ! -f "$marker" ]; then
    php artisan db:seed --force
    touch "$marker"
  fi
fi

php artisan config:cache

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
