<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $customer;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed permissions
        $this->artisan('db:seed', ['--class' => 'PermissionsSeeder']);
        
        // Create test users
        $this->admin = \App\Models\User::factory()->admin()->create([
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        
        $this->customer = \App\Models\User::factory()->customer()->create([
            'email' => 'customer@test.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_api_login_with_valid_credentials()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@test.com',
            'password' => 'password',
            'device_name' => 'Test Device',
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'user' => ['id', 'name', 'email', 'role'],
                        'token',
                        'token_type',
                    ],
                ]);
    }

    public function test_api_login_with_invalid_credentials()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@test.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
                ->assertJson([
                    'success' => false,
                    'message' => 'Invalid credentials',
                ]);
    }

    public function test_admin_can_access_admin_routes()
    {
        $token = $this->admin->createToken('test')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/admin/test');

        $response->assertStatus(200)
                ->assertJson(['message' => 'Admin access granted']);
    }

    public function test_customer_cannot_access_admin_routes()
    {
        $token = $this->customer->createToken('test')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/admin/test');

        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'message' => 'Insufficient permissions',
                ]);
    }

    public function test_user_permissions_check()
    {
        $this->assertTrue($this->admin->hasPermission('admin.dashboard'));
        $this->assertTrue($this->admin->hasPermission('calls.make'));
        
        $this->assertFalse($this->customer->hasPermission('admin.dashboard'));
        $this->assertTrue($this->customer->hasPermission('calls.make'));
    }
}
