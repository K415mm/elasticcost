<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'PermissionSeeder'])->run();
});

it('returns validation errors via precognition for invalid user data', function () {
    $manager = User::factory()->create(['role' => 'manager']);

    $this->actingAs($manager)
        ->withHeaders(['Precognition' => 'true', 'Accept' => 'application/json'])
        ->post(route('users.store'), [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
            'role' => 'invalid_role',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password', 'role']);
});

it('returns 204 for valid user data via precognition', function () {
    $manager = User::factory()->create(['role' => 'manager']);

    $this->actingAs($manager)
        ->withHeaders(['Precognition' => 'true'])
        ->post(route('users.store'), [
            'name' => 'Valid User',
            'email' => 'valid@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'client',
        ])
        ->assertStatus(204);
});

it('returns validation errors via precognition for update with duplicate email', function () {
    $manager = User::factory()->create(['role' => 'manager']);
    $existingUser = User::factory()->create(['email' => 'existing@example.com']);
    $targetUser = User::factory()->create(['email' => 'target@example.com']);

    $this->actingAs($manager)
        ->withHeaders(['Precognition' => 'true', 'Accept' => 'application/json'])
        ->put(route('users.update', $targetUser), [
            'name' => $targetUser->name,
            'email' => 'existing@example.com',
            'role' => 'client',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('still works without precognition header for normal form submission', function () {
    $manager = User::factory()->create(['role' => 'manager']);

    $this->actingAs($manager)
        ->post(route('users.store'), [
            'name' => 'Normal User',
            'email' => 'normal@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'client',
        ])
        ->assertRedirect(route('users.index'));

    $this->assertDatabaseHas('users', ['email' => 'normal@example.com']);
});
