<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\CallRecord;
use App\Models\PaymentTransaction;
use App\Models\CallRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class ApiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed permissions
        $this->artisan('db:seed', ['--class' => 'PermissionsSeeder']);
        
        // Create test users
        $this->admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active'
        ]);
        
        $this->customer = User::factory()->create([
            'role' => 'customer',
            'account_type' => 'prepaid',
            'balance' => 50.00,
            'status' => 'active'
        ]);
    }

    public function test_complete_api_authentication_flow()
    {
        // Test login
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $this->customer->email,
            'password' => 'password',
            'device_name' => 'Test Device'
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user',
                    'token',
                    'token_type'
                ]
            ]);

        $token = $loginResponse->json('data.token');

        // Test authenticated request
        $balanceResponse = $this->withHeaders([
            'Authorization' => "Bearer {$token}"
        ])->getJson('/api/customer/balance');

        $balanceResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'current_balance' => 50.00,
                    'account_type' => 'prepaid'
                ]
            ]);

        // Test logout
        $logoutResponse = $this->withHeaders([
            'Authorization' => "Bearer {$token}"
        ])->postJson('/api/auth/logout');

        $logoutResponse->assertStatus(200);

        // Test that token is invalidated
        $invalidResponse = $this->withHeaders([
            'Authorization' => "Bearer {$token}"
        ])->getJson('/api/customer/balance');

        $invalidResponse->assertStatus(401);
    }

    public function test_customer_call_history_api_integration()
    {
        Sanctum::actingAs($this->customer, ['calls:read']);

        // Create test call records
        $callRecords = CallRecord::factory()->count(5)->create([
            'user_id' => $this->customer->id,
            'status' => 'completed',
            'cost' => 0.50
        ]);

        $response = $this->getJson('/api/customer/calls/history');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'calls' => [
                        '*' => [
                            'id',
                            'call_id',
                            'destination',
                            'start_time',
                            'end_time',
                            'duration',
                            'cost',
                            'status'
                        ]
                    ],
                    'pagination'
                ]
            ]);

        $this->assertCount(5, $response->json('data.calls'));
    }

    public function test_customer_call_history_with_filters()
    {
        Sanctum::actingAs($this->customer, ['calls:read']);

        // Create call records with different statuses
        CallRecord::factory()->create([
            'user_id' => $this->customer->id,
            'status' => 'completed',
            'destination' => '+1234567890',
            'start_time' => now()->subDays(1)
        ]);

        CallRecord::factory()->create([
            'user_id' => $this->customer->id,
            'status' => 'failed',
            'destination' => '+1987654321',
            'start_time' => now()->subDays(2)
        ]);

        // Test status filter
        $response = $this->getJson('/api/customer/calls/history?status=completed');
        $response->assertStatus(200);
        $calls = $response->json('data.calls');
        $this->assertCount(1, $calls);
        $this->assertEquals('completed', $calls[0]['status']);

        // Test date range filter
        $response = $this->getJson('/api/customer/calls/history?date_from=' . now()->subDays(1)->format('Y-m-d'));
        $response->assertStatus(200);
        $calls = $response->json('data.calls');
        $this->assertCount(1, $calls);
    }

    public function test_payment_methods_api_integration()
    {
        Sanctum::actingAs($this->customer, ['payments:write']);

        $response = $this->getJson('/api/customer/payments/methods');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'paypal' => [
                        'name',
                        'type',
                        'currencies',
                        'min_amount'
                    ]
                ]
            ]);

        $paypalMethod = $response->json('data.paypal');
        $this->assertEquals('PayPal', $paypalMethod['name']);
        $this->assertEquals('paypal', $paypalMethod['type']);
        $this->assertContains('USD', $paypalMethod['currencies']);
    }

    public function test_payment_history_api_integration()
    {
        Sanctum::actingAs($this->customer, ['payments:write']);

        // Create test payment transactions
        PaymentTransaction::factory()->count(3)->create([
            'user_id' => $this->customer->id,
            'status' => 'completed'
        ]);

        PaymentTransaction::factory()->create([
            'user_id' => $this->customer->id,
            'status' => 'pending'
        ]);

        $response = $this->getJson('/api/customer/payments/history');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'payments' => [
                        '*' => [
                            'id',
                            'amount',
                            'currency',
                            'gateway',
                            'status',
                            'created_at'
                        ]
                    ],
                    'pagination'
                ]
            ]);

        $this->assertCount(4, $response->json('data.payments'));
    }

    public function test_admin_customer_management_api_integration()
    {
        Sanctum::actingAs($this->admin, ['*']);

        // Test customer listing
        $listResponse = $this->getJson('/api/admin/customers');
        $listResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'customers' => [
                        '*' => [
                            'id',
                            'name',
                            'email',
                            'role',
                            'account_type',
                            'balance',
                            'status'
                        ]
                    ]
                ]
            ]);

        // Test customer creation
        $createResponse = $this->postJson('/api/admin/customers', [
            'name' => 'New Customer',
            'email' => 'newcustomer@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'phone' => '+1234567890',
            'account_type' => 'prepaid',
            'balance' => 25.00
        ]);

        $createResponse->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'customer' => [
                        'id',
                        'name',
                        'email',
                        'account_type',
                        'balance'
                    ]
                ]
            ]);

        $customerId = $createResponse->json('data.customer.id');

        // Test customer update
        $updateResponse = $this->putJson("/api/admin/customers/{$customerId}", [
            'name' => 'Updated Customer Name',
            'balance' => 75.00
        ]);

        $updateResponse->assertStatus(200);

        // Verify update in database
        $updatedCustomer = User::find($customerId);
        $this->assertEquals('Updated Customer Name', $updatedCustomer->name);
        $this->assertEquals(75.00, $updatedCustomer->balance);
    }

    public function test_admin_rate_management_api_integration()
    {
        Sanctum::actingAs($this->admin, ['*']);

        // Test rate listing
        CallRate::factory()->count(3)->create(['is_active' => true]);

        $listResponse = $this->getJson('/api/admin/rates');
        $listResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'rates' => [
                        '*' => [
                            'id',
                            'destination_prefix',
                            'destination_name',
                            'rate_per_minute',
                            'minimum_duration',
                            'billing_increment'
                        ]
                    ]
                ]
            ]);

        // Test rate creation
        $createResponse = $this->postJson('/api/admin/rates', [
            'destination_prefix' => '33',
            'destination_name' => 'France',
            'rate_per_minute' => 0.12,
            'minimum_duration' => 60,
            'billing_increment' => 6,
            'effective_date' => now()->format('Y-m-d H:i:s')
        ]);

        $createResponse->assertStatus(201);

        $rateId = $createResponse->json('data.rate.id');

        // Test rate update
        $updateResponse = $this->putJson("/api/admin/rates/{$rateId}", [
            'rate_per_minute' => 0.15,
            'destination_name' => 'France Mobile'
        ]);

        $updateResponse->assertStatus(200);

        // Verify update in database
        $updatedRate = CallRate::find($rateId);
        $this->assertEquals(0.15, $updatedRate->rate_per_minute);
        $this->assertEquals('France Mobile', $updatedRate->destination_name);
    }

    public function test_role_based_access_control_integration()
    {
        // Test customer cannot access admin endpoints
        Sanctum::actingAs($this->customer, ['*']);

        $adminResponse = $this->getJson('/api/admin/customers');
        $adminResponse->assertStatus(403);

        // Test admin can access customer endpoints
        Sanctum::actingAs($this->admin, ['*']);

        $customerResponse = $this->getJson('/api/customer/balance');
        $customerResponse->assertStatus(403); // Admin role should not access customer-specific endpoints

        // Test admin can access admin endpoints
        $adminEndpointResponse = $this->getJson('/api/admin/customers');
        $adminEndpointResponse->assertStatus(200);
    }

    public function test_api_validation_integration()
    {
        Sanctum::actingAs($this->customer, ['calls:write']);

        // Test call initiation validation
        $invalidCallResponse = $this->postJson('/api/customer/calls/initiate', [
            'destination' => '', // Invalid empty destination
        ]);

        $invalidCallResponse->assertStatus(422)
            ->assertJsonValidationErrors(['destination']);

        // Test with invalid phone format
        $invalidPhoneResponse = $this->postJson('/api/customer/calls/initiate', [
            'destination' => 'invalid-phone',
        ]);

        $invalidPhoneResponse->assertStatus(422)
            ->assertJsonValidationErrors(['destination']);

        // Test with valid data
        $validCallResponse = $this->postJson('/api/customer/calls/initiate', [
            'destination' => '+1234567890',
            'caller_id' => '+0987654321'
        ]);

        // This might fail due to FreePBX integration, but validation should pass
        $this->assertContains($validCallResponse->status(), [200, 500]); // 500 if FreePBX not available
    }

    public function test_pagination_integration()
    {
        Sanctum::actingAs($this->customer, ['calls:read']);

        // Create many call records
        CallRecord::factory()->count(25)->create([
            'user_id' => $this->customer->id
        ]);

        // Test first page
        $firstPageResponse = $this->getJson('/api/customer/calls/history?per_page=10&page=1');
        $firstPageResponse->assertStatus(200);

        $firstPageData = $firstPageResponse->json('data');
        $this->assertCount(10, $firstPageData['calls']);
        $this->assertEquals(1, $firstPageData['pagination']['current_page']);
        $this->assertEquals(3, $firstPageData['pagination']['last_page']);

        // Test second page
        $secondPageResponse = $this->getJson('/api/customer/calls/history?per_page=10&page=2');
        $secondPageResponse->assertStatus(200);

        $secondPageData = $secondPageResponse->json('data');
        $this->assertCount(10, $secondPageData['calls']);
        $this->assertEquals(2, $secondPageData['pagination']['current_page']);
    }

    public function test_error_handling_integration()
    {
        Sanctum::actingAs($this->customer, ['calls:read']);

        // Test accessing non-existent resource
        $notFoundResponse = $this->getJson('/api/customer/calls/999999');
        $notFoundResponse->assertStatus(404);

        // Test accessing other user's data
        $otherUser = User::factory()->create(['role' => 'customer']);
        $otherUserCall = CallRecord::factory()->create(['user_id' => $otherUser->id]);

        $unauthorizedResponse = $this->getJson("/api/customer/calls/{$otherUserCall->id}");
        $unauthorizedResponse->assertStatus(404); // Should not reveal existence
    }
}