#!/bin/sh
# Restaura um dump em um banco SEPARADO (nunca sobre produção). Em seguida:
#   - restauração completa: troque a aplicação para o banco restaurado; ou
#   - restauração de UM tenant: php artisan tenants:restore <id> --source=restore_source
#     (configure a conexão restore_source apontando para este banco).
#   PGHOST=... PGUSER=postgres ./docker/backup/restore.sh arquivo.dump tenantly_restore
set -eu
DUMP="$1"
TARGET="${2:-tenantly_restore}"
psql --dbname postgres -v ON_ERROR_STOP=1 -c "DROP DATABASE IF EXISTS \"$TARGET\"" -c "CREATE DATABASE \"$TARGET\" OWNER tenantly_migrator"
pg_restore --no-owner --role=tenantly_migrator --dbname "$TARGET" --exit-on-error "$DUMP"
psql --dbname "$TARGET" -v ON_ERROR_STOP=1 -c "select count(*) as tenants from tenants"
