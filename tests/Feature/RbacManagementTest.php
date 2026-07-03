<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacManagementTest extends TestCase
{
    use RefreshDatabase;

    private function seedPermissions(): void
    {
        $this->artisan('db:seed', ['--class' => 'PermissionSeeder'])->run();
    }

    private function createManager(): User
    {
        return User::factory()->create([
            'role' => 'manager',
            'password' => bcrypt('password'),
        ]);
    }

    private function createClient(): User
    {
        return User::factory()->create([
            'role' => 'client',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_manager_can_access_user_management_page(): void
    {
        $this->seedPermissions();
        $manager = $this->createManager();

        $response = $this->actingAs($manager)->get('/users');

        $response->assertStatus(200);
        $response->assertViewIs('users.index');
    }

    public function test_client_cannot_access_user_management_page(): void
    {
        $this->seedPermissions();
        $client = $this->createClient();

        $response = $this->actingAs($client)->get('/users');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_redirected_from_user_management(): void
    {
        $response = $this->get('/users');

        $response->assertRedirect('/login');
    }

    public function test_manager_can_create_user(): void
    {
        $this->seedPermissions();
        $manager = $this->createManager();

        $response = $this->actingAs($manager)->post('/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'client',
        ]);

        $response->assertRedirect('/users');
        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
    }

    public function test_manager_can_update_user(): void
    {
        $this->seedPermissions();
        $manager = $this->createManager();
        $user = User::factory()->create(['role' => 'client']);

        $response = $this->actingAs($manager)->put("/users/{$user->id}", [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'role' => 'manager',
        ]);

        $response->assertRedirect('/users');
        $user->refresh();
        $this->assertEquals('Updated Name', $user->name);
        $this->assertEquals('manager', $user->role);
    }

    public function test_manager_can_delete_user(): void
    {
        $this->seedPermissions();
        $manager = $this->createManager();
        $user = User::factory()->create(['role' => 'client']);

        $response = $this->actingAs($manager)->delete("/users/{$user->id}");

        $response->assertRedirect('/users');
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_manager_cannot_delete_self(): void
    {
        $this->seedPermissions();
        $manager = $this->createManager();

        $response = $this->actingAs($manager)->delete("/users/{$manager->id}");

        $response->assertRedirect('/users');
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $manager->id]);
    }

    public function test_manager_can_access_permission_matrix(): void
    {
        $this->seedPermissions();
        $manager = $this->createManager();

        $response = $this->actingAs($manager)->get('/roles/permissions');

        $response->assertStatus(200);
        $response->assertViewIs('roles.permissions');
    }

    public function test_client_cannot_access_permission_matrix(): void
    {
        $this->seedPermissions();
        $client = $this->createClient();

        $response = $this->actingAs($client)->get('/roles/permissions');

        $response->assertStatus(403);
    }

    public function test_manager_can_update_permission_matrix(): void
    {
        $this->seedPermissions();
        $manager = $this->createManager();

        $permission = Permission::where('key', 'dashboard')->first();

        $response = $this->actingAs($manager)->put('/roles/permissions', [
            'permissions' => [
                'dashboard' => ['client' => 1],
            ],
        ]);

        $response->assertRedirect('/roles/permissions');

        $rp = RolePermission::where('role', 'client')
            ->where('permission_id', $permission->id)
            ->first();

        $this->assertTrue($rp->is_allowed);
    }

    public function test_manager_can_revoke_permission_for_role(): void
    {
        $this->seedPermissions();
        $manager = $this->createManager();

        $permission = Permission::where('key', 'dashboard')->first();

        RolePermission::where('role', 'client')
            ->where('permission_id', $permission->id)
            ->update(['is_allowed' => true]);

        $response = $this->actingAs($manager)->put('/roles/permissions', [
            'permissions' => [],
        ]);

        $response->assertRedirect('/roles/permissions');

        $rp = RolePermission::where('role', 'client')
            ->where('permission_id', $permission->id)
            ->first();

        $this->assertFalse($rp->is_allowed);
    }

    public function test_permission_middleware_blocks_unauthorized_route(): void
    {
        $this->seedPermissions();
        $client = $this->createClient();

        $response = $this->actingAs($client)->get('/simulator');

        $response->assertStatus(403);
    }

    public function test_permission_middleware_allows_authorized_route(): void
    {
        $this->seedPermissions();
        $manager = $this->createManager();

        $response = $this->actingAs($manager)->get('/dashboard');

        $response->assertStatus(200);
    }

    public function test_user_has_permission_helper_works(): void
    {
        $this->seedPermissions();
        $manager = $this->createManager();
        $client = $this->createClient();

        $this->assertTrue($manager->hasPermission('user_management'));
        $this->assertFalse($client->hasPermission('user_management'));
        $this->assertTrue($client->hasPermission('dashboard'));
    }

    public function test_user_allowed_permissions_returns_collection(): void
    {
        $this->seedPermissions();
        $manager = $this->createManager();

        $allowed = $manager->allowedPermissions();

        $this->assertContains('dashboard', $allowed->toArray());
        $this->assertContains('user_management', $allowed->toArray());
    }

    public function test_manager_can_access_token_management(): void
    {
        $this->seedPermissions();
        $manager = $this->createManager();

        $response = $this->actingAs($manager)->get('/tokens');

        $response->assertStatus(200);
        $response->assertViewIs('tokens.index');
    }

    public function test_client_cannot_access_token_management(): void
    {
        $this->seedPermissions();
        $client = $this->createClient();

        $response = $this->actingAs($client)->get('/tokens');

        $response->assertStatus(403);
    }
}
