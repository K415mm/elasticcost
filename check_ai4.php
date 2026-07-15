<?php

use App\Ai\Agents\SizingRegulator;
use App\Http\Controllers\SizingDashboardController;
use App\Models\Client;
use App\Models\Scenario;
use App\Services\AiConfigHelper;
use App\Services\SizingEngine;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$client = Client::find(2);
$scenario = Scenario::find(2);

echo "=== Full sizing analysis test (timeout=180) ===\n";
try {
    $controller = app(SizingDashboardController::class);
    // Call with a longer timeout by modifying the approach
    $data = app(SizingEngine::class)->calculate($client, $scenario);

    $sizingBreakdown = [
        'client_name' => $client->name,
        'scenario_name' => $scenario->name,
        'workload_profile' => $scenario->workload_profile,
        'retention_days' => $scenario->retention_days,
        'totals' => [
            'daily_raw_gb' => $data['totals']['daily_raw_gb'],
            'daily_indexed_gb' => $data['totals']['daily_indexed_gb'],
            'total_storage_footprint_gb' => $data['totals']['total_storage_footprint_gb'],
            'total_ram_gb' => $data['licensing']['total_ram_gb'],
            'required_erus' => $data['licensing']['required_erus'],
            'annual_license_cost_usd' => $data['licensing']['annual_cost_usd'],
        ],
    ];

    $promptContent = "Please analyze the following Elasticsearch sizing details:\n\n".
        json_encode($sizingBreakdown, JSON_PRETTY_PRINT)."\n\n".
        'Evaluate the sizing/topology and offer recommendations.';

    echo 'Prompt length: '.strlen($promptContent)." chars\n";

    $aiConfig = AiConfigHelper::configure();
    echo 'Provider: '.(is_object($aiConfig['provider']) ? $aiConfig['provider']->value : (string) $aiConfig['provider'])."\n";
    echo 'Model: '.$aiConfig['model']."\n";

    $start = microtime(true);
    $response = (new SizingRegulator)->prompt($promptContent, provider: $aiConfig['provider'], model: $aiConfig['model'], timeout: 180);
    $elapsed = microtime(true) - $start;

    echo 'Elapsed: '.round($elapsed, 2)."s\n";
    echo 'Response length: '.strlen($response->text)." chars\n";
    echo 'Response (first 500): '.substr($response->text, 0, 500)."\n";
} catch (Throwable $e) {
    $elapsed = microtime(true) - ($start ?? microtime(true));
    echo 'Error after '.round($elapsed, 2).'s: '.$e->getMessage()."\n";
}
