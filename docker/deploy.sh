#!/usr/bin/env bash
# =========================================================
# PeopleManager - production Docker deploy (build on VPS)
#
# Usage:
#   ./docker/deploy.sh
#
# Expects to be run from project root on the VPS.
# Requires: docker, docker compose plugin, git, .env on VPS.
# =========================================================
set -euo pipefail

COMPOSE_FILE="docker-compose.prod.yml"
COMPOSE="docker compose -f ${COMPOSE_FILE}"
EXTERNAL_APP_IMAGE="${APP_IMAGE:-}"
EXTERNAL_GIT_SHA="${GIT_SHA:-}"
EXTERNAL_DEPLOY_IMAGE_MODE="${DEPLOY_IMAGE_MODE:-}"

if [ ! -f ".env" ]; then
    echo "ERROR: .env not found in $(pwd). Aborting." >&2
    exit 1
fi

set -a
# shellcheck disable=SC1091
source .env
set +a

if [ -n "$EXTERNAL_APP_IMAGE" ]; then
    APP_IMAGE="$EXTERNAL_APP_IMAGE"
    export APP_IMAGE
fi

if [ -n "$EXTERNAL_GIT_SHA" ]; then
    GIT_SHA="$EXTERNAL_GIT_SHA"
fi

if [ -n "$EXTERNAL_DEPLOY_IMAGE_MODE" ]; then
    DEPLOY_IMAGE_MODE="$EXTERNAL_DEPLOY_IMAGE_MODE"
fi

BRANCH="${DEPLOY_BRANCH:-master}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/peoplemanager}"
BACKUP_RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-14}"
MAINTENANCE_SECRET="${MAINTENANCE_SECRET:-}"
DEPLOY_IMAGE_MODE="${DEPLOY_IMAGE_MODE:-build}"

# Maintenance is taken UP only on success.
# On failure the operator must investigate before bringing a half-migrated app back.
DEPLOY_SUCCESS=0
trap '
    rc=$?
    if [ "$DEPLOY_SUCCESS" -ne 1 ]; then
        echo "DEPLOY FAILED (exit=$rc). Maintenance mode is left on if it was enabled." >&2
        echo "Investigate, then run: ${COMPOSE} exec -T -u www-data app php artisan up" >&2
    fi
    exit $rc
' EXIT

echo "==> [1/9] Fetching latest code from origin/${BRANCH}"
git fetch --all --prune
if [ -n "${GIT_SHA:-}" ]; then
    git checkout --force "$GIT_SHA"
else
    git reset --hard "origin/${BRANCH}"
fi

if [ "$DEPLOY_IMAGE_MODE" = "pull" ]; then
    echo "==> [2/9] Pulling application image: ${APP_IMAGE:?APP_IMAGE must be set when DEPLOY_IMAGE_MODE=pull}"
    ${COMPOSE} pull app queue scheduler
else
    echo "==> [2/9] Building application image"
    ${COMPOSE} build --pull app
fi

APP_RUNNING=0
if ${COMPOSE} ps --services --filter status=running 2>/dev/null | grep -qx 'app'; then
    APP_RUNNING=1
fi

if [ "$APP_RUNNING" -eq 1 ]; then
    echo "==> [3/9] Enabling maintenance mode on old Docker app"
    MAINTENANCE_TARGET=docker COMPOSE_FILE="$COMPOSE_FILE" bash docker/maintenance.sh down || true
else
    echo "==> [3/9] No running Docker app, skipping Docker maintenance"
fi

echo "==> [4/9] Starting MariaDB"
${COMPOSE} up -d mysql

echo "==> [5/9] Creating database backup"
mkdir -p "$BACKUP_DIR"
DB_BACKUP="${BACKUP_DIR}/$(date +%Y%m%d-%H%M%S)-database.sql.gz"
${COMPOSE} exec -T mysql sh -lc \
    'mariadb-dump --single-transaction --quick -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' \
    | gzip -9 > "$DB_BACKUP"

find "$BACKUP_DIR" -type f -name '*-database.sql.gz' -mtime +"$BACKUP_RETENTION_DAYS" -delete
echo "==> Database backup saved: ${DB_BACKUP}"

echo "==> [6/9] Running migrations on new image"
if ! ${COMPOSE} run --rm --no-deps \
        -e CONTAINER_ROLE=migrate \
        app php artisan migrate --force; then
    echo "ERROR: migration failed. Stack NOT swapped, maintenance NOT lifted." >&2
    exit 1
fi

echo "==> [7/9] Bringing up new app/queue/scheduler"
${COMPOSE} up -d --no-deps --remove-orphans app queue scheduler

echo "==> [8/9] Waiting for app to become healthy (timeout 150s)"
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

echo "==> [9/9] Restarting nginx + queue workers"
${COMPOSE} up -d --no-deps --force-recreate nginx
${COMPOSE} exec -T -u www-data app php artisan queue:restart

echo "==> Disabling maintenance mode + pruning project images"
MAINTENANCE_TARGET=docker COMPOSE_FILE="$COMPOSE_FILE" bash docker/maintenance.sh up
docker image prune -f --filter "label=org.opencontainers.image.source" >/dev/null

DEPLOY_SUCCESS=1
echo ""
echo "==> Deploy finished successfully."
${COMPOSE} ps
