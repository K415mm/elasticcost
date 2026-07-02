<?php

use App\Models\Client;
use App\Models\ClientScenarioMsspDetail;
use App\Models\Scenario;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('reset simulation route resets settings successfully', function () {
    $client = Client::create(['name' => 'Test Client']);
    $scenario = Scenario::create([
        'name' => 'Scenario 1',
        'description' => 'Test scenario',
        'workload_profile' => 'min',
        'retention_days' => 30,
        'hot_days' => 7,
        'warm_days' => 23,
        'cold_days' => 0,
        'frozen_days' => 0,
    ]);

    ClientScenarioMsspDetail::create([
        'client_id' => $client->id,
        'scenario_id' => $scenario->id,
        'soc_model' => 'tier2',
        'shift_model' => '24_7',
    ]);

    $response = $this->post(route('mssp.reset-simulation', [$client->id, $scenario->id]));

    $response->assertRedirect(route('mssp.show', [$client->id, $scenario->id, 'tab' => 'simulator']));
    $response->assertSessionHas('success');
});
