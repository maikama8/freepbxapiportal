<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\CallRecord;
use App\Models\CallRate;
use App\Models\PaymentTransaction;
use App\Models\BalanceTransaction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DatabaseIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_model_relationships()
    {
        $user = User::factory()->create();

        // Test call records relationship
        $callRecord = CallRecord::factory()->create(['user_id' => $user->id]);
        $this->assertTrue($user->callRecords->contains($callRecord));

        // Test payment transactions relationship
        $paymentTransaction = PaymentTransaction::factory()->create(['user_id' => $user->id]);
        $this->assertTrue($user->paymentTransactions->contains($paymentTransaction));

        // Test balance transactions relationship
        $balanceTransaction = BalanceTransaction::create([
            'user_id' => $user->id,
            'type' => 'credit',
            'amount' => 50.00,
            'description' => 'Test credit',
            'reference_type' => 'payment',
            'reference_id' => $paymentTransaction->id
        ]);
        $this->assertTrue($user->balanceTransactions->contains($balanceTransaction));

        // Test invoices relationship
        $invoice = Invoice::create([
            'user_id' => $user->id,
            'invoice_number' => 'INV-001',
            'amount' => 25.00,
            'status' => 'pending',
            'due_date' => now()->addDays(30)
        ]);
        $this->assertTrue($user->invoices->contains($invoice));
    }

    public function test_call_record_model_functionality()
    {
        $user = User::factory()->create();
        
        $callRecord = CallRecord::create([
            'user_id' => $user->id,
            'call_id' => 'test_call_123',
            'caller_id' => '+1234567890',
            'destination' => '+1987654321',
            'start_time' => now()->subMinutes(5),
            'end_time' => now()->subMinutes(3),
            'status' => 'completed'
        ]);

        // Test duration calculation
        $this->assertEquals(120, $callRecord->getDurationInSeconds());

        // Test formatted duration
        $this->assertEquals('02:00', $callRecord->getFormattedDuration());

        // Test status methods
        $this->assertTrue($callRecord->isCompleted());
        $this->assertFalse($callRecord->isActive());

        // Test user relationship
        $this->assertEquals($user->id, $callRecord->user->id);
    }

    public function test_call_rate_model_functionality()
    {
        $rate = CallRate::create([
            'destination_prefix' => '1',
            'destination_name' => 'USA',
            'rate_per_minute' => 0.05,
            'minimum_duration' => 60,
            'billing_increment' => 6,
            'effective_date' => now(),
            'is_active' => true
        ]);

        // Test cost calculation
        $this->assertEquals(0.05, $rate->calculateCost(60)); // 1 minute
        $this->assertEquals(0.10, $rate->calculateCost(120)); // 2 minutes
        $this->assertEquals(0.05, $rate->calculateCost(30)); // Should use minimum duration

        // Test rate finding
        $foundRate = CallRate::findRateForDestination('12345678901');
        $this->assertEquals($rate->id, $foundRate->id);

        // Test formatted rate
        $this->assertEquals('0.050000', $rate->getFormattedRate());
    }

    public function test_payment_transaction_model_functionality()
    {
        $user = User::factory()->create();
        
        $transaction = PaymentTransaction::create([
            'user_id' => $user->id,
            'amount' => 50.00,
            'currency' => 'USD',
            'gateway' => 'paypal',
            'payment_method' => 'paypal',
            'status' => 'pending',
            'gateway_transaction_id' => 'paypal_123'
        ]);

        // Test status methods
        $this->assertTrue($transaction->isPending());
        $this->assertFalse($transaction->isCompleted());
        $this->assertFalse($transaction->isFailed());

        // Test status transitions
        $transaction->markAsCompleted();
        $this->assertTrue($transaction->isCompleted());
        $this->assertFalse($transaction->isPending());

        // Test user relationship
        $this->assertEquals($user->id, $transaction->user->id);
    }

    public function test_user_balance_operations()
    {
        $user = User::factory()->create([
            'account_type' => 'prepaid',
            'balance' => 100.00
        ]);

        // Test sufficient balance check
        $this->assertTrue($user->hasSufficientBalance(50.00));
        $this->assertFalse($user->hasSufficientBalance(150.00));

        // Test balance deduction
        $this->assertTrue($user->deductBalance(25.00));
        $this->assertEquals(75.00, $user->fresh()->balance);

        // Test insufficient balance deduction
        $this->assertFalse($user->deductBalance(100.00));
        $this->assertEquals(75.00, $user->fresh()->balance); // Should remain unchanged

        // Test balance addition
        $user->addBalance(50.00);
        $this->assertEquals(125.00, $user->fresh()->balance);
    }

    public function test_postpaid_user_credit_limit()
    {
        $user = User::factory()->create([
            'account_type' => 'postpaid',
            'balance' => -25.00,
            'credit_limit' => 100.00
        ]);

        // Test sufficient balance with credit limit
        $this->assertTrue($user->hasSufficientBalance(50.00)); // -25 + 100 = 75 available
        $this->assertFalse($user->hasSufficientBalance(100.00)); // Exceeds available credit

        // Test balance deduction for postpaid
        $this->assertTrue($user->deductBalance(30.00));
        $this->assertEquals(-55.00, $user->fresh()->balance);
    }

    public function test_invoice_model_with_items()
    {
        $user = User::factory()->create();
        
        $invoice = Invoice::create([
            'user_id' => $user->id,
            'invoice_number' => 'INV-001',
            'amount' => 0, // Will be calculated from items
            'status' => 'draft',
            'due_date' => now()->addDays(30)
        ]);

        // Add invoice items
        $item1 = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Call charges',
            'quantity' => 1,
            'unit_price' => 25.50,
            'total' => 25.50
        ]);

        $item2 = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Service fee',
            'quantity' => 1,
            'unit_price' => 5.00,
            'total' => 5.00
        ]);

        // Test invoice items relationship
        $this->assertCount(2, $invoice->items);
        $this->assertTrue($invoice->items->contains($item1));
        $this->assertTrue($invoice->items->contains($item2));

        // Test total calculation
        $expectedTotal = 30.50;
        $actualTotal = $invoice->items->sum('total');
        $this->assertEquals($expectedTotal, $actualTotal);
    }

    public function test_balance_transaction_tracking()
    {
        $user = User::factory()->create(['balance' => 50.00]);
        
        // Create payment transaction
        $paymentTransaction = PaymentTransaction::factory()->create([
            'user_id' => $user->id,
            'amount' => 25.00,
            'status' => 'completed'
        ]);

        // Create balance transaction for payment
        $balanceTransaction = BalanceTransaction::create([
            'user_id' => $user->id,
            'type' => 'credit',
            'amount' => 25.00,
            'description' => 'Payment received',
            'reference_type' => 'payment',
            'reference_id' => $paymentTransaction->id,
            'balance_before' => 50.00,
            'balance_after' => 75.00
        ]);

        // Test relationships
        $this->assertEquals($user->id, $balanceTransaction->user->id);
        $this->assertEquals('payment', $balanceTransaction->reference_type);
        $this->assertEquals($paymentTransaction->id, $balanceTransaction->reference_id);

        // Test balance tracking
        $this->assertEquals(50.00, $balanceTransaction->balance_before);
        $this->assertEquals(75.00, $balanceTransaction->balance_after);
    }

    public function test_database_constraints_and_validation()
    {
        // Test unique constraints
        $user1 = User::factory()->create(['email' => 'test@example.com']);
        
        $this->expectException(\Illuminate\Database\QueryException::class);
        User::factory()->create(['email' => 'test@example.com']); // Should fail due to unique constraint
    }

    public function test_soft_deletes_if_implemented()
    {
        // This test assumes soft deletes are implemented on certain models
        // Skip if not implemented
        if (!method_exists(User::class, 'bootSoftDeletes')) {
            $this->markTestSkipped('Soft deletes not implemented on User model');
        }

        $user = User::factory()->create();
        $userId = $user->id;

        $user->delete();

        // Should not find with regular query
        $this->assertNull(User::find($userId));

        // Should find with trashed
        $this->assertNotNull(User::withTrashed()->find($userId));
    }

    public function test_model_scopes()
    {
        // Create active and inactive rates
        CallRate::factory()->create(['is_active' => true, 'effective_date' => now()->subDay()]);
        CallRate::factory()->create(['is_active' => false, 'effective_date' => now()->subDay()]);
        CallRate::factory()->create(['is_active' => true, 'effective_date' => now()->addDay()]);

        // Test active scope
        $activeRates = CallRate::active()->get();
        $this->assertCount(1, $activeRates);
        $this->assertTrue($activeRates->first()->is_active);

        // Test prefix scope
        CallRate::factory()->create(['destination_prefix' => '44', 'is_active' => true]);
        $ukRates = CallRate::forPrefix('44')->get();
        $this->assertCount(1, $ukRates);
        $this->assertEquals('44', $ukRates->first()->destination_prefix);
    }

    public function test_model_casting()
    {
        $user = User::factory()->create([
            'balance' => 123.4567,
            'credit_limit' => 999.9999
        ]);

        // Test decimal casting
        $this->assertEquals('123.4567', $user->balance);
        $this->assertEquals('999.9999', $user->credit_limit);

        // Test datetime casting
        $this->assertInstanceOf(\Carbon\Carbon::class, $user->created_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $user->updated_at);
    }

    public function test_complex_queries_and_joins()
    {
        $user = User::factory()->create();
        
        // Create call records with costs
        CallRecord::factory()->create([
            'user_id' => $user->id,
            'cost' => 5.50,
            'status' => 'completed',
            'start_time' => now()->subDays(1)
        ]);
        
        CallRecord::factory()->create([
            'user_id' => $user->id,
            'cost' => 3.25,
            'status' => 'completed',
            'start_time' => now()->subDays(2)
        ]);

        // Test aggregation queries
        $totalCost = CallRecord::where('user_id', $user->id)
            ->where('status', 'completed')
            ->sum('cost');
        
        $this->assertEquals(8.75, $totalCost);

        // Test date range queries
        $recentCalls = CallRecord::where('user_id', $user->id)
            ->where('start_time', '>=', now()->subDays(1)->startOfDay())
            ->count();
        
        $this->assertEquals(1, $recentCalls);
    }
}