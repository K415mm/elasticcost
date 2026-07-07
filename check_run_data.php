<?php

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$base = base_path('testandcompare/latest/traces');
$modes = ['A1-direct-api', 'A2-loop-no-features', 'B-full-harness', 'B-warm-harness'];

echo "=== Trace Counts ===\n";
foreach ($modes as $m) {
    $files = glob($base.'/'.$m.'/*.json') ?: [];
    echo "$m: ".count($files)." traces\n";
}

echo "\n=== Comparison Summary ===\n";
$summaryFile = base_path('testandcompare/latest/comparison-summary.json');
if (file_exists($summaryFile)) {
    echo file_get_contents($summaryFile)."\n";
} else {
    echo "NOT FOUND\n";
}

echo "\n=== Sample B-full-harness trace-001 keys ===\n";
$f = $base.'/B-full-harness/request-001.json';
if (file_exists($f)) {
    $t = json_decode(file_get_contents($f), true);
    echo 'Top-level keys: '.implode(', ', array_keys($t))."\n";
    echo 'timing keys: '.implode(', ', array_keys($t['timing'] ?? []))."\n";
    echo 'tokens keys: '.implode(', ', array_keys($t['tokens'] ?? []))."\n";
    echo 'features keys: '.implode(', ', array_keys($t['features'] ?? []))."\n";
    echo 'phpkaiharness keys: '.implode(', ', array_keys($t['phpkaiharness'] ?? []))."\n";
    echo 'pipeline_stages count: '.count($t['pipeline_stages'] ?? [])."\n";
    echo 'ai_evaluation: '.($t['ai_evaluation'] ?? 'MISSING')."\n";
} else {
    echo "File not found\n";
}

echo "\n=== Sample A1 trace-001 keys ===\n";
$f = $base.'/A1-direct-api/request-001.json';
if (file_exists($f)) {
    $t = json_decode(file_get_contents($f), true);
    echo 'Top-level keys: '.implode(', ', array_keys($t))."\n";
    echo 'ai_evaluation: '.($t['ai_evaluation'] ?? 'MISSING')."\n";
} else {
    echo "File not found\n";
}
