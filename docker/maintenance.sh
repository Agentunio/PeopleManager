#!/usr/bin/env bash
# =========================================================
# PeopleManager - maintenance mode helper.
#
# Usage:
#   ./docker/maintenance.sh down
#   ./docker/maintenance.sh up
#   ./docker/maintenance.sh status
#
# Docker target is default. For legacy/local Laravel app:
#   MAINTENANCE_TARGET=local APP_DIR=/var/www/current-app ./docker/maintenance.sh down
# =========================================================
set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

ACTION="${1:-down}"
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"
MAINTENANCE_TARGET="${MAINTENANCE_TARGET:-docker}"
MAINTENANCE_RENDER="${MAINTENANCE_RENDER:-errors::503}"
MAINTENANCE_RETRY="${MAINTENANCE_RETRY:-60}"
MAINTENANCE_SECRET="${MAINTENANCE_SECRET:-}"
APP_DIR="${APP_DIR:-${LEGACY_APP_DIR:-$PROJECT_ROOT}}"

case "$COMPOSE_FILE" in
    /*) ;;
    *) COMPOSE_FILE="${PROJECT_ROOT}/${COMPOSE_FILE}" ;;
esac

COMPOSE=(docker compose -f "$COMPOSE_FILE")

run_artisan() {
    if [ "$MAINTENANCE_TARGET" = "docker" ]; then
        "${COMPOSE[@]}" exec -T app php artisan "$@"
        return
    fi

    (
        cd "$APP_DIR"
        php artisan "$@"
    )
}

show_status() {
    if [ "$MAINTENANCE_TARGET" = "docker" ]; then
        "${COMPOSE[@]}" exec -T app sh -lc 'if test -f storage/framework/down; then echo down; else echo up; fi'
        return
    fi

    if [ -f "${APP_DIR}/storage/framework/down" ]; then
        echo "down"
    else
        echo "up"
    fi
}

case "$ACTION" in
    down)
        DOWN_ARGS=(down --render="$MAINTENANCE_RENDER" --retry="$MAINTENANCE_RETRY")
        if [ -n "$MAINTENANCE_SECRET" ]; then
            DOWN_ARGS+=(--secret="$MAINTENANCE_SECRET")
        fi
        run_artisan "${DOWN_ARGS[@]}"
        ;;
    up)
        run_artisan up
        ;;
    status)
        show_status
        ;;
    *)
        echo "Usage: $0 down|up|status" >&2
        exit 2
        ;;
esac
