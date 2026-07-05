<?php

use App\Models\GlobalSetting;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

echo 'DB: '.DB::connection()->getDatabaseName()."\n";
echo 'Embedding Provider: '.GlobalSetting::getValue('rag_embedding_provider', 'ollama')."\n";
echo 'Embedding Model: '.GlobalSetting::getValue('rag_embedding_model', 'nomic-embed-text')."\n";
echo 'AI Provider: '.GlobalSetting::getValue('ai_provider', 'qwen')."\n";
echo 'AI Model: '.GlobalSetting::getValue('ai_model', 'qwen-plus')."\n";

// Check SQLite DBs
$dbDir = storage_path('app/phpkaiharness');
echo "\nSQLite DBs:\n";
if (is_dir($dbDir)) {
    foreach (glob($dbDir.'/*.sqlite') as $db) {
        echo '  '.basename($db).' ('.filesize($db)." bytes)\n";
    }
} else {
    echo "  Directory does not exist\n";
}

// Check harness config
echo "\nHarness Config:\n";
echo '  ontology.enabled: '.config('harness.feature_graph.nodes.ontology_injection.enabled')."\n";
echo '  semantic_cache.enabled: '.config('harness.feature_graph.nodes.semantic_cache.enabled')."\n";
echo '  quantum_harness.enabled: '.config('harness.feature_graph.nodes.quantum_harness.enabled')."\n";
echo '  cognitive_memory.enabled: '.config('harness.feature_graph.nodes.cognitive_memory.enabled')."\n";
