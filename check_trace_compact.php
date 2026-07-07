<?php

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$base = base_path('testandcompare/latest/traces');

// Show file names
echo "=== File names ===\n";
$files = glob($base.'/B-full-harness/*.json') ?: [];
sort($files);
foreach (array_slice($files, 0, 3) as $f) {
    echo basename($f)."\n";
}

// Show compact structure of first B trace
echo "\n=== B-full-harness first trace (compact) ===\n";
if (! empty($files)) {
    $t = json_decode(file_get_contents($files[0]), true);
    echo 'Top-level keys: '.implode(', ', array_keys($t))."\n";
    echo 'timing: '.json_encode($t['timing'] ?? 'MISSING')."\n";
    echo 'tokens: '.json_encode($t['tokens'] ?? 'MISSING')."\n";
    echo 'features keys: '.implode(', ', array_keys($t['features'] ?? []))."\n";
    echo 'phpkaiharness keys: '.implode(', ', array_keys($t['phpkaiharness'] ?? []))."\n";
    echo 'cache: '.json_encode($t['cache'] ?? 'MISSING')."\n";
    echo 'pipeline_stages count: '.count($t['pipeline_stages'] ?? [])."\n";
    echo 'pipeline_stages[0]: '.json_encode(($t['pipeline_stages'] ?? [])[0] ?? 'empty')."\n";
    echo 'success: '.json_encode($t['success'] ?? 'MISSING')."\n";
    echo 'ai_evaluation: '.json_encode($t['ai_evaluation'] ?? 'MISSING')."\n";
    echo 'request_index: '.json_encode($t['request_index'] ?? 'MISSING')."\n";
    echo 'category: '.json_encode($t['category'] ?? 'MISSING')."\n";
    echo 'agent: '.json_encode($t['agent'] ?? 'MISSING')."\n";
    echo 'mode: '.json_encode($t['mode'] ?? 'MISSING')."\n";
    echo 'run_id: '.json_encode($t['run_id'] ?? 'MISSING')."\n";

    // Deep-dive phpkaiharness data
    if (isset($t['phpkaiharness'])) {
        echo "\nphpkaiharness detail:\n";
        foreach ($t['phpkaiharness'] as $k => $v) {
            echo "  $k: ".json_encode($v)."\n";
        }
    }
}

// Show compact structure of first A1 trace
echo "\n=== A1-direct-api first trace (compact) ===\n";
$filesA1 = glob($base.'/A1-direct-api/*.json') ?: [];
sort($filesA1);
if (! empty($filesA1)) {
    $t = json_decode(file_get_contents($filesA1[0]), true);
    echo 'Top-level keys: '.implode(', ', array_keys($t))."\n";
    echo 'timing: '.json_encode($t['timing'] ?? 'MISSING')."\n";
    echo 'tokens: '.json_encode($t['tokens'] ?? 'MISSING')."\n";
    echo 'success: '.json_encode($t['success'] ?? 'MISSING')."\n";
    echo 'ai_evaluation: '.json_encode($t['ai_evaluation'] ?? 'MISSING')."\n";
}
