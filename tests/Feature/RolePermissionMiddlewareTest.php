<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

class RolePermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed permissions
        $this->artisan('db:seed', ['--class' => 'PermissionsSeeder']);
    }

    public function test_role_middleware_allows_correct_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/test');
        $response->assertStatus(200);
        $response->assertJson(['message' => 'Admin access granted']);
    }

    public function test_role_middleware_denies_incorrect_role(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($customer);

        $response = $this->getJson('/api/admin/test');
        $response->assertStatus(403);
    }

    public function test_permission_middleware_allows_correct_permission(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin-dashboard-test');
        $response->assertStatus(200);
        $response->assertJson(['message' => 'Admin dashboard access granted']);
    }

    public function test_permission_middleware_denies_incorrect_permission(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($customer);

        $response = $this->getJson('/api/admin-dashboard-test');
        $response->assertStatus(403);
    }

    public function test_role_management_api_requires_admin_permission(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($customer);

        // Try to access role management endpoint
        $response = $this->getJson('/api/admin/roles');
        $response->assertStatus(403);

        // Admin should be able to access
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/roles');
        $response->assertStatus(200);
    }

    public function test_user_permission_management_api(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        
        Sanctum::actingAs($admin);

        // Get user permissions
        $response = $this->getJson("/api/admin/users/{$customer->id}/permissions");
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'user',
                'permission_summary',
                'assignable_permissions',
                'assignable_roles',
            ],
        ]);

        // Assign role
        $response = $this->postJson("/api/admin/users/{$customer->id}/permissions/role", [
            'role' => 'operator',
        ]);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Verify role was changed
        $customer->refresh();
        $this->assertEquals('operator', $customer->role);
    }

    public function test_role_or_permission_middleware(): void
    {
        // Create test route for role_or_permission middleware - only admin role allowed
        \Route::middleware(['auth:sanctum', 'role_or_permission:admin|'])
            ->get('/test-role-or-permission', function () {
                return response()->json(['message' => 'Access granted']);
            });

        // Admin should have access (by role)
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);
        $response = $this->getJson('/test-role-or-permission');
        $response->assertStatus(200);

        // Operator should not have access (no role match)
        $operator = User::factory()->create(['role' => 'operator']);
        Sanctum::actingAs($operator);
        $response = $this->getJson('/test-role-or-permission');
        $response->assertStatus(403);

        // Customer should not have access (no role match)
        $customer = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($customer);
        $response = $this->getJson('/test-role-or-permission');
        $response->assertStatus(403);
    }

    public function test_inactive_user_denied_access(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'inactive',
        ]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/test');
        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Account is not active',
        ]);
    }

    public function test_locked_user_denied_access(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'locked',
        ]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/test');
        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Account is not active',
        ]);
    }
}