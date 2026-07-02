<?php

use App\Models\Client;
use App\Models\ClientScenarioMsspDetail;
use App\Models\Scenario;
use Database\Seeders\SizingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('client has clientScenarioMsspDetails relationship', function () {
    $this->seed(SizingSeeder::class);

    $client = Client::create([
        'name' => 'Test Client',
        'description' => 'Test Description',
    ]);

    $scenario = Scenario::first();

    $detail = ClientScenarioMsspDetail::create([
        'client_id' => $client->id,
        'scenario_id' => $scenario->id,
        'cloud_datacenter' => 'aws',
    ]);

    expect($client->clientScenarioMsspDetails)->toHaveCount(1);
    expect($client->clientScenarioMsspDetails->first()->id)->toBe($detail->id);
});
