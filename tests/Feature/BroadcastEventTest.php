<?php

use App\Events\PermissionChanged;
use App\Events\TokenRevoked;
use App\Events\UserUpdated;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'PermissionSeeder'])->run();
});

it('broadcasts UserUpdated event on user creation', function () {
    Event::fake([UserUpdated::class]);

    $manager = User::factory()->create(['role' => 'manager']);

    $this->actingAs($manager)
        ->post(route('users.store'), [
            'name' => 'New User',
            'email' => 'newuser@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'client',
        ]);

    Event::assertDispatched(UserUpdated::class);
});

it('broadcasts UserUpdated event on user update', function () {
    Event::fake([UserUpdated::class]);

    $manager = User::factory()->create(['role' => 'manager']);
    $targetUser = User::factory()->create(['role' => 'client']);

    $this->actingAs($manager)
        ->put(route('users.update', $targetUser), [
            'name' => $targetUser->name,
            'email' => $targetUser->email,
            'role' => 'manager',
        ]);

    Event::assertDispatched(UserUpdated::class);
});

it('broadcasts PermissionChanged event on permission matrix update', function () {
    Event::fake([PermissionChanged::class]);

    $manager = User::factory()->create(['role' => 'manager']);

    $this->actingAs($manager)
        ->put(route('roles.permissions.update'), [
            'permissions' => [],
        ]);

    Event::assertDispatched(PermissionChanged::class);
});

it('broadcasts TokenRevoked event on token revocation', function () {
    Event::fake([TokenRevoked::class]);

    $manager = User::factory()->create(['role' => 'manager']);
    $targetUser = User::factory()->create(['role' => 'client']);

    $this->actingAs($manager)
        ->delete(route('tokens.revoke-all', $targetUser));

    Event::assertDispatched(TokenRevoked::class);
});

it('UserUpdated event broadcasts on admin.users private channel', function () {
    $user = User::factory()->create(['role' => 'manager']);

    $event = new UserUpdated($user);

    expect($event->broadcastOn())->each->toBeInstanceOf(PrivateChannel::class);
    expect($event->broadcastOn()[0]->name)->toBe('private-admin.users');
});

it('PermissionChanged event broadcasts on admin.permissions private channel', function () {
    $event = new PermissionChanged('manager');

    expect($event->broadcastOn()[0]->name)->toBe('private-admin.permissions');
});

it('TokenRevoked event broadcasts on admin.tokens private channel', function () {
    $user = User::factory()->create(['role' => 'client']);

    $event = new TokenRevoked($user);

    expect($event->broadcastOn()[0]->name)->toBe('private-admin.tokens');
});
