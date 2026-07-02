<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Services\TestCompare\TestCompareReportGenerator;
use Illuminate\Contracts\Console\Kernel;

$outputDir = base_path('testandcompare');

// Load all traces
$allTraces = [];
foreach (['A1-direct-api', 'A2-loop-no-features', 'B-full-harness'] as $mode) {
    $dir = $outputDir.'/traces/'.$mode;
    $files = glob($dir.'/*.json');
    $traces = [];
    foreach ($files as $f) {
        $traces[] = json_decode(file_get_contents($f), true);
    }
    $allTraces[$mode] = $traces;
}

echo "Generating comparison report...\n";

// Build a basic summary from traces
$summary = [];
foreach ($allTraces as $mode => $traces) {
    $total = count($traces);
    $success = count(array_filter($traces, fn ($t) => $t['success'] ?? false));
    $latencies = array_column(array_column($traces, 'timing'), 'latency_ms');
    $summary[$mode] = [
        'total' => $total,
        'success' => $success,
        'avg_latency_ms' => $total > 0 ? array_sum($latencies) / $total : 0,
    ];
}

$generator = new TestCompareReportGenerator($allTraces, $summary, $outputDir);
$report = $generator->generate();

file_put_contents($outputDir.'/comparison-report.md', $report);

echo "Report saved to testandcompare/comparison-report.md\n";
echo 'Length: '.number_format(strlen($report))." chars\n";
