#!/bin/bash
# ============================================================
# zz_apply_migrations.sh   (runs LAST on a fresh `docker compose up`)
#
# full_dump.sql is a point-in-time prod export and can lag current prod by
# a few migrations. This applies the schema migrations on top of it so a
# fresh local DB matches CURRENT production.
#
# --force = skip "duplicate column / table exists" when a migration is
#           already baked into the dump, without aborting init.
#
# SKIP list = data-backfill migrations that INSERT rows; re-running them on
#             a dump that already has the data would DUPLICATE it. They are
#             one-time and already reflected in the dump.
# ============================================================
set -u
MYSQL="mysql --force -uroot -p${MYSQL_ROOT_PASSWORD} ${MYSQL_DATABASE}"

SKIP="migrate_donors_to_inventory.sql migration_articles_images_v2.sql"

echo "[zz-migrations] applying DDL migrations on top of full_dump.sql ..."
for f in /docker-entrypoint-initdb.d/migrations/*.sql; do
    [ -e "$f" ] || continue
    base=$(basename "$f")
    case " $SKIP " in *" $base "*) echo "  -- skip $base (data-backfill)"; continue;; esac
    echo "  -> $base"
    $MYSQL < "$f" 2>&1 | grep -iv "using a password" | grep -i "error" && echo "     (non-fatal, continued)" || true
done
echo "[zz-migrations] done."
