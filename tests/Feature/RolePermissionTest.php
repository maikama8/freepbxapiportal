<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Permission;
use App\Models\Role;
use App\Services\RolePermissionService;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed permissions
        $this->artisan('db:seed', ['--class' => 'PermissionsSeeder']);
    }

    public function test_role_exists_check(): void
    {
        $this->assertTrue(Role::exists('admin'));
        $this->assertTrue(Role::exists('customer'));
        $this->assertTrue(Role::exists('operator'));
        $this->assertFalse(Role::exists('invalid_role'));
    }

    public function test_role_permissions(): void
    {
        $adminPermissions = Role::getPermissions('admin');
        $customerPermissions = Role::getPermissions('customer');
        $operatorPermissions = Role::getPermissions('operator');

        // Admin should have more permissions than others
        $this->assertGreaterThan(count($customerPermissions), count($adminPermissions));
        $this->assertGreaterThan(count($operatorPermissions), count($adminPermissions));

        // Check specific permissions
        $this->assertContains('admin.dashboard', $adminPermissions);
        $this->assertContains('calls.make', $customerPermissions);
        $this->assertContains('users.view', $operatorPermissions);
    }

    public function test_user_role_methods(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $operator = User::factory()->create(['role' => 'operator']);

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isCustomer());
        $this->assertFalse($admin->isOperator());

        $this->assertTrue($customer->isCustomer());
        $this->assertFalse($customer->isAdmin());
        $this->assertFalse($customer->isOperator());

        $this->assertTrue($operator->isOperator());
        $this->assertFalse($operator->isAdmin());
        $this->assertFalse($operator->isCustomer());
    }

    public function test_user_has_permission(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);

        // Admin should have admin permissions
        $this->assertTrue($admin->hasPermission('admin.dashboard'));
        $this->assertTrue($admin->hasPermission('users.view'));

        // Customer should have customer permissions but not admin permissions
        $this->assertTrue($customer->hasPermission('calls.make'));
        $this->assertTrue($customer->hasPermission('account.view_profile'));
        $this->assertFalse($customer->hasPermission('admin.dashboard'));
        $this->assertFalse($customer->hasPermission('users.view'));
    }

    public function test_role_permission_service_assign_role(): void
    {
        $service = new RolePermissionService();
        $user = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertEquals('customer', $user->role);

        $service->assignRole($user, 'operator', $admin);
        $user->refresh();

        $this->assertEquals('operator', $user->role);
    }

    public function test_role_permission_service_grant_permission(): void
    {
        $service = new RolePermissionService();
        $customer = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);

        // Customer shouldn't have admin permissions initially
        $this->assertFalse($customer->hasPermission('admin.dashboard'));

        // Grant admin permission to customer
        $service->grantPermission($customer, 'admin.dashboard', $admin);

        // Now customer should have the permission
        $this->assertTrue($customer->hasPermission('admin.dashboard'));
    }

    public function test_role_permission_service_revoke_permission(): void
    {
        $service = new RolePermissionService();
        $customer = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);

        // Customer should have call permission initially
        $this->assertTrue($customer->hasPermission('calls.make'));

        // Revoke call permission from customer
        $service->revokePermission($customer, 'calls.make', $admin);

        // Now customer should not have the permission
        $this->assertFalse($customer->hasPermission('calls.make'));
    }

    public function test_role_hierarchy(): void
    {
        $this->assertTrue(Role::hasHigherOrEqualLevel('admin', 'operator'));
        $this->assertTrue(Role::hasHigherOrEqualLevel('admin', 'customer'));
        $this->assertTrue(Role::hasHigherOrEqualLevel('operator', 'customer'));
        $this->assertTrue(Role::hasHigherOrEqualLevel('admin', 'admin'));

        $this->assertFalse(Role::hasHigherOrEqualLevel('customer', 'operator'));
        $this->assertFalse(Role::hasHigherOrEqualLevel('customer', 'admin'));
        $this->assertFalse(Role::hasHigherOrEqualLevel('operator', 'admin'));
    }

    public function test_can_manage_user(): void
    {
        $service = new RolePermissionService();
        $admin = User::factory()->create(['role' => 'admin']);
        $operator = User::factory()->create(['role' => 'operator']);
        $customer1 = User::factory()->create(['role' => 'customer']);
        $customer2 = User::factory()->create(['role' => 'customer']);

        // Admin can manage everyone
        $this->assertTrue($service->canManageUser($admin, $operator));
        $this->assertTrue($service->canManageUser($admin, $customer1));
        $this->assertTrue($service->canManageUser($admin, $admin));

        // Operator can manage customers but not admin
        $this->assertTrue($service->canManageUser($operator, $customer1));
        $this->assertFalse($service->canManageUser($operator, $admin));

        // Customer can only manage themselves
        $this->assertTrue($service->canManageUser($customer1, $customer1));
        $this->assertFalse($service->canManageUser($customer1, $customer2));
        $this->assertFalse($service->canManageUser($customer1, $operator));
    }

    public function test_user_effective_permissions(): void
    {
        $service = new RolePermissionService();
        $customer = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);

        // Get initial effective permissions
        $permissions = $service->getUserEffectivePermissions($customer);

        // Should have role-based permissions
        $this->assertArrayHasKey('calls.make', $permissions);
        $this->assertTrue($permissions['calls.make']['granted']);
        $this->assertEquals('role', $permissions['calls.make']['source']);

        // Grant additional permission
        $service->grantPermission($customer, 'admin.dashboard', $admin);

        // Get updated effective permissions
        $permissions = $service->getUserEffectivePermissions($customer);

        // Should now have the granted permission
        $this->assertArrayHasKey('admin.dashboard', $permissions);
        $this->assertTrue($permissions['admin.dashboard']['granted']);
        $this->assertEquals('user', $permissions['admin.dashboard']['source']);
    }
}