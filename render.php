<?php
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/bootstrap/app.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

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
