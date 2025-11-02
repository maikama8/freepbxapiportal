<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\PaymentTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Run seeders to set up roles and permissions
        $this->artisan('db:seed', ['--class' => 'PermissionsSeeder']);
    }

    public function test_can_get_payment_methods(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'status' => 'active'
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/payments/methods');

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
    }

    public function test_can_get_payment_history(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'status' => 'active'
        ]);

        // Create a test payment transaction
        PaymentTransaction::create([
            'user_id' => $user->id,
            'amount' => 50.00,
            'currency' => 'USD',
            'gateway' => 'paypal',
            'payment_method' => 'paypal',
            'status' => 'completed',
            'gateway_transaction_id' => 'test_123',
            'completed_at' => now()
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/payments/history');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'data' => [
                            '*' => [
                                'id',
                                'amount',
                                'currency',
                                'gateway',
                                'status',
                                'created_at'
                            ]
                        ]
                    ]
                ]);
    }

    public function test_can_get_payment_status(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'status' => 'active'
        ]);

        $transaction = PaymentTransaction::create([
            'user_id' => $user->id,
            'amount' => 25.00,
            'currency' => 'USD',
            'gateway' => 'paypal',
            'payment_method' => 'paypal',
            'status' => 'pending',
            'gateway_transaction_id' => 'test_456'
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/payments/{$transaction->id}/status");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'transaction_id',
                        'amount',
                        'currency',
                        'gateway',
                        'status'
                    ]
                ]);
    }

    public function test_cannot_access_other_users_payment_status(): void
    {
        $user1 = User::factory()->create([
            'role' => 'customer',
            'status' => 'active'
        ]);

        $user2 = User::factory()->create([
            'role' => 'customer',
            'status' => 'active'
        ]);

        $transaction = PaymentTransaction::create([
            'user_id' => $user2->id,
            'amount' => 25.00,
            'currency' => 'USD',
            'gateway' => 'paypal',
            'payment_method' => 'paypal',
            'status' => 'pending',
            'gateway_transaction_id' => 'test_789'
        ]);

        Sanctum::actingAs($user1);

        $response = $this->getJson("/api/payments/{$transaction->id}/status");

        $response->assertStatus(404);
    }

    public function test_payment_transaction_model_relationships(): void
    {
        $user = User::factory()->create();
        
        $transaction = PaymentTransaction::create([
            'user_id' => $user->id,
            'amount' => 100.00,
            'currency' => 'USD',
            'gateway' => 'paypal',
            'payment_method' => 'paypal',
            'status' => 'completed',
            'gateway_transaction_id' => 'test_relationship'
        ]);

        $this->assertInstanceOf(User::class, $transaction->user);
        $this->assertEquals($user->id, $transaction->user->id);
        $this->assertTrue($user->paymentTransactions->contains($transaction));
    }

    public function test_payment_transaction_status_methods(): void
    {
        $completedTransaction = PaymentTransaction::create([
            'user_id' => User::factory()->create()->id,
            'amount' => 50.00,
            'currency' => 'USD',
            'gateway' => 'paypal',
            'payment_method' => 'paypal',
            'status' => 'completed'
        ]);

        $pendingTransaction = PaymentTransaction::create([
            'user_id' => User::factory()->create()->id,
            'amount' => 25.00,
            'currency' => 'USD',
            'gateway' => 'paypal',
            'payment_method' => 'paypal',
            'status' => 'pending'
        ]);

        $failedTransaction = PaymentTransaction::create([
            'user_id' => User::factory()->create()->id,
            'amount' => 75.00,
            'currency' => 'USD',
            'gateway' => 'paypal',
            'payment_method' => 'paypal',
            'status' => 'failed'
        ]);

        $this->assertTrue($completedTransaction->isCompleted());
        $this->assertFalse($completedTransaction->isPending());
        $this->assertFalse($completedTransaction->isFailed());

        $this->assertFalse($pendingTransaction->isCompleted());
        $this->assertTrue($pendingTransaction->isPending());
        $this->assertFalse($pendingTransaction->isFailed());

        $this->assertFalse($failedTransaction->isCompleted());
        $this->assertFalse($failedTransaction->isPending());
        $this->assertTrue($failedTransaction->isFailed());
    }
}
