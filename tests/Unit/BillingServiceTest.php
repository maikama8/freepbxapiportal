<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\BillingService;
use App\Models\CallRate;
use App\Models\CallRecord;
use App\Models\User;
use App\Exceptions\FreePBXApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class BillingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected BillingService $billingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->billingService = new BillingService();
    }

    public function test_calculate_call_cost_with_valid_destination()
    {
        // Create a test rate
        $rate = CallRate::create([
            'destination_prefix' => '1',
            'destination_name' => 'USA',
            'rate_per_minute' => 0.05,
            'minimum_duration' => 60,
            'billing_increment' => 6,
            'effective_date' => now(),
            'is_active' => true
        ]);

        $result = $this->billingService->calculateCallCost('12345678901', 120);

        $this->assertIsArray($result);
        $this->assertEquals($rate->id, $result['rate']->id);
        $this->assertEquals(0.10, $result['cost']);
        $this->assertEquals(120, $result['billable_duration']);
        $this->assertEquals(0.05, $result['rate_per_minute']);
        $this->assertEquals('USA', $result['destination_name']);
    }

    public function test_calculate_call_cost_with_minimum_duration()
    {
        $rate = CallRate::create([
            'destination_prefix' => '44',
            'destination_name' => 'UK',
            'rate_per_minute' => 0.08,
            'minimum_duration' => 60,
            'billing_increment' => 6,
            'effective_date' => now(),
            'is_active' => true
        ]);

        $result = $this->billingService->calculateCallCost('441234567890', 30);

        $this->assertEquals(0.08, $result['cost']); // Should bill for minimum 60 seconds
        $this->assertEquals(60, $result['billable_duration']);
    }

    public function test_calculate_call_cost_throws_exception_for_unknown_destination()
    {
        $this->expectException(FreePBXApiException::class);
        $this->expectExceptionMessage('No rate found for destination: 999999999');

        $this->billingService->calculateCallCost('999999999', 60);
    }

    public function test_process_call_billing_success()
    {
        $user = User::factory()->create([
            'account_type' => 'prepaid',
            'balance' => 10.00
        ]);

        $rate = CallRate::create([
            'destination_prefix' => '1',
            'destination_name' => 'USA',
            'rate_per_minute' => 0.05,
            'minimum_duration' => 60,
            'billing_increment' => 6,
            'effective_date' => now(),
            'is_active' => true
        ]);

        $callRecord = CallRecord::create([
            'user_id' => $user->id,
            'caller_id' => '1234567890',
            'destination' => '12345678901',
            'start_time' => now()->subMinutes(2),
            'end_time' => now(),
            'duration' => 120, // 2 minutes in seconds
            'status' => 'completed',
            'call_id' => 'test_call_123'
        ]);

        $result = $this->billingService->processCallBilling($callRecord);

        $this->assertTrue($result);
        $callRecord->refresh();
        $this->assertNotNull($callRecord->cost);
        $this->assertEquals(0.10, $callRecord->cost);

        $user->refresh();
        $this->assertEquals(9.90, $user->balance);
    }

    public function test_process_call_billing_already_billed()
    {
        $user = User::factory()->create();
        
        $callRecord = CallRecord::create([
            'user_id' => $user->id,
            'caller_id' => '1234567890',
            'destination' => '12345678901',
            'start_time' => now()->subMinutes(2),
            'end_time' => now(),
            'status' => 'completed',
            'call_id' => 'test_call_456',
            'cost' => 0.50
        ]);

        $result = $this->billingService->processCallBilling($callRecord);

        $this->assertTrue($result);
        $this->assertEquals(0.50, $callRecord->cost); // Should remain unchanged
    }

    public function test_process_call_billing_zero_duration()
    {
        $user = User::factory()->create();
        
        $callRecord = CallRecord::create([
            'user_id' => $user->id,
            'caller_id' => '1234567890',
            'destination' => '12345678901',
            'start_time' => now(),
            'end_time' => now(),
            'status' => 'completed',
            'call_id' => 'test_call_789'
        ]);

        $result = $this->billingService->processCallBilling($callRecord);

        $this->assertTrue($result);
        $callRecord->refresh();
        $this->assertEquals(0, $callRecord->cost);
    }

    public function test_can_afford_call_prepaid_sufficient_balance()
    {
        $user = User::factory()->create([
            'account_type' => 'prepaid',
            'balance' => 5.00
        ]);

        $rate = CallRate::create([
            'destination_prefix' => '1',
            'destination_name' => 'USA',
            'rate_per_minute' => 0.05,
            'minimum_duration' => 60,
            'billing_increment' => 6,
            'effective_date' => now(),
            'is_active' => true
        ]);

        $result = $this->billingService->canAffordCall($user, '12345678901', 60);

        $this->assertTrue($result['can_afford']);
        $this->assertNull($result['reason']);
        $this->assertEquals(0.05, $result['estimated_cost']);
        $this->assertEquals('USA', $result['destination_name']);
    }

    public function test_can_afford_call_prepaid_insufficient_balance()
    {
        $user = User::factory()->create([
            'account_type' => 'prepaid',
            'balance' => 0.02
        ]);

        $rate = CallRate::create([
            'destination_prefix' => '1',
            'destination_name' => 'USA',
            'rate_per_minute' => 0.05,
            'minimum_duration' => 60,
            'billing_increment' => 6,
            'effective_date' => now(),
            'is_active' => true
        ]);

        $result = $this->billingService->canAffordCall($user, '12345678901', 60);

        $this->assertFalse($result['can_afford']);
        $this->assertEquals('Insufficient balance', $result['reason']);
        $this->assertEquals(0.05, $result['estimated_cost']);
    }

    public function test_can_afford_call_no_rate_found()
    {
        $user = User::factory()->create([
            'account_type' => 'prepaid',
            'balance' => 5.00
        ]);

        $result = $this->billingService->canAffordCall($user, '999999999', 60);

        $this->assertFalse($result['can_afford']);
        $this->assertEquals('No rate found for destination', $result['reason']);
        $this->assertEquals(0, $result['estimated_cost']);
    }

    public function test_get_rate_info_success()
    {
        $rate = CallRate::create([
            'destination_prefix' => '44',
            'destination_name' => 'UK',
            'rate_per_minute' => 0.08,
            'minimum_duration' => 60,
            'billing_increment' => 6,
            'effective_date' => now(),
            'is_active' => true
        ]);

        $result = $this->billingService->getRateInfo('441234567890');

        $this->assertIsArray($result);
        $this->assertEquals('44', $result['destination_prefix']);
        $this->assertEquals('UK', $result['destination_name']);
        $this->assertEquals(0.08, $result['rate_per_minute']);
        $this->assertEquals(60, $result['minimum_duration']);
        $this->assertEquals(6, $result['billing_increment']);
    }

    public function test_get_rate_info_not_found()
    {
        $result = $this->billingService->getRateInfo('999999999');

        $this->assertNull($result);
    }

    public function test_get_max_call_duration()
    {
        $user = User::factory()->create([
            'account_type' => 'prepaid',
            'balance' => 1.00
        ]);

        $rate = CallRate::create([
            'destination_prefix' => '1',
            'destination_name' => 'USA',
            'rate_per_minute' => 0.05,
            'minimum_duration' => 60,
            'billing_increment' => 6,
            'effective_date' => now(),
            'is_active' => true
        ]);

        $result = $this->billingService->getMaxCallDuration($user, '12345678901');

        // $1.00 / $0.05 per minute = 20 minutes = 1200 seconds
        $this->assertEquals(1200, $result);
    }

    public function test_get_max_call_duration_postpaid_with_credit_limit()
    {
        $user = User::factory()->create([
            'account_type' => 'postpaid',
            'balance' => -5.00,
            'credit_limit' => 10.00
        ]);

        $rate = CallRate::create([
            'destination_prefix' => '1',
            'destination_name' => 'USA',
            'rate_per_minute' => 0.10,
            'minimum_duration' => 60,
            'billing_increment' => 6,
            'effective_date' => now(),
            'is_active' => true
        ]);

        $result = $this->billingService->getMaxCallDuration($user, '12345678901');

        // Available balance: -5 + 10 = 5.00
        // $5.00 / $0.10 per minute = 50 minutes = 3000 seconds
        $this->assertEquals(3000, $result);
    }

    public function test_get_max_call_duration_no_balance()
    {
        $user = User::factory()->create([
            'account_type' => 'prepaid',
            'balance' => 0.00
        ]);

        $rate = CallRate::create([
            'destination_prefix' => '1',
            'destination_name' => 'USA',
            'rate_per_minute' => 0.05,
            'minimum_duration' => 60,
            'billing_increment' => 6,
            'effective_date' => now(),
            'is_active' => true
        ]);

        $result = $this->billingService->getMaxCallDuration($user, '12345678901');

        $this->assertEquals(0, $result);
    }

    public function test_process_pending_billing()
    {
        $user = User::factory()->create([
            'account_type' => 'prepaid',
            'balance' => 10.00
        ]);

        $rate = CallRate::create([
            'destination_prefix' => '1',
            'destination_name' => 'USA',
            'rate_per_minute' => 0.05,
            'minimum_duration' => 60,
            'billing_increment' => 6,
            'effective_date' => now(),
            'is_active' => true
        ]);

        // Create unbilled call records
        CallRecord::create([
            'user_id' => $user->id,
            'caller_id' => '1234567890',
            'destination' => '12345678901',
            'start_time' => now()->subMinutes(3),
            'end_time' => now()->subMinutes(1),
            'status' => 'completed',
            'call_id' => 'test_call_1'
        ]);

        CallRecord::create([
            'user_id' => $user->id,
            'caller_id' => '1234567890',
            'destination' => '12345678901',
            'start_time' => now()->subMinutes(5),
            'end_time' => now()->subMinutes(3),
            'status' => 'completed',
            'call_id' => 'test_call_2'
        ]);

        $processedCount = $this->billingService->processPendingBilling();

        $this->assertEquals(2, $processedCount);

        // Verify calls were billed
        $billedCalls = CallRecord::whereNotNull('cost')->count();
        $this->assertEquals(2, $billedCalls);
    }
}