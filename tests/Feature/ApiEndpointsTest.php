<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\CallRecord;
use App\Models\PaymentTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class ApiEndpointsTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test users
        $this->customer = User::factory()->create([
            'role' => 'customer',
            'account_type' => 'prepaid',
            'balance' => 50.00,
            'status' => 'active'
        ]);
    }

    /** @test */
    public function it_can_access_api_documentation()
    {
        $response = $this->getJson('/api/docs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'api_version',
                    'base_url',
                    'authentication',
                    'rate_limits',
                    'endpoints'
                ]
            ]);
    }

    /** @test */
    public function it_can_get_rate_limit_information()
    {
        $response = $this->getJson('/api/docs/rate-limits');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'rate_limits',
                    'headers',
                    'error_response'
                ]
            ]);
    }

    /** @test */
    public function it_requires_authentication_for_customer_endpoints()
    {
        $response = $this->getJson('/api/customer/balance');

        $response->assertStatus(401);
    }

    /** @test */
    public function authenticated_customer_can_get_balance()
    {
        Sanctum::actingAs($this->customer, ['account:read']);

        $response = $this->getJson('/api/customer/balance');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'current_balance',
                    'available_balance',
                    'account_type',
                    'currency'
                ]
            ]);
    }

    /** @test */
    public function authenticated_customer_can_get_account_info()
    {
        Sanctum::actingAs($this->customer, ['account:read']);

        $response = $this->getJson('/api/customer/account');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user',
                    'balance',
                    'payment_stats'
                ]
            ]);
    }

    /** @test */
    public function authenticated_customer_can_get_call_history()
    {
        Sanctum::actingAs($this->customer, ['calls:read']);

        // Create some test call records
        CallRecord::factory()->count(3)->create([
            'user_id' => $this->customer->id
        ]);

        $response = $this->getJson('/api/customer/calls/history');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'calls',
                    'pagination'
                ]
            ]);
    }

    /** @test */
    public function authenticated_customer_can_get_payment_methods()
    {
        Sanctum::actingAs($this->customer, ['payments:write']);

        $response = $this->getJson('/api/customer/payments/methods');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data'
            ]);
    }

    /** @test */
    public function authenticated_customer_can_get_payment_history()
    {
        Sanctum::actingAs($this->customer, ['payments:write']);

        // Create some test payment transactions
        PaymentTransaction::factory()->count(2)->create([
            'user_id' => $this->customer->id
        ]);

        $response = $this->getJson('/api/customer/payments/history');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'payments',
                    'pagination'
                ]
            ]);
    }

    /** @test */
    public function it_validates_call_initiation_parameters()
    {
        Sanctum::actingAs($this->customer, ['calls:write']);

        $response = $this->postJson('/api/customer/calls/initiate', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['destination']);
    }

    /** @test */
    public function it_validates_payment_initiation_parameters()
    {
        Sanctum::actingAs($this->customer, ['payments:write']);

        $response = $this->postJson('/api/customer/payments/initiate', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount', 'currency', 'gateway', 'payment_method']);
    }

    /** @test */
    public function it_enforces_role_based_access_control()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin, ['*']);

        // Admin should not be able to access customer-specific endpoints
        $response = $this->getJson('/api/customer/balance');

        $response->assertStatus(403);
    }

    /** @test */
    public function it_applies_rate_limiting_to_auth_endpoints()
    {
        // Make multiple requests to exceed rate limit
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/auth/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword'
            ]);
        }

        // The 6th request should be rate limited
        $response->assertStatus(429)
            ->assertJsonStructure([
                'success',
                'message',
                'error' => [
                    'code',
                    'type',
                    'max_attempts',
                    'retry_after_seconds'
                ]
            ]);
    }

    /** @test */
    public function rate_limit_headers_are_present()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword'
        ]);

        $response->assertHeader('X-RateLimit-Limit')
            ->assertHeader('X-RateLimit-Remaining')
            ->assertHeader('X-RateLimit-Reset')
            ->assertHeader('X-RateLimit-Type');
    }
}