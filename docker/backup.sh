#!/usr/bin/env bash
set -euo pipefail

COMPOSE=(docker compose -f docker-compose.prod.yml)
BACKUP_DIR="${BACKUP_DIR:-./backups}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-14}"
STAMP="$(date +%Y%m%d-%H%M%S)"

mkdir -p "$BACKUP_DIR"

DB_BACKUP="${BACKUP_DIR}/${STAMP}-database.sql.gz"
STORAGE_BACKUP="${BACKUP_DIR}/${STAMP}-storage.tar.gz"

echo "==> Creating database backup: ${DB_BACKUP}"
"${COMPOSE[@]}" exec -T mysql sh -lc \
    'mariadb-dump --single-transaction --quick -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' \
    | gzip -9 > "$DB_BACKUP"

echo "==> Creating storage backup: ${STORAGE_BACKUP}"
"${COMPOSE[@]}" exec -T app tar -C /var/www/html/storage -czf - . > "$STORAGE_BACKUP"

echo "==> Removing backups older than ${RETENTION_DAYS} days"
find "$BACKUP_DIR" -type f \( -name '*-database.sql.gz' -o -name '*-storage.tar.gz' \) -mtime +"$RETENTION_DAYS" -delete

echo "==> Backup finished"
