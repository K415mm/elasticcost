<?php

use App\Http\Controllers\MsspCostingController;
use App\Http\Controllers\SizingDashboardController;
use App\Models\Client;
use App\Models\Scenario;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Simulate the ollamaPing request
echo "=== Testing ollamaPing ===\n";
try {
    $controller = app(MsspCostingController::class);
    $request = Request::create('/api/ollama-ping', 'GET');
    $response = $controller->ollamaPing($request);
    echo 'Status: '.$response->getStatusCode()."\n";
    echo 'Body: '.$response->getContent()."\n";
} catch (Throwable $e) {
    echo 'Error: '.$e->getMessage()."\n";
    echo 'Trace: '.$e->getTraceAsString()."\n";
}

// Simulate the analyzeSizingAi request
echo "\n=== Testing analyzeSizingAi ===\n";
try {
    $client = Client::find(2);
    $scenario = Scenario::find(2);
    if (! $client || ! $scenario) {
        echo "Client or Scenario not found\n";
    } else {
        echo "Client: {$client->name}, Scenario: {$scenario->name}\n";
        $controller = app(SizingDashboardController::class);
        $response = $controller->analyzeSizingAi($client, $scenario);
        echo 'Status: '.$response->getStatusCode()."\n";
        $body = $response->getContent();
        echo 'Body (first 2000 chars): '.substr($body, 0, 2000)."\n";
    }
} catch (Throwable $e) {
    echo 'Error: '.$e->getMessage()."\n";
    echo 'File: '.$e->getFile().':'.$e->getLine()."\n";
    echo 'Trace: '.substr($e->getTraceAsString(), 0, 2000)."\n";
}
