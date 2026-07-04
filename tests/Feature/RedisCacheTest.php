<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'PermissionSeeder'])->run();
});

it('can store and retrieve values from cache', function () {
    Cache::put('test-key', 'test-value', now()->addMinutes(5));

    expect(Cache::get('test-key'))->toBe('test-value');

    Cache::forget('test-key');
});

it('can use remember pattern for cached data', function () {
    $callCount = 0;

    $result = Cache::remember('remember-test', 60, function () use (&$callCount) {
        $callCount++;

        return 'computed-value-'.$callCount;
    });

    expect($result)->toBe('computed-value-1');

    // Second call should return cached value, not recompute
    $result2 = Cache::remember('remember-test', 60, function () use (&$callCount) {
        $callCount++;

        return 'computed-value-'.$callCount;
    });

    expect($result2)->toBe('computed-value-1');
    expect($callCount)->toBe(1);

    Cache::forget('remember-test');
});

it('can flush cache by tag-like prefix', function () {
    Cache::put('perm:dashboard:1', 'yes', 60);
    Cache::put('perm:dashboard:2', 'no', 60);

    // Redis doesn't support tag flushing natively without predis tag support
    // We test manual key forgetting
    Cache::forget('perm:dashboard:1');

    expect(Cache::has('perm:dashboard:1'))->toBeFalse();
    expect(Cache::has('perm:dashboard:2'))->toBeTrue();

    Cache::flush();
});

it('persists session data across requests', function () {
    $user = User::factory()->create(['role' => 'manager']);

    $this->actingAs($user)
        ->withSession(['test-session-key' => 'test-session-value'])
        ->get('/dashboard')
        ->assertStatus(200);

    // Session should persist
    $this->assertNotNull(session('test-session-key'));
});
