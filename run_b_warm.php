<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Services\TestCompare\TestRunner;
use Illuminate\Contracts\Console\Kernel;

// Save to a separate output dir so the cold B traces are preserved for comparison
$outputDir = base_path('testandcompare-warm');

echo "Starting B-full-harness WARM run (cache + memory pre-loaded)...\n";
echo "Output dir: {$outputDir}\n\n";

$runner = new TestRunner($outputDir);
$result = $runner->runAll(function ($mode, $index, $total, $status) {
    echo "[{$mode}] Request ".($index + 1)."/{$total} — {$status}\n";
}, 'B-full-harness');

echo "\nDone. Traces: ".count($result['traces'])."\n";
