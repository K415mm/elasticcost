<?php

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$base = base_path('testandcompare/latest/traces');

echo "=== B-full-harness file list ===\n";
$files = glob($base.'/B-full-harness/*.json') ?: [];
foreach (array_slice($files, 0, 3) as $f) {
    echo basename($f)."\n";
}

echo "\n=== First B-full-harness trace structure ===\n";
if (! empty($files)) {
    sort($files);
    $t = json_decode(file_get_contents($files[0]), true);
    echo 'Top-level keys: '.implode(', ', array_keys($t))."\n";
    echo 'timing: '.json_encode($t['timing'] ?? 'MISSING')."\n";
    echo 'tokens: '.json_encode($t['tokens'] ?? 'MISSING')."\n";
    echo 'features: '.json_encode(array_keys($t['features'] ?? []))."\n";
    echo 'phpkaiharness top-level: '.json_encode(array_keys($t['phpkaiharness'] ?? []))."\n";
    echo 'cache: '.json_encode($t['cache'] ?? 'MISSING')."\n";
    echo 'pipeline_stages: '.json_encode($t['pipeline_stages'] ?? 'MISSING')."\n";
    echo 'success: '.json_encode($t['success'] ?? 'MISSING')."\n";
    echo 'ai_evaluation: '.($t['ai_evaluation'] ?? 'MISSING')."\n";
    echo 'request_index: '.($t['request_index'] ?? 'MISSING')."\n";
    echo 'category: '.($t['category'] ?? 'MISSING')."\n";
    echo "\nFull trace:\n".json_encode($t, JSON_PRETTY_PRINT)."\n";
}

echo "\n=== First A1-direct-api trace structure ===\n";
$filesA1 = glob($base.'/A1-direct-api/*.json') ?: [];
if (! empty($filesA1)) {
    sort($filesA1);
    $t = json_decode(file_get_contents($filesA1[0]), true);
    echo 'Top-level keys: '.implode(', ', array_keys($t))."\n";
    echo "\nFull trace:\n".json_encode($t, JSON_PRETTY_PRINT)."\n";
}
