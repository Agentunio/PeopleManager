#!/bin/bash
set -euo pipefail

ROLE="${CONTAINER_ROLE:-app}"
echo "[entrypoint] Starting PeopleManager (APP_ENV=${APP_ENV:-local}, ROLE=${ROLE})..."

RUN_AS=(gosu www-data:www-data)

chown -R www-data:www-data storage bootstrap/cache
"${RUN_AS[@]}" mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
                          storage/logs storage/app/public bootstrap/cache

if [ "$ROLE" = "app" ] && [ "${APP_ENV:-local}" = "production" ] && [ -d /opt/app-public ]; then
    echo "[entrypoint] Syncing public/ from image snapshot into shared volume..."
    chown -R www-data:www-data /var/www/html/public
    "${RUN_AS[@]}" find /var/www/html/public -mindepth 1 -delete
    "${RUN_AS[@]}" cp -a /opt/app-public/. /var/www/html/public/
fi

if [ -n "${DB_HOST:-}" ]; then
    echo "[entrypoint] Waiting for database at ${DB_HOST}:${DB_PORT:-3306}..."
    ATTEMPTS=0
    MAX_ATTEMPTS=60
    until "${RUN_AS[@]}" php -r "try { new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: 3306), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); exit(0); } catch (Throwable \$e) { exit(1); }" 2>/dev/null; do
        ATTEMPTS=$((ATTEMPTS+1))
        if [ "$ATTEMPTS" -ge "$MAX_ATTEMPTS" ]; then
            echo "[entrypoint] Database not reachable after ${MAX_ATTEMPTS} attempts, aborting." >&2
            exit 1
        fi
        sleep 2
    done
    echo "[entrypoint] Database is ready."
fi

if [ "$ROLE" = "app" ]; then
    "${RUN_AS[@]}" php artisan storage:link --force 2>/dev/null || true
fi

if [ "$ROLE" = "migrate" ] || [ "$ROLE" = "shell" ]; then
    echo "[entrypoint] One-shot role (${ROLE}), skipping cache warm-up."
elif [ "${APP_ENV:-local}" = "production" ]; then
    "${RUN_AS[@]}" php artisan optimize:clear
    "${RUN_AS[@]}" php artisan config:cache
    "${RUN_AS[@]}" php artisan route:cache
    "${RUN_AS[@]}" php artisan view:cache
    "${RUN_AS[@]}" php artisan event:cache
else
    "${RUN_AS[@]}" php artisan migrate --force || true
    "${RUN_AS[@]}" php artisan optimize:clear || true
fi

if [ "${1:-}" = "php-fpm" ]; then
    exec "$@"
fi

exec gosu www-data:www-data "$@"
