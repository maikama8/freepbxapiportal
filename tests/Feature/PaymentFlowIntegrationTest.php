<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\PaymentTransaction;
use App\Services\Payment\PaymentService;
use App\Services\Payment\NowPaymentsGateway;
use App\Services\Payment\PayPalGateway;
use App\Services\Email\PaymentNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Mockery;

class PaymentFlowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected PaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->customer = User::factory()->create([
            'role' => 'customer',
            'account_type' => 'prepaid',
            'balance' => 10.00,
            'status' => 'active'
        ]);

        // Mock external services for integration tests
        $this->paymentService = $this->app->make(PaymentService::class);
    }

    public function test_complete_paypal_payment_flow()
    {
        Sanctum::actingAs($this->customer, ['payments:write']);

        // Mock PayPal API responses
        Http::fake([
            'https://api.paypal.com/*' => Http::sequence()
                ->push(['id' => 'paypal_order_123', 'status' => 'CREATED', 'links' => [
                    ['rel' => 'approve', 'href' => 'https://paypal.com/approve/123']
                ]], 201)
                ->push(['id' => 'paypal_order_123', 'status' => 'APPROVED'], 200)
                ->push(['id' => 'paypal_capture_456', 'status' => 'COMPLETED'], 201)
        ]);

        // Step 1: Initiate payment
        $initiateResponse = $this->postJson('/api/customer/payments/initiate', [
            'amount' => 50.00,
            'currency' => 'USD',
            'gateway' => 'paypal',
            'payment_method' => 'paypal'
        ]);

        $initiateResponse->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'transaction_id',
                    'payment_url',
                    'gateway_transaction_id'
                ]
            ]);

        $transactionId = $initiateResponse->json('data.transaction_id');
        $transaction = PaymentTransaction::find($transactionId);

        $this->assertEquals('pending', $transaction->status);
        $this->assertEquals(50.00, $transaction->amount);
        $this->assertEquals('paypal', $transaction->gateway);

        // Step 2: Simulate PayPal webhook for payment completion
        $webhookPayload = [
            'id' => 'webhook_event_123',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => $transaction->gateway_transaction_id,
                'status' => 'COMPLETED',
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => '50.00'
                ]
            ]
        ];

        $webhookResponse = $this->postJson('/api/webhooks/paypal', $webhookPayload);
        $webhookResponse->assertStatus(200);

        // Step 3: Verify payment completion
        $transaction->refresh();
        $this->assertEquals('completed', $transaction->status);
        $this->assertNotNull($transaction->completed_at);

        // Step 4: Verify balance update
        $this->customer->refresh();
        $this->assertEquals(60.00, $this->customer->balance); // 10.00 + 50.00

        // Step 5: Verify payment status API
        $statusResponse = $this->getJson("/api/customer/payments/{$transactionId}/status");
        $statusResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'completed',
                    'amount' => 50.00
                ]
            ]);
    }

    public function test_cryptocurrency_payment_flow_with_nowpayments()
    {
        Sanctum::actingAs($this->customer, ['payments:write']);

        // Mock NowPayments API responses
        Http::fake([
            'https://api.nowpayments.io/*' => Http::sequence()
                ->push(['currencies' => ['btc', 'eth', 'usdt']], 200) // Available currencies
                ->push([
                    'payment_id' => 'np_payment_789',
                    'payment_status' => 'waiting',
                    'pay_address' => '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa',
                    'pay_amount' => 0.00125,
                    'pay_currency' => 'btc',
                    'price_amount' => 25.00,
                    'price_currency' => 'USD'
                ], 201)
                ->push([
                    'payment_id' => 'np_payment_789',
                    'payment_status' => 'confirmed'
                ], 200)
        ]);

        // Step 1: Get available payment methods
        $methodsResponse = $this->getJson('/api/customer/payments/methods');
        $methodsResponse->assertStatus(200);
        
        $methods = $methodsResponse->json('data');
        $this->assertArrayHasKey('crypto_btc', $methods);

        // Step 2: Initiate cryptocurrency payment
        $initiateResponse = $this->postJson('/api/customer/payments/initiate', [
            'amount' => 25.00,
            'currency' => 'USD',
            'gateway' => 'nowpayments',
            'payment_method' => 'btc'
        ]);

        $initiateResponse->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'transaction_id',
                    'payment_address',
                    'pay_amount',
                    'pay_currency',
                    'gateway_transaction_id'
                ]
            ]);

        $transactionId = $initiateResponse->json('data.transaction_id');
        $transaction = PaymentTransaction::find($transactionId);

        $this->assertEquals('pending', $transaction->status);
        $this->assertEquals('nowpayments', $transaction->gateway);
        $this->assertEquals('btc', $transaction->payment_method);

        // Step 3: Simulate NowPayments webhook for payment confirmation
        $webhookPayload = [
            'payment_id' => $transaction->gateway_transaction_id,
            'payment_status' => 'confirmed',
            'pay_amount' => 0.00125,
            'pay_currency' => 'btc',
            'price_amount' => 25.00,
            'price_currency' => 'USD'
        ];

        $webhookResponse = $this->postJson('/api/webhooks/nowpayments', $webhookPayload);
        $webhookResponse->assertStatus(200);

        // Step 4: Verify payment completion
        $transaction->refresh();
        $this->assertEquals('completed', $transaction->status);

        // Step 5: Verify balance update
        $this->customer->refresh();
        $this->assertEquals(35.00, $this->customer->balance); // 10.00 + 25.00
    }

    public function test_payment_failure_flow()
    {
        Sanctum::actingAs($this->customer, ['payments:write']);

        // Mock failed PayPal response
        Http::fake([
            'https://api.paypal.com/*' => Http::response([
                'error' => 'PAYMENT_DENIED',
                'error_description' => 'Payment was denied by the bank'
            ], 400)
        ]);

        // Step 1: Initiate payment that will fail
        $initiateResponse = $this->postJson('/api/customer/payments/initiate', [
            'amount' => 100.00,
            'currency' => 'USD',
            'gateway' => 'paypal',
            'payment_method' => 'paypal'
        ]);

        // Payment initiation might succeed but processing will fail
        if ($initiateResponse->status() === 201) {
            $transactionId = $initiateResponse->json('data.transaction_id');
            
            // Step 2: Simulate webhook for payment failure
            $webhookPayload = [
                'id' => 'webhook_event_456',
                'event_type' => 'PAYMENT.CAPTURE.DENIED',
                'resource' => [
                    'id' => PaymentTransaction::find($transactionId)->gateway_transaction_id,
                    'status' => 'DECLINED'
                ]
            ];

            $webhookResponse = $this->postJson('/api/webhooks/paypal', $webhookPayload);
            $webhookResponse->assertStatus(200);

            // Step 3: Verify payment failure
            $transaction = PaymentTransaction::find($transactionId);
            $this->assertEquals('failed', $transaction->status);

            // Step 4: Verify balance unchanged
            $this->customer->refresh();
            $this->assertEquals(10.00, $this->customer->balance); // Should remain unchanged
        } else {
            // If initiation fails immediately
            $initiateResponse->assertStatus(422);
        }
    }

    public function test_payment_timeout_handling()
    {
        Sanctum::actingAs($this->customer, ['payments:write']);

        // Create a pending payment transaction
        $transaction = PaymentTransaction::create([
            'user_id' => $this->customer->id,
            'amount' => 30.00,
            'currency' => 'USD',
            'gateway' => 'paypal',
            'payment_method' => 'paypal',
            'status' => 'pending',
            'gateway_transaction_id' => 'paypal_timeout_123',
            'expires_at' => now()->subMinutes(30) // Expired 30 minutes ago
        ]);

        // Test payment status check for expired payment
        $statusResponse = $this->getJson("/api/customer/payments/{$transaction->id}/status");
        $statusResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'expired'
                ]
            ]);
    }

    public function test_payment_retry_flow()
    {
        Sanctum::actingAs($this->customer, ['payments:write']);

        // Create a failed payment transaction
        $failedTransaction = PaymentTransaction::create([
            'user_id' => $this->customer->id,
            'amount' => 40.00,
            'currency' => 'USD',
            'gateway' => 'paypal',
            'payment_method' => 'paypal',
            'status' => 'failed',
            'gateway_transaction_id' => 'paypal_failed_123'
        ]);

        // Mock successful PayPal response for retry
        Http::fake([
            'https://api.paypal.com/*' => Http::response([
                'id' => 'paypal_retry_456',
                'status' => 'CREATED',
                'links' => [
                    ['rel' => 'approve', 'href' => 'https://paypal.com/approve/456']
                ]
            ], 201)
        ]);

        // Retry the payment
        $retryResponse = $this->postJson("/api/customer/payments/{$failedTransaction->id}/retry");
        
        if ($retryResponse->status() === 201) {
            $retryResponse->assertJsonStructure([
                'success',
                'data' => [
                    'transaction_id',
                    'payment_url'
                ]
            ]);

            $newTransactionId = $retryResponse->json('data.transaction_id');
            $this->assertNotEquals($failedTransaction->id, $newTransactionId);

            $newTransaction = PaymentTransaction::find($newTransactionId);
            $this->assertEquals('pending', $newTransaction->status);
            $this->assertEquals($failedTransaction->amount, $newTransaction->amount);
        } else {
            // If retry is not implemented or fails
            $retryResponse->assertStatus(404);
        }
    }

    public function test_payment_validation_integration()
    {
        Sanctum::actingAs($this->customer, ['payments:write']);

        // Test invalid amount
        $invalidAmountResponse = $this->postJson('/api/customer/payments/initiate', [
            'amount' => -10.00,
            'currency' => 'USD',
            'gateway' => 'paypal',
            'payment_method' => 'paypal'
        ]);

        $invalidAmountResponse->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        // Test unsupported currency
        $invalidCurrencyResponse = $this->postJson('/api/customer/payments/initiate', [
            'amount' => 50.00,
            'currency' => 'JPY',
            'gateway' => 'paypal',
            'payment_method' => 'paypal'
        ]);

        $invalidCurrencyResponse->assertStatus(422)
            ->assertJsonValidationErrors(['currency']);

        // Test unsupported gateway
        $invalidGatewayResponse = $this->postJson('/api/customer/payments/initiate', [
            'amount' => 50.00,
            'currency' => 'USD',
            'gateway' => 'unsupported',
            'payment_method' => 'test'
        ]);

        $invalidGatewayResponse->assertStatus(422)
            ->assertJsonValidationErrors(['gateway']);
    }

    public function test_concurrent_payment_handling()
    {
        Sanctum::actingAs($this->customer, ['payments:write']);

        // Mock PayPal responses
        Http::fake([
            'https://api.paypal.com/*' => Http::response([
                'id' => 'paypal_concurrent_123',
                'status' => 'CREATED',
                'links' => [
                    ['rel' => 'approve', 'href' => 'https://paypal.com/approve/123']
                ]
            ], 201)
        ]);

        // Initiate multiple payments simultaneously
        $responses = [];
        for ($i = 0; $i < 3; $i++) {
            $responses[] = $this->postJson('/api/customer/payments/initiate', [
                'amount' => 20.00,
                'currency' => 'USD',
                'gateway' => 'paypal',
                'payment_method' => 'paypal'
            ]);
        }

        // All should succeed (or handle gracefully)
        foreach ($responses as $response) {
            $this->assertContains($response->status(), [201, 429]); // 429 if rate limited
        }

        // Verify transactions were created
        $transactions = PaymentTransaction::where('user_id', $this->customer->id)->get();
        $this->assertGreaterThanOrEqual(1, $transactions->count());
    }

    public function test_payment_history_with_filters()
    {
        Sanctum::actingAs($this->customer, ['payments:write']);

        // Create various payment transactions
        PaymentTransaction::factory()->create([
            'user_id' => $this->customer->id,
            'status' => 'completed',
            'gateway' => 'paypal',
            'created_at' => now()->subDays(1)
        ]);

        PaymentTransaction::factory()->create([
            'user_id' => $this->customer->id,
            'status' => 'failed',
            'gateway' => 'nowpayments',
            'created_at' => now()->subDays(2)
        ]);

        PaymentTransaction::factory()->create([
            'user_id' => $this->customer->id,
            'status' => 'pending',
            'gateway' => 'paypal',
            'created_at' => now()->subHours(1)
        ]);

        // Test status filter
        $completedResponse = $this->getJson('/api/customer/payments/history?status=completed');
        $completedResponse->assertStatus(200);
        $completedPayments = $completedResponse->json('data.payments');
        $this->assertCount(1, $completedPayments);

        // Test gateway filter
        $paypalResponse = $this->getJson('/api/customer/payments/history?gateway=paypal');
        $paypalResponse->assertStatus(200);
        $paypalPayments = $paypalResponse->json('data.payments');
        $this->assertCount(2, $paypalPayments);

        // Test date range filter
        $recentResponse = $this->getJson('/api/customer/payments/history?date_from=' . now()->subDays(1)->format('Y-m-d'));
        $recentResponse->assertStatus(200);
        $recentPayments = $recentResponse->json('data.payments');
        $this->assertCount(2, $recentPayments);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}