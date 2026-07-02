<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Services\TestCompare\TestRunner;
use Illuminate\Contracts\Console\Kernel;

echo "Starting A2-loop-no-features...\n";

$runner = new TestRunner;
$result = $runner->runAll(function ($mode, $index, $total, $status) {
    echo "[{$mode}] Request ".($index + 1)."/{$total} — {$status}\n";
}, 'A2-loop-no-features');

echo "\nDone. Traces: ".count($result['traces'])."\n";
