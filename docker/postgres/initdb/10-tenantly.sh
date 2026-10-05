#!/bin/sh
# Executado pela imagem oficial do Postgres na primeira inicialização do volume.
set -eu
export PGUSER="$POSTGRES_USER"
export PGPASSWORD="${POSTGRES_PASSWORD:-}"
export PGHOST=/var/run/postgresql
sh /tenantly/postgres/setup.sh
