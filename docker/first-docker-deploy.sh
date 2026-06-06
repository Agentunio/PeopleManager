#!/usr/bin/env bash
# =========================================================
# PeopleManager - first production cutover from legacy host install to Docker.
#
# This script freezes the legacy Laravel app before the final database dump.
# That prevents writes made after the dump from being lost in the Docker stack.
#
# Requires: docker, docker compose plugin, git, mysqldump, gzip.
#
# Required env:
#   LEGACY_APP_DIR=/var/www/current-app
#
# Optional env:
#   LEGACY_DB_HOST=127.0.0.1
#   LEGACY_DB_PORT=3306
#   LEGACY_DB_DATABASE=...
#   LEGACY_DB_USERNAME=...
#   LEGACY_DB_PASSWORD=...
#   MAINTENANCE_SECRET=...
#   BACKUP_DIR=/var/backups/peoplemanager
#   ALLOW_DOCKER_DB_OVERWRITE=1
#   LEGACY_WEB_SERVICE=apache2
# =========================================================
set -euo pipefail

COMPOSE_FILE="docker-compose.prod.yml"
COMPOSE="docker compose -f ${COMPOSE_FILE}"
LEGACY_APP_DIR="${LEGACY_APP_DIR:-}"
LEGACY_DB_HOST="${LEGACY_DB_HOST:-127.0.0.1}"
LEGACY_DB_PORT="${LEGACY_DB_PORT:-3306}"
LEGACY_WEB_SERVICE="${LEGACY_WEB_SERVICE:-}"

if [ ! -f ".env" ]; then
    echo "ERROR: .env not found in $(pwd). Aborting." >&2
    exit 1
fi

set -a
# shellcheck disable=SC1091
source .env
set +a

BRANCH="${DEPLOY_BRANCH:-master}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/peoplemanager}"
MAINTENANCE_SECRET="${MAINTENANCE_SECRET:-}"
LEGACY_APP_DIR="${LEGACY_APP_DIR:-}"
LEGACY_DB_DATABASE="${LEGACY_DB_DATABASE:-${DB_DATABASE:?DB_DATABASE must be set in .env}}"
LEGACY_DB_USERNAME="${LEGACY_DB_USERNAME:-${DB_USERNAME:?DB_USERNAME must be set in .env}}"
LEGACY_DB_PASSWORD="${LEGACY_DB_PASSWORD:-${DB_PASSWORD:?DB_PASSWORD must be set in .env}}"

if [ -z "$LEGACY_APP_DIR" ] || [ ! -d "$LEGACY_APP_DIR" ]; then
    echo "ERROR: LEGACY_APP_DIR must point to the current non-Docker Laravel app." >&2
    exit 1
fi

if [ ! -f "${LEGACY_APP_DIR}/artisan" ]; then
    echo "ERROR: ${LEGACY_APP_DIR} does not look like a Laravel app." >&2
    exit 1
fi

if command -v ss >/dev/null 2>&1 \
    && ss -ltnH | awk '{print $4}' | grep -Eq '(:80|:443)$' \
    && [ -z "$LEGACY_WEB_SERVICE" ]; then
    echo "ERROR: ports 80/443 are in use and LEGACY_WEB_SERVICE is not set." >&2
    echo "Set LEGACY_WEB_SERVICE, for example LEGACY_WEB_SERVICE=apache2 or LEGACY_WEB_SERVICE=nginx." >&2
    exit 1
fi

mkdir -p "$BACKUP_DIR"
DB_BACKUP="${BACKUP_DIR}/$(date +%Y%m%d-%H%M%S)-legacy-database.sql.gz"

CUTOVER_SUCCESS=0
trap '
    rc=$?
    if [ "$CUTOVER_SUCCESS" -ne 1 ]; then
        echo "CUTOVER FAILED (exit=$rc). Legacy app is left in maintenance mode to protect data consistency." >&2
        echo "Investigate, then either rerun this script or manually run: cd \"${LEGACY_APP_DIR}\" && php artisan up" >&2
    fi
    exit $rc
' EXIT

