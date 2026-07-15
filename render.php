<?php
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Login a user so the view renders successfully without Auth redirection
$user = App\Models\User::first();
Auth::login($user);

$request = Illuminate\Http\Request::create('/clients/1/scenarios/1', 'GET');
$response = $kernel->handle($request);

file_put_contents('rendered_sizing.html', $response->getContent());
echo "Done rendering scenario 1\n";

$request2 = Illuminate\Http\Request::create('/clients/1/scenarios/2', 'GET');
$response2 = $kernel->handle($request2);

file_put_contents('rendered_sizing2.html', $response2->getContent());
echo "Done rendering scenario 2\n";

// Find first diagram ID to render it dynamically
$diagram = App\Models\Diagram::first();
if ($diagram) {
    $request3 = Illuminate\Http\Request::create("/clients/{$diagram->client_id}/diagrams/{$diagram->id}", 'GET');
    $response3 = $kernel->handle($request3);
    file_put_contents('rendered_diagram.html', $response3->getContent());
    echo "Done rendering diagram {$diagram->id}\n";
} else {
    echo "No diagrams found to render\n";
}
