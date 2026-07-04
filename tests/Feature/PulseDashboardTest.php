<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'PermissionSeeder'])->run();
});

it('allows manager to access pulse dashboard', function () {
    $manager = User::factory()->create(['role' => 'manager']);

    $this->actingAs($manager)
        ->get('/pulse')
        ->assertStatus(200);
});

it('allows ceo to access pulse dashboard', function () {
    $ceo = User::factory()->create(['role' => 'ceo']);

    $this->actingAs($ceo)
        ->get('/pulse')
        ->assertStatus(200);
});

it('denies client access to pulse dashboard', function () {
    $client = User::factory()->create(['role' => 'client']);

    $this->actingAs($client)
        ->get('/pulse')
        ->assertStatus(403);
});

it('redirects unauthenticated users from pulse dashboard', function () {
    $this->get('/pulse')
        ->assertRedirect('/login');
});
