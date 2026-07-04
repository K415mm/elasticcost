#!/bin/bash
# ============================================================
# ElasticCost — Full Cache Clear & Server Restart Script
# Usage: bash restart_all.sh
# ============================================================

set -e

cd /var/www/elasticcost

echo "=== [1/8] Pull latest code ==="
git pull origin main

echo "=== [2/8] Clear Laravel caches (app container) ==="
docker exec elasticcost-app php artisan optimize:clear
docker exec elasticcost-app php artisan config:clear
docker exec elasticcost-app php artisan route:clear
docker exec elasticcost-app php artisan view:clear
docker exec elasticcost-app php artisan event:clear

echo "=== [3/8] Clear Laravel caches (octane container) ==="
docker exec elasticcost-octane php artisan optimize:clear 2>/dev/null || true
docker exec elasticcost-octane php artisan config:clear
docker exec elasticcost-octane php artisan route:clear
docker exec elasticcost-octane php artisan view:clear
docker exec elasticcost-octane php artisan event:clear

echo "=== [4/8] Clear Redis cache + sessions + queues ==="
docker exec elasticcost-redis redis-cli FLUSHALL

echo "=== [5/8] Clear cached views and logs ==="
docker exec elasticcost-app sh -c 'rm -rf /var/www/storage/framework/views/* /var/www/storage/framework/cache/* /var/www/storage/logs/*.log 2>/dev/null || true'
docker exec elasticcost-octane sh -c 'rm -rf /var/www/storage/framework/views/* 2>/dev/null || true'

echo "=== [6/8] Clear Horizon queue + failed jobs ==="
docker exec elasticcost-app php artisan horizon:clear --force 2>/dev/null || true
docker exec elasticcost-app php artisan queue:flush 2>/dev/null || true

echo "=== [7/8] Restart all containers ==="
docker compose restart

echo "=== [8/8] Wait for containers to be healthy ==="
sleep 5
docker compose ps

echo ""
echo "=== Done! All caches cleared and servers restarted. ==="