echo "==> [1/10] Enabling maintenance mode on legacy app"
MAINTENANCE_TARGET=local APP_DIR="$LEGACY_APP_DIR" bash docker/maintenance.sh down

echo "==> [2/10] Dumping legacy database after maintenance lock"
mysqldump \
    --single-transaction \
    --quick \
    -h"$LEGACY_DB_HOST" \
    -P"$LEGACY_DB_PORT" \
    -u"$LEGACY_DB_USERNAME" \
    -p"$LEGACY_DB_PASSWORD" \
    "$LEGACY_DB_DATABASE" \
    | gzip -9 > "$DB_BACKUP"
echo "==> Legacy database backup saved: ${DB_BACKUP}"

echo "==> [3/10] Fetching latest code from origin/${BRANCH}"
git fetch --all --prune
git reset --hard "origin/${BRANCH}"

echo "==> [4/10] Building application image"
${COMPOSE} build --pull app

echo "==> [5/10] Starting Docker MariaDB"
${COMPOSE} up -d mysql

echo "==> [6/10] Checking Docker database target"
TABLE_COUNT="$(${COMPOSE} exec -T mysql sh -lc 'mariadb -N -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE();"' | tr -d '[:space:]')"
if [ "$TABLE_COUNT" != "0" ] && [ "${ALLOW_DOCKER_DB_OVERWRITE:-0}" != "1" ]; then
    echo "ERROR: Docker database is not empty (${TABLE_COUNT} tables)." >&2
    echo "Set ALLOW_DOCKER_DB_OVERWRITE=1 only if you intentionally want to replace it." >&2
    exit 1
fi

if [ "$TABLE_COUNT" != "0" ]; then
    echo "==> Docker database has ${TABLE_COUNT} tables; dropping and recreating because ALLOW_DOCKER_DB_OVERWRITE=1"
    ${COMPOSE} exec -T mysql sh -lc 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" -e "DROP DATABASE \`$MARIADB_DATABASE\`; CREATE DATABASE \`$MARIADB_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON \`$MARIADB_DATABASE\`.* TO '\''$MARIADB_USER'\''@'\''%'\'';"'
fi

echo "==> [7/10] Importing legacy database into Docker MariaDB"
gzip -dc "$DB_BACKUP" | ${COMPOSE} exec -T mysql sh -lc 'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"'

echo "==> [8/10] Running migrations on Docker app image"
${COMPOSE} run --rm --no-deps \
    -e CONTAINER_ROLE=migrate \
    app php artisan migrate --force

if [ -n "$LEGACY_WEB_SERVICE" ]; then
    echo "==> Stopping legacy web service: ${LEGACY_WEB_SERVICE}"
    sudo systemctl stop "$LEGACY_WEB_SERVICE"
fi

if command -v ss >/dev/null 2>&1 && ss -ltnH | awk '{print $4}' | grep -Eq '(:80|:443)$'; then
    echo "ERROR: ports 80/443 are still in use. Stop the legacy web server, then rerun this script." >&2
    exit 1
fi

echo "==> [9/10] Starting Docker app stack"
${COMPOSE} up -d --remove-orphans app queue scheduler nginx

echo "==> [10/10] Waiting for Docker app health and leaving maintenance"
ATTEMPTS=0
until [ "$(docker inspect -f '{{.State.Health.Status}}' peoplemanager-app 2>/dev/null || echo starting)" = "healthy" ]; do
    ATTEMPTS=$((ATTEMPTS+1))
    if [ "$ATTEMPTS" -ge 75 ]; then
        echo "ERROR: app did not become healthy in 150s" >&2
        ${COMPOSE} logs --tail=80 app >&2
        exit 1
    fi
    sleep 2
done

${COMPOSE} exec -T app php artisan queue:restart
MAINTENANCE_TARGET=docker COMPOSE_FILE="$COMPOSE_FILE" bash docker/maintenance.sh up

CUTOVER_SUCCESS=1
echo ""
echo "==> First Docker deploy finished successfully."
echo "==> Legacy app remains in maintenance mode. Keep it disabled or stop its old web server after verification."
${COMPOSE} ps
