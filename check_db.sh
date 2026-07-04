#!/bin/bash
echo "=== Global monitor.db sessions ==="
docker exec elasticcost-octane php -r '
require "/var/www/vendor/autoload.php";
$app = require "/var/www/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$pdo = new PDO("sqlite:" . config("harness.cache.db_path"));
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$count = $pdo->query("SELECT COUNT(*) FROM harness_sessions")->fetchColumn();
echo "Total sessions: $count\n";
$rows = $pdo->query("SELECT id, method, status, total_duration_ms, created_at FROM harness_sessions ORDER BY created_at DESC LIMIT 5")->fetchAll();
foreach ($rows as $r) { echo json_encode($r) . "\n"; }
echo "\n=== Details count ===";
$dc = $pdo->query("SELECT COUNT(*) FROM harness_details")->fetchColumn();
echo "Total details: $dc\n";
$dt = $pdo->query("SELECT type, COUNT(*) as c FROM harness_details GROUP BY type")->fetchAll();
foreach ($dt as $r) { echo json_encode($r) . "\n"; }
echo "\n=== Recent details ===";
$rd = $pdo->query("SELECT session_id, type, name, duration_ms FROM harness_details ORDER BY created_at DESC LIMIT 5")->fetchAll();
foreach ($rd as $r) { echo json_encode($r) . "\n"; }
' 2>&1

echo "=== Worker recent laravel.log ==="
docker exec elasticcost-worker bash -c 'tail -50 /var/www/storage/logs/laravel.log 2>/dev/null | grep -i "analytics\|collector\|sqlite\|monitor\|session\|RgSocEngineer\|ProcessSoc" | tail -15' 2>&1
