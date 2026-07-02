#!/usr/bin/env bash

# Export PostgreSQL and Redis data from Kali WSL
set -e

EXPORT_DIR="/tmp/elasticcost_export"
mkdir -p "$EXPORT_DIR"

export PGPASSWORD="${PGPASSWORD:-secret}"
export PGUSER="${PGUSER:-elasticcost}"

echo "=== 1. Exporting PostgreSQL database (elasticcost) ==="
if command -v pg_dump &> /dev/null; then
    pg_dump -U "$PGUSER" -h localhost -d elasticcost -F c -b -v -f "$EXPORT_DIR/elasticcost_pg.dump" || \
    pg_dump -U postgres -h localhost -d elasticcost -F c -b -v -f "$EXPORT_DIR/elasticcost_pg.dump"
    echo "[SUCCESS] PostgreSQL dump saved to $EXPORT_DIR/elasticcost_pg.dump"

else
    echo "[WARNING] pg_dump command not found. Please install postgresql-client or run pg_dump manually."
fi

echo "=== 2. Exporting Redis Snapshot (dump.rdb) ==="
if command -v redis-cli &> /dev/null; then
    redis-cli SAVE || true
    REDIS_RDB=$(redis-cli config get dir | tail -n1)/dump.rdb
    if [ -f "$REDIS_RDB" ]; then
        cp "$REDIS_RDB" "$EXPORT_DIR/dump.rdb"
        echo "[SUCCESS] Redis dump saved to $EXPORT_DIR/dump.rdb"
    elif [ -f "/var/lib/redis/dump.rdb" ]; then
        cp /var/lib/redis/dump.rdb "$EXPORT_DIR/dump.rdb"
        echo "[SUCCESS] Redis dump copied from /var/lib/redis/dump.rdb"
    fi
else
    echo "[WARNING] redis-cli not found."
fi

echo ""
echo "=== EXPORT COMPLETED ==="
echo "Files located in: $EXPORT_DIR"
ls -lh "$EXPORT_DIR"
