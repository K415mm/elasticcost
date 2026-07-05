<?php

use App\Services\AiConfigHelper;
use Illuminate\Contracts\Console\Kernel;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Providers\DummyTextProvider;
use Phpkaiharness\Core\Agents\AnonymousAgent;
use Phpkaiharness\Optimize\OntologicalContextInjector;
use Phpkaiharness\Optimize\QuantumInferenceEngine;
use Phpkaiharness\Optimize\SemanticCache;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

echo "=== Testing Embedding Generation ===\n";
$config = AiConfigHelper::configureEmbeddings();
echo 'Provider: '.$config['provider']."\n";
echo 'Model: '.$config['model']."\n";

try {
    $response = Embeddings::for(['test query about MSSP pricing'])->generate($config['provider'], $config['model']);
    $vector = $response->first();
    if (! empty($vector)) {
        echo 'SUCCESS: Generated embedding with '.count($vector)." dimensions\n";
        echo 'First 5 values: '.implode(', ', array_slice($vector, 0, 5))."\n";
    } else {
        echo "FAILED: Empty vector returned\n";
    }
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
}

echo "\n=== Testing OntologicalContextInjector ===\n";
try {
    $injector = new OntologicalContextInjector;
    $prompt = new AgentPrompt(
        agent: new AnonymousAgent('', [], []),
        prompt: 'What is MSSP SOC staffing pricing?',
        attachments: [],
        provider: new DummyTextProvider,
        model: 'qwen-plus'
    );
    $metadata = [];
    $result = $injector->inject($prompt, 'App\Models\ClientAsset', 'embedding', 0.30, 3, $metadata);
    echo 'Metadata: '.json_encode($metadata, JSON_PRETTY_PRINT)."\n";
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
}

echo "\n=== Testing SemanticCache ===\n";
try {
    $cache = new SemanticCache;
    $result = $cache->lookup('test query about MSSP pricing');
    echo 'Cache lookup result: '.json_encode($result)."\n";
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
}

echo "\n=== Testing QuantumInferenceEngine ===\n";
try {
    $engine = new QuantumInferenceEngine;
    $anchors = $engine->retrieveAnchors('test query about MSSP pricing', 3);
    echo 'Anchors retrieved: '.count($anchors)."\n";
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
}
