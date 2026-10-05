#!/bin/sh
# Backup lógico completo (formato custom, compactado). Agende diariamente e
# envie para armazenamento externo com retenção (ex.: S3 com lifecycle).
#   PGHOST=... PGUSER=tenantly_migrator PGPASSWORD=... ./docker/backup/backup.sh
set -eu
DB="${DB_DATABASE:-tenantly}"
OUT="${BACKUP_DIR:-./backups}/${DB}-$(date -u +%Y%m%dT%H%M%SZ).dump"
mkdir -p "$(dirname "$OUT")"
pg_dump --format=custom --no-owner --no-privileges --dbname "$DB" --file "$OUT"
pg_restore --list "$OUT" > /dev/null   # valida que o arquivo é legível
echo "$OUT"
