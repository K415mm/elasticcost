<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== ENVIRONMENT ===\n";
echo "Config Mode: " . config('harness.config_mode', 'not set') . "\n";
echo "Semantic Cache Node Enabled: " . (config('harness.feature_graph.nodes.semantic_cache.enabled') ? 'true' : 'false') . "\n";
echo "Quantum Harness Node Enabled: " . (config('harness.feature_graph.nodes.quantum_harness.enabled') ? 'true' : 'false') . "\n";
echo "Cache Enabled: " . (config('harness.cache.enabled') ? 'true' : 'false') . "\n";
echo "Quantum Harness Enabled: " . (config('harness.quantum_harness.enabled') ? 'true' : 'false') . "\n";

echo "\n=== MONITOR DB (Cache) ===\n";
try {
    $dbPath = config('harness.cache.db_path') ?: \Phpkaiharness\Monitor\SqliteMonitorStore::defaultDbPath();
    echo "DB Path: {$dbPath}\n";
    if (file_exists($dbPath)) {
        $db = new PDO('sqlite:' . $dbPath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $sessionsCount = $db->query('SELECT COUNT(*) FROM harness_sessions')->fetchColumn();
        echo "harness_sessions count: {$sessionsCount}\n";
        
        $detailsCount = $db->query('SELECT COUNT(*) FROM harness_details')->fetchColumn();
        echo "harness_details count: {$detailsCount}\n";
        
        echo "\nLatest 3 harness_sessions:\n";
        $stmt = $db->query('SELECT * FROM harness_sessions ORDER BY created_at DESC LIMIT 3');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "ID: {$row['id']}, Method: {$row['method']}, Prompt: " . substr($row['prompt'], 0, 80) . "...\n";
        }
    } else {
        echo "Monitor DB file does not exist.\n";
    }
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== QUANTUM MEMORY DB ===\n";
try {
    $qDbPath = config('harness.quantum_harness.db_path') ?: storage_path('app/phpkaiharness/agent_memory.sqlite');
    echo "Quantum DB Path: {$qDbPath}\n";
    if (file_exists($qDbPath)) {
        $qDb = new PDO('sqlite:' . $qDbPath);
        $qDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $nodesCount = $qDb->query('SELECT COUNT(*) FROM memory_nodes')->fetchColumn();
        echo "memory_nodes count: {$nodesCount}\n";
        
        echo "\nLatest 3 memory_nodes:\n";
        $stmt = $qDb->query('SELECT * FROM memory_nodes ORDER BY created_at DESC LIMIT 3');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "ID: {$row['id']}, Type: {$row['type']}, Phase: {$row['phase_angle']}, Content: " . substr($row['content'], 0, 80) . "...\n";
        }
    } else {
        echo "Quantum DB file does not exist.\n";
    }
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
