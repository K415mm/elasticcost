<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $dbPath = config('harness.cache.db_path') ?: \Phpkaiharness\Monitor\SqliteMonitorStore::defaultDbPath();
    if (file_exists($dbPath)) {
        $db = new PDO('sqlite:' . $dbPath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "=== LATEST FEATURE MATRIX EVENTS ===\n";
        $stmt = $db->query("SELECT * FROM harness_details WHERE type = 'feature_matrix' ORDER BY id DESC LIMIT 3");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "ID: {$row['id']}, SessionID: {$row['session_id']}, CreatedAt: {$row['created_at']}\n";
            echo "Payload: " . $row['payload'] . "\n\n";
        }
    } else {
        echo "Monitor DB file does not exist.\n";
    }
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
