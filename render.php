<?php
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Login a user so the view renders successfully without Auth redirection
$user = App\Models\User::first();
Auth::login($user);

// Loop through all clients and scenarios and render their sizing dashboard
$clients = App\Models\Client::all();
$scenarios = App\Models\Scenario::all();

foreach ($clients as $c) {
    foreach ($scenarios as $s) {
        try {
            $request = Illuminate\Http\Request::create("/clients/{$c->id}/scenarios/{$s->id}", 'GET');
            $response = $kernel->handle($request);
            $content = $response->getContent();
            $lineCount = count(explode("\n", $content));
            
            $fileName = "rendered_c{$c->id}_s{$s->id}.html";
            file_put_contents($fileName, $content);
            echo "Client {$c->id} Scenario {$s->id} -> {$fileName} ({$lineCount} lines)\n";
        } catch (\Exception $e) {
            echo "Error rendering Client {$c->id} Scenario {$s->id}: " . $e->getMessage() . "\n";
        }
    }
}

// Find a newly generated diagram to inspect its rendered HTML
$diagram = App\Models\Diagram::whereIn('type', ['log_ingestion', 'node_specs', 'cluster_topology', 'node_clustering'])->first();
if ($diagram) {
    $request3 = Illuminate\Http\Request::create("/clients/{$diagram->client_id}/diagrams/{$diagram->id}", 'GET');
    $response3 = $kernel->handle($request3);
    file_put_contents('rendered_diagram5.html', $response3->getContent());
    echo "Done rendering sizing diagram {$diagram->id} -> rendered_diagram5.html\n";
} else {
    echo "No sizing diagrams found to render\n";
}
