<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Ai\Agents\RgSocEngineer;

$prompt = "list all the client then give me the list of asset for each client";
echo "Sending prompt: '$prompt'\n";

$agent = new RgSocEngineer();
$response = $agent->prompt($prompt);

echo "Response:\n";
echo $response->text . "\n";
