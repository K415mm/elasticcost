<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AuthAndRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a personal access client for tests that call createToken()
        Passport::client()->newQuery()->forceCreate([
            'name' => 'Test Personal Access Client',
            'secret' => 'test-secret',
            'provider' => 'users',
            'redirect_uris' => [],
            'grant_types' => ['personal_access'],
            'revoked' => false,
        ]);
    }

    /**
     * Test user registration via API.
     */
    public function test_user_can_register_via_api(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test Client',
            'email' => 'client@test.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email', 'role'],
                'access_token',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'client@test.com',
            'role' => 'client',
        ]);
    }

    /**
     * Test registration with a specific role.
     */
    public function test_user_can_register_with_manager_role(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test Manager',
            'email' => 'manager@test.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'manager',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'manager@test.com',
            'role' => 'manager',
        ]);
    }

    /**
     * Test registration rejects invalid role.
     */
    public function test_registration_rejects_invalid_role(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Hacker',
            'email' => 'hacker@test.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'admin',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test user login via API.
     */
    public function test_user_can_login_via_api(): void
    {
        $user = User::factory()->create([
            'email' => 'login@test.com',
            'password' => bcrypt('password123'),
            'role' => 'manager',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'login@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email', 'role'],
                'access_token',
            ]);
    }

    /**
     * Test login with wrong credentials.
     */
    public function test_login_fails_with_wrong_credentials(): void
    {
        User::factory()->create([
            'email' => 'wrong@test.com',
            'password' => bcrypt('correct-password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'wrong@test.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test authenticated user can get their profile.
     */
    public function test_authenticated_user_can_get_me(): void
    {
        $user = User::factory()->create(['role' => 'ceo']);
        Passport::actingAs($user, ['ceo']);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.role', 'ceo');
    }

    /**
     * Test unauthenticated access to me endpoint.
     */
    public function test_unauthenticated_access_to_me_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    /**
     * Test user can logout.
     */
    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user, ['client']);

        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Logged out successfully.');
    }

    /**
     * Test user can refresh token.
     */
    public function test_user_can_refresh_token(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        Passport::actingAs($user, ['manager']);

        $response = $this->postJson('/api/v1/auth/refresh');

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'access_token']);
    }

    /**
     * Test role middleware blocks wrong role.
     */
    public function test_client_cannot_access_ceo_routes(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        Passport::actingAs($user, ['client']);

        // Add a temporary CEO-only route for testing
        $this->app['router']->group(['middleware' => ['auth:api', 'role:ceo']], function ($router) {
            $router->get('/api/v1/test-ceo-only', fn () => response()->json(['ok' => true]));
        });

        $response = $this->getJson('/api/v1/test-ceo-only');

        $response->assertStatus(403);
    }

    /**
     * Test role middleware allows correct role.
     */
    public function test_ceo_can_access_ceo_routes(): void
    {
        $user = User::factory()->create(['role' => 'ceo']);
        Passport::actingAs($user, ['ceo']);

        $this->app['router']->group(['middleware' => ['auth:api', 'role:ceo']], function ($router) {
            $router->get('/api/v1/test-ceo-only', fn () => response()->json(['ok' => true]));
        });

        $response = $this->getJson('/api/v1/test-ceo-only');

        $response->assertStatus(200)
            ->assertJsonPath('ok', true);
    }

    /**
     * Test multi-role middleware (manager|ceo) allows both.
     */
    public function test_multi_role_allows_both_manager_and_ceo(): void
    {
        // Manager can access
        $manager = User::factory()->create(['role' => 'manager']);
        Passport::actingAs($manager, ['manager']);

        $this->app['router']->group(['middleware' => ['auth:api', 'role:manager|ceo']], function ($router) {
            $router->get('/api/v1/test-multi-role', fn () => response()->json(['ok' => true]));
        });

        $response = $this->getJson('/api/v1/test-multi-role');
        $response->assertStatus(200);

        // CEO can access
        $ceo = User::factory()->create(['role' => 'ceo']);
        Passport::actingAs($ceo, ['ceo']);

        $response = $this->getJson('/api/v1/test-multi-role');
        $response->assertStatus(200);
    }

    /**
     * Test multi-role middleware blocks non-listed role.
     */
    public function test_multi_role_blocks_client(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        Passport::actingAs($client, ['client']);

        $this->app['router']->group(['middleware' => ['auth:api', 'role:manager|ceo']], function ($router) {
            $router->get('/api/v1/test-multi-role-block', fn () => response()->json(['ok' => true]));
        });

        $response = $this->getJson('/api/v1/test-multi-role-block');
        $response->assertStatus(403);
    }

    /**
     * Test User model role helpers.
     */
    public function test_user_model_role_helpers(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $this->assertTrue($client->isClient());
        $this->assertFalse($client->isManager());

        $manager = User::factory()->create(['role' => 'manager']);
        $this->assertTrue($manager->isManager());
        $this->assertFalse($manager->isCeo());

        $salesManager = User::factory()->create(['role' => 'sales_manager']);
        $this->assertTrue($salesManager->isSalesManager());

        $partner = User::factory()->create(['role' => 'partner']);
        $this->assertTrue($partner->isPartner());

        $ceo = User::factory()->create(['role' => 'ceo']);
        $this->assertTrue($ceo->isCeo());
        $this->assertTrue($ceo->hasAnyRole(['ceo', 'manager']));
        $this->assertFalse($ceo->hasAnyRole(['client', 'partner']));
    }

    /**
     * Test Gates for role-based permissions.
     */
    public function test_gates_for_role_permissions(): void
    {
        $ceo = User::factory()->create(['role' => 'ceo']);
        $this->assertTrue($ceo->can('manage-clients'));
        $this->assertTrue($ceo->can('manage-settings'));

        $manager = User::factory()->create(['role' => 'manager']);
        $this->assertTrue($manager->can('manage-clients'));
        $this->assertFalse($manager->can('manage-settings'));

        $client = User::factory()->create(['role' => 'client']);
        $this->assertFalse($client->can('manage-clients'));
        $this->assertFalse($client->can('manage-settings'));

        $salesManager = User::factory()->create(['role' => 'sales_manager']);
        $this->assertTrue($salesManager->can('manage-partners'));
        $this->assertTrue($salesManager->can('view-financials'));

        $partner = User::factory()->create(['role' => 'partner']);
        $this->assertFalse($partner->can('manage-clients'));
        $this->assertFalse($partner->can('view-financials'));
    }
}
