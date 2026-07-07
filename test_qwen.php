<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Qwen connection config:\n";
var_dump(config('ai.providers.qwen'));

echo "\nCalling Qwen Embeddings...\n";
try {
    $response = \Laravel\Ai\Embeddings::for(['test'])->generate('qwen');
    var_dump($response->first());
} catch (\Throwable $e) {
    echo "Embeddings Error: " . $e->getMessage() . "\n";
}
