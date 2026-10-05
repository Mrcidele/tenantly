#!/bin/sh
# Cria papéis e bancos do Tenantly. Usa as variáveis padrão do libpq
# (PGHOST, PGPORT, PGUSER, PGPASSWORD) apontando para um superusuário.
#
#   TENANTLY_DATABASES="tenantly tenantly_test" ./docker/postgres/setup.sh
set -eu

DIR="$(cd "$(dirname "$0")" && pwd)"
DATABASES="${TENANTLY_DATABASES:-tenantly}"

psql -v ON_ERROR_STOP=1 --dbname postgres \
    -v migrator_password="${DB_MIGRATOR_PASSWORD:-migrator}" \
    -v app_password="${DB_PASSWORD:-app}" \
    -v admin_password="${DB_ADMIN_PASSWORD:-admin}" \
    -f "$DIR/roles.sql"

for db in $DATABASES; do
    if ! psql --dbname postgres -tAc "SELECT 1 FROM pg_database WHERE datname = '$db'" | grep -q 1; then
        psql -v ON_ERROR_STOP=1 --dbname postgres -c "CREATE DATABASE \"$db\" OWNER tenantly_migrator"
    fi
    psql -v ON_ERROR_STOP=1 --dbname "$db" -f "$DIR/database.sql"
done
