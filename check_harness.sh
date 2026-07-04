#!/bin/bash
echo "=== Octane phpkaiharness dir ==="
docker exec elasticcost-octane ls -la /var/www/storage/app/phpkaiharness/ 2>&1
echo "=== Octane pdo_sqlite ==="
docker exec elasticcost-octane php -r 'echo extension_loaded("pdo_sqlite") ? "pdo_sqlite loaded" : "pdo_sqlite NOT loaded"; echo "\n";' 2>&1
echo "=== Worker pdo_sqlite ==="
docker exec elasticcost-worker php -r 'echo extension_loaded("pdo_sqlite") ? "pdo_sqlite loaded" : "pdo_sqlite NOT loaded"; echo "\n";' 2>&1
echo "=== Octane harness config ==="
docker exec elasticcost-octane php -r 'require "/var/www/vendor/autoload.php"; $app = require "/var/www/bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo "db_path: " . config("harness.cache.db_path", "NOT SET") . "\n"; echo "session_isolation: " . (config("harness.session_isolation", false) ? "true" : "false") . "\n";' 2>&1
echo "=== Worker harness config ==="
docker exec elasticcost-worker php -r 'require "/var/www/vendor/autoload.php"; $app = require "/var/www/bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo "db_path: " . config("harness.cache.db_path", "NOT SET") . "\n"; echo "session_isolation: " . (config("harness.session_isolation", false) ? "true" : "false") . "\n";' 2>&1
echo "=== Worker recent logs ==="
docker exec elasticcost-worker php -r 'require "/var/www/vendor/autoload.php"; $app = require "/var/www/bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); $logs = Illuminate\Support\Facades\Log::getLogger()->getHandlers(); echo "Log handlers: " . count($logs) . "\n";' 2>&1
echo "=== Check Laravel logs for analytics errors ==="
docker exec elasticcost-worker tail -30 /var/www/storage/logs/laravel.log 2>&1 | grep -i "analytics\|collector\|sqlite\|monitor\|session" | tail -10
