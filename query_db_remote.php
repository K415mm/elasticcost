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
        
        echo "=== COMPLETED SESSIONS ===\n";
        $stmt = $db->query("SELECT id, method, prompt, response FROM harness_sessions WHERE response != '' AND response IS NOT NULL LIMIT 5");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Found " . count($rows) . " completed sessions.\n";
        foreach ($rows as $row) {
            echo "ID: {$row['id']}, Method: {$row['method']}\n";
            echo "Prompt: {$row['prompt']}\n";
            echo "Response: " . substr($row['response'], 0, 100) . "...\n\n";
        }
    } else {
        echo "Monitor DB file does not exist.\n";
    }
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
