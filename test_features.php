<?php

use App\Services\AiConfigHelper;
use Illuminate\Contracts\Console\Kernel;
use Laravel\Ai\Embeddings;
use Phpkaiharness\Optimize\QuantumInferenceEngine;
use Phpkaiharness\Optimize\SemanticCache;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

echo "=== Embedding Generation ===\n";
$config = AiConfigHelper::configureEmbeddings();
echo 'Provider: '.$config['provider'].' | Model: '.$config['model']."\n";
try {
    $response = Embeddings::for(['MSSP SOC staffing pricing'])->generate($config['provider'], $config['model']);
    $vector = $response->first();
    echo empty($vector) ? "FAILED: empty\n" : 'OK: '.count($vector)." dimensions\n";
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
}

echo "\n=== SemanticCache ===\n";
try {
    $cache = new SemanticCache;
    $result = $cache->lookup('test query');
    echo 'Lookup: '.($result === null ? 'null (expected for empty cache)' : json_encode($result))."\n";
    echo "Storing a cached response...\n";
    $embedding = Embeddings::for(['test query about MSSP'])->generate($config['provider'], $config['model'])->first() ?? [];
    $cache->store('test query about MSSP', 'cached response about MSSP pricing', $embedding);
    $hit = $cache->lookup('test query about MSSP');
    echo 'Second lookup: '.($hit ? 'HIT' : 'MISS')."\n";
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
}

echo "\n=== QuantumInferenceEngine ===\n";
try {
    $engine = new QuantumInferenceEngine;
    $pdo = $engine->getPdo();
    $count = $pdo->query('SELECT COUNT(*) as c FROM memory_nodes')->fetch();
    echo 'Memory nodes: '.$count['c']."\n";
    $stmt = $pdo->query('SELECT COUNT(*) as c FROM memory_vectors');
    $vcount = $stmt ? $stmt->fetch() : ['c' => 0];
    echo 'Memory vectors: '.$vcount['c']."\n";
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
}

echo "\n=== SQLite DBs ===\n";
$dbDir = storage_path('app/phpkaiharness');
foreach (glob($dbDir.'/*.sqlite') as $db) {
    echo basename($db).': '.filesize($db)." bytes\n";
}

echo "\n=== Harness Config ===\n";
echo 'ontology: '.(config('harness.feature_graph.nodes.ontology_injection.enabled') ? 'ON' : 'OFF')."\n";
echo 'semantic_cache: '.(config('harness.feature_graph.nodes.semantic_cache.enabled') ? 'ON' : 'OFF')."\n";
echo 'quantum_harness: '.(config('harness.feature_graph.nodes.quantum_harness.enabled') ? 'ON' : 'OFF')."\n";
echo 'cognitive_memory: '.(config('harness.feature_graph.nodes.cognitive_memory.enabled') ? 'ON' : 'OFF')."\n";
