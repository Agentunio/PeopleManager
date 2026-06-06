#!/bin/bash
set -euo pipefail

ROLE="${CONTAINER_ROLE:-app}"
echo "[entrypoint] Starting PeopleManager (APP_ENV=${APP_ENV:-local}, ROLE=${ROLE})..."

# Ensure writable runtime dirs exist on the storage volume / tmpfs.
# Run as root because named volumes / tmpfs come up owned by root unless tmpfs has uid=.
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
         storage/logs storage/app/public bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# In production, /var/www/html/public is a shared named volume between app and nginx.
# Only the `app` role refreshes it from the image snapshot; one-shot containers (migrate,
# tinker, etc.) must NOT mutate the shared volume — they would race with serving traffic
# and leave new assets paired with old PHP if migration later fails.
if [ "$ROLE" = "app" ] && [ "${APP_ENV:-local}" = "production" ] && [ -d /opt/app-public ] && [ -w /var/www/html/public ]; then
    echo "[entrypoint] Syncing public/ from image snapshot into shared volume..."
    find /var/www/html/public -mindepth 1 -delete
    cp -a /opt/app-public/. /var/www/html/public/
    chown -R www-data:www-data /var/www/html/public
fi

# Everything after this runs unprivileged.
RUN_AS=(gosu www-data:www-data)

# Wait for database to accept connections
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

# storage:link only matters for the HTTP-serving container.
if [ "$ROLE" = "app" ]; then
    "${RUN_AS[@]}" php artisan storage:link --force 2>/dev/null || true
fi

# Migrate / tinker / shell roles skip the optimize+cache dance.
# They run a one-shot command and exit; rebuilding caches would be wasted work
# and could leave stale config:cache if the command modifies env-dependent state.
if [ "$ROLE" = "migrate" ] || [ "$ROLE" = "shell" ]; then
    echo "[entrypoint] One-shot role (${ROLE}), skipping cache warm-up."
elif [ "${APP_ENV:-local}" = "production" ]; then
    # Each long-running container compiles its own caches into its own tmpfs.
    "${RUN_AS[@]}" php artisan optimize:clear
    "${RUN_AS[@]}" php artisan config:cache
    "${RUN_AS[@]}" php artisan route:cache
    "${RUN_AS[@]}" php artisan view:cache
    "${RUN_AS[@]}" php artisan event:cache
else
    # Dev/local: auto-migrate, no caches (better DX).
    "${RUN_AS[@]}" php artisan migrate --force || true
    "${RUN_AS[@]}" php artisan optimize:clear || true
fi

# Drop privileges for the main process.
exec gosu www-data:www-data "$@"
