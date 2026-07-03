#!/bin/bash
# ============================================================
# zz_apply_migrations.sh
# Docker's /docker-entrypoint-initdb.d only runs top-level *.sql,
# it does NOT recurse into ./migrations. This script (named zz_* so it
# runs LAST on a fresh `docker compose up`) applies every migration
# after full_dump.sql + schema_missing_tables.sql + the seeds.
#
# --force = keep going past "duplicate column / table exists" errors so
# migrations already reflected in full_dump don't abort the whole init.
# ============================================================
set -u
MYSQL="mysql --force -uroot -p${MYSQL_ROOT_PASSWORD} ${MYSQL_DATABASE}"

echo "[zz-migrations] applying database/migrations/*.sql ..."
for f in /docker-entrypoint-initdb.d/migrations/*.sql; do
    [ -e "$f" ] || continue
    echo "  -> $(basename "$f")"
    $MYSQL < "$f" 2>&1 | grep -iv "using a password" | grep -i "error" && echo "     (non-fatal, continued)" || true
done
echo "[zz-migrations] done."
