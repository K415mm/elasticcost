<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated users can visit the folio pipeline page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/experiments/pipeline')
        ->assertOk()
        ->assertSee('semantic_cache')
        ->assertSee('context_compression');
});

test('authenticated users can visit the folio precognition page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/experiments/precognition')
        ->assertOk()
        ->assertSee('Precognition');
});

test('precognition validates a single field and returns 204', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post('/experiments/precognition', [
            'node_name' => 'semantic_cache',
            'enabled' => '1',
            'threshold' => '0.5',
        ], [
            'Precognition' => 'true',
            'Precognition-Validate-Only' => 'node_name',
            'Accept' => 'application/json',
        ]);

    $response->assertNoContent()
        ->assertHeader('Precognition-Success', 'true');
});

test('precognition returns field validation errors', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post('/experiments/precognition', [
            'node_name' => 'invalid-node',
            'enabled' => '1',
            'threshold' => '0.5',
        ], [
            'Precognition' => 'true',
            'Precognition-Validate-Only' => 'node_name',
            'Accept' => 'application/json',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('node_name');
});

test('precognition validates the full form on submission', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post('/experiments/precognition', [
            'node_name' => 'model_optimizer',
            'enabled' => '0',
            'threshold' => '0.75',
        ], [
            'Accept' => 'application/json',
        ]);

    $response->assertOk()
        ->assertJson(['message' => 'Node configuration accepted.']);
});
