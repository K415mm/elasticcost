<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$dbPath = config('harness.cache.db_path') ?: \Phpkaiharness\Monitor\SqliteMonitorStore::defaultDbPath();
echo "DB Path: {$dbPath}\n";

$semanticMemory = null;
if (app()->bound(\Phpkaiharness\Contracts\SemanticMemoryInterface::class)) {
    $semanticMemory = app(\Phpkaiharness\Contracts\SemanticMemoryInterface::class);
}

$cache = new \Phpkaiharness\Optimize\SemanticCache(
    pdo: new PDO('sqlite:' . $dbPath),
    threshold: 0.88,
    semanticMemory: $semanticMemory
);

$prompt = "List all clients in the system with their current device counts.";
echo "Looking up: '{$prompt}'\n";
$hit = $cache->lookup($prompt);

if ($hit !== null) {
    echo "CACHE HIT!\n";
    echo "Response: " . substr($hit, 0, 150) . "...\n";
} else {
    echo "CACHE MISS!\n";
}
