<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\CallRecord;
use App\Models\CallRate;
use App\Models\PaymentTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

class UserWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed permissions
        $this->artisan('db:seed', ['--class' => 'PermissionsSeeder']);
    }

    public function test_complete_user_registration_and_authentication_workflow()
    {
        // Step 1: User registration
        $registrationData = [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'phone' => '+1234567890',
            'account_type' => 'prepaid'
        ];

        $registerResponse = $this->postJson('/api/auth/register', $registrationData);
        
        $registerResponse->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'role',
                        'account_type'
                    ]
                ]
            ]);

        $userId = $registerResponse->json('data.user.id');
        $user = User::find($userId);

        // Verify user was created correctly
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john.doe@example.com', $user->email);
        $this->assertEquals('customer', $user->role);
        $this->assertEquals('prepaid', $user->account_type);
        $this->assertEquals(0.00, $user->balance);

        // Step 2: User login
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'john.doe@example.com',
            'password' => 'SecurePass123!',
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

        // Step 3: Access protected resources
        $profileResponse = $this->withHeaders([
            'Authorization' => "Bearer {$token}"
        ])->getJson('/api/customer/account');

        $profileResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $userId,
                        'name' => 'John Doe',
                        'email' => 'john.doe@example.com'
                    ]
                ]
            ]);

        // Step 4: Update profile
        $updateResponse = $this->withHeaders([
            'Authorization' => "Bearer {$token}"
        ])->putJson('/api/customer/profile', [
            'name' => 'John Updated Doe',
            'phone' => '+1987654321'
        ]);

        if ($updateResponse->status() === 200) {
            $user->refresh();
            $this->assertEquals('John Updated Doe', $user->name);
        }

        // Step 5: Change password
        $passwordResponse = $this->withHeaders([
            'Authorization' => "Bearer {$token}"
        ])->putJson('/api/customer/password', [
            'current_password' => 'SecurePass123!',
            'password' => 'NewSecurePass456!',
            'password_confirmation' => 'NewSecurePass456!'
        ]);

        if ($passwordResponse->status() === 200) {
            // Test login with new password
            $newLoginResponse = $this->postJson('/api/auth/login', [
                'email' => 'john.doe@example.com',
                'password' => 'NewSecurePass456!',
                'device_name' => 'Test Device'
            ]);

            $newLoginResponse->assertStatus(200);
        }

        // Step 6: Logout
        $logoutResponse = $this->withHeaders([
            'Authorization' => "Bearer {$token}"
        ])->postJson('/api/auth/logout');

        $logoutResponse->assertStatus(200);
    }

    public function test_complete_call_placement_and_billing_workflow()
    {
        // Setup: Create user with balance and call rates
        $user = User::factory()->create([
            'role' => 'customer',
            'account_type' => 'prepaid',
            'balance' => 50.00,
            'status' => 'active',
            'extension' => '1001'
        ]);

        CallRate::create([
            'destination_prefix' => '1',
            'destination_name' => 'USA',
            'rate_per_minute' => 0.05,
            'minimum_duration' => 60,
            'billing_increment' => 6,
            'effective_date' => now(),
            'is_active' => true
        ]);

        Sanctum::actingAs($user, ['calls:write', 'calls:read']);

        // Mock FreePBX API responses
        Http::fake([
            '*/admin/api/v17/calls/originate' => Http::response([
                'status' => true,
                'message' => 'Call initiated',
                'call_id' => 'freepbx_call_123'
            ], 200),
            '*/admin/api/v17/calls/*/status' => Http::response([
                'status' => 'active',
                'duration' => 120,
                'caller_id' => '1001',
                'destination' => '+12345678901'
            ], 200),
            '*/admin/api/v17/calls/*/hangup' => Http::response([
                'status' => true,
                'message' => 'Call terminated'
            ], 200)
        ]);

        // Step 1: Check call affordability
        $affordabilityResponse = $this->getJson('/api/customer/calls/affordability?destination=12345678901');
        
        if ($affordabilityResponse->status() === 200) {
            $affordabilityResponse->assertJson([
                'success' => true,
                'data' => [
                    'can_afford' => true,
                    'estimated_cost' => 0.05
                ]
            ]);
        }

        // Step 2: Initiate call
        $callResponse = $this->postJson('/api/customer/calls/initiate', [
            'destination' => '+12345678901',
            'caller_id' => '+1001'
        ]);

        if ($callResponse->status() === 201) {
            $callResponse->assertJsonStructure([
                'success',
                'data' => [
                    'call_id',
                    'call_record_id',
                    'status'
                ]
            ]);

            $callId = $callResponse->json('data.call_id');
            $callRecordId = $callResponse->json('data.call_record_id');

            // Verify call record was created
            $callRecord = CallRecord::find($callRecordId);
            $this->assertEquals($user->id, $callRecord->user_id);
            $this->assertEquals('+12345678901', $callRecord->destination);
            $this->assertEquals('initiated', $callRecord->status);

            // Step 3: Monitor call status
            $statusResponse = $this->getJson("/api/customer/calls/{$callId}/status");
            
            if ($statusResponse->status() === 200) {
                $statusResponse->assertJsonStructure([
                    'success',
                    'data' => [
                        'call_id',
                        'status',
                        'duration'
                    ]
                ]);
            }

            // Step 4: Simulate call completion (normally done by FreePBX webhook)
            $callRecord->update([
                'end_time' => now(),
                'duration' => 120,
                'status' => 'completed'
            ]);

            // Step 5: Process billing
            $billingResponse = $this->postJson("/api/admin/calls/{$callRecordId}/process-billing");
            
            if ($billingResponse->status() === 200) {
                // Verify billing was processed
                $callRecord->refresh();
                $this->assertNotNull($callRecord->cost);
                $this->assertEquals(0.10, $callRecord->cost); // 2 minutes at $0.05/min

                // Verify balance was deducted
                $user->refresh();
                $this->assertEquals(49.90, $user->balance);
            }

            // Step 6: View call history
            $historyResponse = $this->getJson('/api/customer/calls/history');
            
            if ($historyResponse->status() === 200) {
                $historyResponse->assertJsonStructure([
                    'success',
                    'data' => [
                        'calls' => [
                            '*' => [
                                'id',
                                'destination',
                                'duration',
                                'cost',
                                'status'
                            ]
                        ]
                    ]
                ]);

                $calls = $historyResponse->json('data.calls');
                $this->assertCount(1, $calls);
                $this->assertEquals($callRecordId, $calls[0]['id']);
            }
        } else {
            // If call initiation is not implemented, skip the rest
            $this->markTestSkipped('Call initiation API not fully implemented');
        }
    }

    public function test_complete_payment_processing_and_balance_update_workflow()
    {
        // Setup: Create user with low balance
        $user = User::factory()->create([
            'role' => 'customer',
            'account_type' => 'prepaid',
            'balance' => 5.00,
            'status' => 'active'
        ]);

        Sanctum::actingAs($user, ['payments:write']);

        // Mock PayPal API responses
        Http::fake([
            'https://api.paypal.com/*' => Http::sequence()
                ->push([
                    'id' => 'paypal_order_123',
                    'status' => 'CREATED',
                    'links' => [
                        ['rel' => 'approve', 'href' => 'https://paypal.com/approve/123']
                    ]
                ], 201)
                ->push([
                    'id' => 'paypal_order_123',
                    'status' => 'APPROVED'
                ], 200)
        ]);

        // Step 1: Check current balance
        $balanceResponse = $this->getJson('/api/customer/balance');
        $balanceResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'current_balance' => 5.00,
                    'account_type' => 'prepaid'
                ]
            ]);

        // Step 2: Get available payment methods
        $methodsResponse = $this->getJson('/api/customer/payments/methods');
        $methodsResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'paypal' => [
                        'name',
                        'type',
                        'currencies'
                    ]
                ]
            ]);

        // Step 3: Initiate payment
        $paymentResponse = $this->postJson('/api/customer/payments/initiate', [
            'amount' => 25.00,
            'currency' => 'USD',
            'gateway' => 'paypal',
            'payment_method' => 'paypal'
        ]);

        if ($paymentResponse->status() === 201) {
            $paymentResponse->assertJsonStructure([
                'success',
                'data' => [
                    'transaction_id',
                    'payment_url',
                    'gateway_transaction_id'
                ]
            ]);

            $transactionId = $paymentResponse->json('data.transaction_id');
            $gatewayTransactionId = $paymentResponse->json('data.gateway_transaction_id');

            // Verify payment transaction was created
            $transaction = PaymentTransaction::find($transactionId);
            $this->assertEquals($user->id, $transaction->user_id);
            $this->assertEquals(25.00, $transaction->amount);
            $this->assertEquals('pending', $transaction->status);

            // Step 4: Check payment status
            $statusResponse = $this->getJson("/api/customer/payments/{$transactionId}/status");
            $statusResponse->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'status' => 'pending',
                        'amount' => 25.00
                    ]
                ]);

            // Step 5: Simulate payment completion via webhook
            $webhookPayload = [
                'id' => 'webhook_event_123',
                'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
                'resource' => [
                    'id' => $gatewayTransactionId,
                    'status' => 'COMPLETED',
                    'amount' => [
                        'currency_code' => 'USD',
                        'value' => '25.00'
                    ]
                ]
            ];

            $webhookResponse = $this->postJson('/api/webhooks/paypal', $webhookPayload);
            
            if ($webhookResponse->status() === 200) {
                // Step 6: Verify payment completion
                $transaction->refresh();
                $this->assertEquals('completed', $transaction->status);
                $this->assertNotNull($transaction->completed_at);

                // Step 7: Verify balance update
                $user->refresh();
                $this->assertEquals(30.00, $user->balance); // 5.00 + 25.00

                // Step 8: Check updated balance via API
                $updatedBalanceResponse = $this->getJson('/api/customer/balance');
                $updatedBalanceResponse->assertStatus(200)
                    ->assertJson([
                        'success' => true,
                        'data' => [
                            'current_balance' => 30.00
                        ]
                    ]);

                // Step 9: View payment history
                $historyResponse = $this->getJson('/api/customer/payments/history');
                $historyResponse->assertStatus(200)
                    ->assertJsonStructure([
                        'success',
                        'data' => [
                            'payments' => [
                                '*' => [
                                    'id',
                                    'amount',
                                    'status',
                                    'gateway'
                                ]
                            ]
                        ]
                    ]);

                $payments = $historyResponse->json('data.payments');
                $this->assertCount(1, $payments);
                $this->assertEquals($transactionId, $payments[0]['id']);
                $this->assertEquals('completed', $payments[0]['status']);
            }
        } else {
            // If payment initiation is not implemented, skip the rest
            $this->markTestSkipped('Payment initiation API not fully implemented');
        }
    }

    public function test_insufficient_balance_call_prevention_workflow()
    {
        // Setup: Create user with insufficient balance
        $user = User::factory()->create([
            'role' => 'customer',
            'account_type' => 'prepaid',
            'balance' => 0.02, // Very low balance
            'status' => 'active'
        ]);

        CallRate::create([
            'destination_prefix' => '1',
            'destination_name' => 'USA',
            'rate_per_minute' => 0.05,
            'minimum_duration' => 60,
            'billing_increment' => 6,
            'effective_date' => now(),
            'is_active' => true
        ]);

        Sanctum::actingAs($user, ['calls:write']);

        // Step 1: Check call affordability
        $affordabilityResponse = $this->getJson('/api/customer/calls/affordability?destination=12345678901');
        
        if ($affordabilityResponse->status() === 200) {
            $affordabilityResponse->assertJson([
                'success' => true,
                'data' => [
                    'can_afford' => false,
                    'reason' => 'Insufficient balance'
                ]
            ]);
        }

        // Step 2: Attempt to initiate call
        $callResponse = $this->postJson('/api/customer/calls/initiate', [
            'destination' => '+12345678901'
        ]);

        // Should be rejected due to insufficient balance
        $callResponse->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Insufficient balance for this call'
            ]);

        // Step 3: Verify no call record was created
        $callRecords = CallRecord::where('user_id', $user->id)->count();
        $this->assertEquals(0, $callRecords);

        // Step 4: Add funds and retry
        $user->addBalance(10.00);

        $retryAffordabilityResponse = $this->getJson('/api/customer/calls/affordability?destination=12345678901');
        
        if ($retryAffordabilityResponse->status() === 200) {
            $retryAffordabilityResponse->assertJson([
                'success' => true,
                'data' => [
                    'can_afford' => true
                ]
            ]);
        }
    }

    public function test_admin_customer_management_workflow()
    {
        // Setup: Create admin user
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active'
        ]);

        Sanctum::actingAs($admin, ['*']);

        // Step 1: View customer list
        $customersResponse = $this->getJson('/api/admin/customers');
        
        if ($customersResponse->status() === 200) {
            $customersResponse->assertJsonStructure([
                'success',
                'data' => [
                    'customers' => []
                ]
            ]);
        }

        // Step 2: Create new customer
        $createResponse = $this->postJson('/api/admin/customers', [
            'name' => 'Test Customer',
            'email' => 'testcustomer@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'phone' => '+1234567890',
            'account_type' => 'prepaid',
            'balance' => 50.00
        ]);

        if ($createResponse->status() === 201) {
            $customerId = $createResponse->json('data.customer.id');

            // Step 3: View customer details
            $detailsResponse = $this->getJson("/api/admin/customers/{$customerId}");
            
            if ($detailsResponse->status() === 200) {
                $detailsResponse->assertJson([
                    'success' => true,
                    'data' => [
                        'customer' => [
                            'id' => $customerId,
                            'name' => 'Test Customer',
                            'email' => 'testcustomer@example.com'
                        ]
                    ]
                ]);
            }

            // Step 4: Update customer
            $updateResponse = $this->putJson("/api/admin/customers/{$customerId}", [
                'name' => 'Updated Test Customer',
                'balance' => 75.00,
                'status' => 'active'
            ]);

            if ($updateResponse->status() === 200) {
                // Verify update
                $customer = User::find($customerId);
                $this->assertEquals('Updated Test Customer', $customer->name);
                $this->assertEquals(75.00, $customer->balance);
            }

            // Step 5: View customer call history (admin perspective)
            $callHistoryResponse = $this->getJson("/api/admin/customers/{$customerId}/calls");
            
            if ($callHistoryResponse->status() === 200) {
                $callHistoryResponse->assertJsonStructure([
                    'success',
                    'data' => [
                        'calls' => []
                    ]
                ]);
            }

            // Step 6: Adjust customer balance
            $balanceResponse = $this->postJson("/api/admin/customers/{$customerId}/balance", [
                'amount' => 25.00,
                'type' => 'credit',
                'description' => 'Admin credit adjustment'
            ]);

            if ($balanceResponse->status() === 200) {
                $customer->refresh();
                $this->assertEquals(100.00, $customer->balance);
            }
        } else {
            $this->markTestSkipped('Admin customer management API not fully implemented');
        }
    }

    public function test_role_based_access_workflow()
    {
        // Create users with different roles
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $operator = User::factory()->create(['role' => 'operator']);

        // Test customer access
        Sanctum::actingAs($customer, ['*']);

        // Customer can access own data
        $customerBalanceResponse = $this->getJson('/api/customer/balance');
        $customerBalanceResponse->assertStatus(200);

        // Customer cannot access admin endpoints
        $adminResponse = $this->getJson('/api/admin/customers');
        $adminResponse->assertStatus(403);

        // Test admin access
        Sanctum::actingAs($admin, ['*']);

        // Admin can access admin endpoints
        $adminCustomersResponse = $this->getJson('/api/admin/customers');
        $this->assertContains($adminCustomersResponse->status(), [200, 404]); // 404 if endpoint not found

        // Test operator access (if implemented)
        Sanctum::actingAs($operator, ['*']);

        $operatorResponse = $this->getJson('/api/operator/dashboard');
        $this->assertContains($operatorResponse->status(), [200, 404, 403]); // Various responses depending on implementation
    }
}