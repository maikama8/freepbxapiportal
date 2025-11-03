<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\CallRecord;
use App\Models\CallRate;
use App\Models\CountryRate;
use App\Models\BalanceTransaction;
use App\Services\AdvancedBillingService;
use App\Services\RealTimeBillingService;
use App\Services\FreePBX\CallManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Mockery;

class CallBillingWorkflowTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $mockCallManagementService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockCallManagementService = Mockery::mock(CallManagementService::class);
        $this->app->instance(CallManagementService::class, $this->mockCallManagementService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function customer_can_initiate_call_with_sufficient_balance()
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'account_type' => 'prepaid',
            'balance' => 10.00
        ]);

        CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'call_rate_per_minute' => 0.05,
            'billing_increment' => 6,
            'minimum_duration' => 0,
            'is_active' => true
        ]);

        $this->mockCallManagementService
            ->shouldReceive('initiateCall')
            ->once()
            ->with($user, '12125551234')
            ->andReturn([
                'success' => true,
                'call_id' => 'test-call-123',
                'status' => 'initiated'
            ]);

        $response = $this->actingAs($user)
            ->post('/customer/calls/initiate', [
                'destination' => '12125551234'
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Verify call record was created
        $callRecord = CallRecord::where('user_id', $user->id)
            ->where('destination', '12125551234')
            ->first();

        $this->assertNotNull($callRecord);
        $this->assertEquals('test-call-123', $callRecord->call_id);
        $this->assertEquals('initiated', $callRecord->status);
    }

    /** @test */
    public function customer_cannot_initiate_call_with_insufficient_balance()
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'account_type' => 'prepaid',
            'balance' => 0.01 // Very low balance
        ]);

        CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'call_rate_per_minute' => 0.05,
            'billing_increment' => 6,
            'minimum_duration' => 6,
            'is_active' => true
        ]);

        $response = $this->actingAs($user)
            ->post('/customer/calls/initiate', [
                'destination' => '12125551234'
            ]);

        $response->assertStatus(400);
        $response->assertJson(['success' => false]);
        $response->assertJsonFragment(['error' => 'Insufficient balance']);

        // Verify no call record was created
        $callRecord = CallRecord::where('user_id', $user->id)->first();
        $this->assertNull($callRecord);
    }

    /** @test */
    public function call_billing_with_6_6_increment_calculation()
    {
        $user = User::factory()->create([
            'account_type' => 'prepaid',
            'balance' => 10.00
        ]);

        CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'call_rate_per_minute' => 0.06, // $0.06 per minute
            'billing_increment' => 6,
            'minimum_duration' => 0,
            'is_active' => true
        ]);

        // Create completed call record (65 seconds)
        $callRecord = CallRecord::create([
            'user_id' => $user->id,
            'call_id' => 'test-call-123',
            'destination' => '12125551234',
            'start_time' => now()->subMinutes(2),
            'end_time' => now()->subMinutes(1)->addSeconds(5), // 65 seconds total
            'status' => 'completed'
        ]);

        $billingService = app(AdvancedBillingService::class);
        $result = $billingService->processAdvancedCallBilling($callRecord);

        $this->assertTrue($result);

        $callRecord->refresh();
        $user->refresh();

        // 65 seconds with 6/6 billing = 6 initial + 60 subsequent (10 increments of 6) = 66 seconds billable
        // 66 seconds = 1.1 minutes * $0.06 = $0.066
        $this->assertEquals(0.066, $callRecord->cost);
        $this->assertEquals('paid', $callRecord->billing_status);
        $this->assertEquals(9.934, $user->balance); // 10.00 - 0.066

        // Verify billing details
        $billingDetails = json_decode($callRecord->billing_details, true);
        $this->assertEquals(65, $billingDetails['actual_duration']);
        $this->assertEquals(66, $billingDetails['billable_duration']);
        $this->assertEquals(0.06, $billingDetails['rate_per_minute']);
    }

    /** @test */
    public function call_billing_with_30_30_increment_calculation()
    {
        $user = User::factory()->create([
            'account_type' => 'prepaid',
            'balance' => 10.00
        ]);

        CallRate::create([
            'destination_prefix' => '1212',
            'destination_name' => 'New York',
            'rate_per_minute' => 0.04,
            'minimum_duration' => 0,
            'billing_increment_config' => '30/30',
            'effective_date' => now()
        ]);

        // Create completed call record (45 seconds)
        $callRecord = CallRecord::create([
            'user_id' => $user->id,
            'call_id' => 'test-call-456',
            'destination' => '12125551234',
            'start_time' => now()->subMinutes(1),
            'end_time' => now()->subSeconds(15), // 45 seconds total
            'status' => 'completed'
        ]);

        $billingService = app(AdvancedBillingService::class);
        $result = $billingService->processAdvancedCallBilling($callRecord);

        $this->assertTrue($result);

        $callRecord->refresh();
        $user->refresh();

        // 45 seconds with 30/30 billing = 30 initial + 30 subsequent (15s rounded up) = 60 seconds billable
        // 60 seconds = 1 minute * $0.04 = $0.04
        $this->assertEquals(0.04, $callRecord->cost);
        $this->assertEquals('paid', $callRecord->billing_status);
        $this->assertEquals(9.96, $user->balance); // 10.00 - 0.04
    }

    /** @test */
    public function call_billing_with_mixed_increment_1_60()
    {
        $user = User::factory()->create([
            'account_type' => 'prepaid',
            'balance' => 10.00
        ]);

        CallRate::create([
            'destination_prefix' => '1',
            'destination_name' => 'USA',
            'rate_per_minute' => 0.05,
            'minimum_duration' => 0,
            'billing_increment_config' => '1/60',
            'effective_date' => now()
        ]);

        // Create completed call record (65 seconds)
        $callRecord = CallRecord::create([
            'user_id' => $user->id,
            'call_id' => 'test-call-789',
            'destination' => '12125551234',
            'start_time' => now()->subMinutes(2),
            'end_time' => now()->subMinutes(1)->addSeconds(5), // 65 seconds total
            'status' => 'completed'
        ]);

        $billingService = app(AdvancedBillingService::class);
        $result = $billingService->processAdvancedCallBilling($callRecord);

        $this->assertTrue($result);

        $callRecord->refresh();

        // 65 seconds with 1/60 billing = 1 initial + 60 subsequent (64s rounded up to 60s) = 61 seconds billable
        // 61 seconds = 1.0167 minutes * $0.05 = $0.0508
        $this->assertEquals(0.0508, round($callRecord->cost, 4));
    }

    /** @test */
    public function call_billing_applies_minimum_duration()
    {
        $user = User::factory()->create([
            'account_type' => 'prepaid',
            'balance' => 10.00
        ]);

        CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'call_rate_per_minute' => 0.05,
            'billing_increment' => 6,
            'minimum_duration' => 30, // 30 second minimum
            'is_active' => true
        ]);

        // Create short call record (10 seconds)
        $callRecord = CallRecord::create([
            'user_id' => $user->id,
            'call_id' => 'test-call-short',
            'destination' => '12125551234',
            'start_time' => now()->subSeconds(20),
            'end_time' => now()->subSeconds(10), // 10 seconds total
            'status' => 'completed'
        ]);

        $billingService = app(AdvancedBillingService::class);
        $result = $billingService->processAdvancedCallBilling($callRecord);

        $this->assertTrue($result);

        $callRecord->refresh();

        // 10 seconds but minimum 30 seconds, with 6/6 billing = 30 seconds billable
        // 30 seconds = 0.5 minutes * $0.05 = $0.025
        $this->assertEquals(0.025, $callRecord->cost);

        $billingDetails = json_decode($callRecord->billing_details, true);
        $this->assertEquals(10, $billingDetails['actual_duration']);
        $this->assertEquals(30, $billingDetails['billable_duration']);
        $this->assertEquals(30, $billingDetails['minimum_duration']);
    }

    /** @test */
    public function postpaid_call_billing_workflow()
    {
        $user = User::factory()->create([
            'account_type' => 'postpaid',
            'balance' => 0.00,
            'credit_limit' => 100.00
        ]);

        CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'call_rate_per_minute' => 0.05,
            'billing_increment' => 6,
            'is_active' => true
        ]);

        // Create completed call record
        $callRecord = CallRecord::create([
            'user_id' => $user->id,
            'call_id' => 'postpaid-call-123',
            'destination' => '12125551234',
            'start_time' => now()->subMinutes(2),
            'end_time' => now()->subMinutes(1), // 60 seconds
            'status' => 'completed'
        ]);

        $billingService = app(AdvancedBillingService::class);
        $result = $billingService->processAdvancedCallBilling($callRecord);

        $this->assertTrue($result);

        $callRecord->refresh();
        $user->refresh();

        // Cost calculated but not deducted from balance for postpaid
        $this->assertEquals(0.05, $callRecord->cost); // 1 minute * $0.05
        $this->assertEquals('unpaid', $callRecord->billing_status);
        $this->assertEquals(0.00, $user->balance); // Balance unchanged
    }

    /** @test */
    public function real_time_billing_terminates_call_on_insufficient_balance()
    {
        $user = User::factory()->create([
            'account_type' => 'prepaid',
            'balance' => 0.10 // Very low balance
        ]);

        CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'call_rate_per_minute' => 0.60, // High rate to quickly exhaust balance
            'billing_increment' => 6,
            'is_active' => true
        ]);

        $callRecord = CallRecord::create([
            'user_id' => $user->id,
            'call_id' => 'real-time-call-123',
            'destination' => '12125551234',
            'start_time' => now()->subSeconds(30),
            'status' => 'in_progress'
        ]);

        $this->mockCallManagementService
            ->shouldReceive('terminateCall')
            ->once()
            ->with('real-time-call-123')
            ->andReturn(true);

        $realTimeBillingService = app(RealTimeBillingService::class);
        
        // Start real-time billing
        $started = $realTimeBillingService->startRealTimeBilling($callRecord);
        $this->assertTrue($started);

        // Simulate periodic billing check that finds insufficient balance
        $terminated = $realTimeBillingService->terminateCallForInsufficientBalance($callRecord);
        $this->assertTrue($terminated);

        $callRecord->refresh();
        $this->assertEquals('terminated', $callRecord->status);
        $this->assertEquals('terminated', $callRecord->billing_status);
    }

    /** @test */
    public function zero_duration_calls_are_not_billed()
    {
        $user = User::factory()->create([
            'account_type' => 'prepaid',
            'balance' => 10.00
        ]);

        // Create zero duration call (failed/unanswered)
        $callRecord = CallRecord::create([
            'user_id' => $user->id,
            'call_id' => 'zero-duration-call',
            'destination' => '12125551234',
            'start_time' => now()->subMinutes(1),
            'end_time' => now()->subMinutes(1), // Same time = zero duration
            'status' => 'completed'
        ]);

        $billingService = app(AdvancedBillingService::class);
        $result = $billingService->processAdvancedCallBilling($callRecord);

        $this->assertTrue($result);

        $callRecord->refresh();
        $user->refresh();

        $this->assertEquals(0, $callRecord->cost);
        $this->assertEquals('completed', $callRecord->billing_status);
        $this->assertEquals(10.00, $user->balance); // Balance unchanged
    }

    /** @test */
    public function customer_can_view_call_history_with_billing_details()
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'account_type' => 'prepaid'
        ]);

        // Create call records with different statuses and costs
        CallRecord::create([
            'user_id' => $user->id,
            'call_id' => 'call-1',
            'destination' => '12125551234',
            'start_time' => now()->subHours(2),
            'end_time' => now()->subHours(2)->addMinutes(3),
            'status' => 'completed',
            'cost' => 0.15,
            'billing_status' => 'paid'
        ]);

        CallRecord::create([
            'user_id' => $user->id,
            'call_id' => 'call-2',
            'destination' => '13105551234',
            'start_time' => now()->subHours(1),
            'end_time' => now()->subHours(1)->addMinutes(1),
            'status' => 'completed',
            'cost' => 0.05,
            'billing_status' => 'paid'
        ]);

        $response = $this->actingAs($user)
            ->get('/customer/call-history');

        $response->assertStatus(200);
        $response->assertSee('12125551234');
        $response->assertSee('13105551234');
        $response->assertSee('$0.15');
        $response->assertSee('$0.05');
        $response->assertSee('paid');
        $response->assertSee('3 minutes'); // Duration display
    }

    /** @test */
    public function customer_can_filter_call_history()
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'account_type' => 'prepaid'
        ]);

        // Create calls on different dates
        CallRecord::create([
            'user_id' => $user->id,
            'call_id' => 'old-call',
            'destination' => '12125551234',
            'start_time' => now()->subWeek(),
            'end_time' => now()->subWeek()->addMinutes(2),
            'status' => 'completed',
            'cost' => 0.10
        ]);

        CallRecord::create([
            'user_id' => $user->id,
            'call_id' => 'recent-call',
            'destination' => '13105551234',
            'start_time' => now()->subHour(),
            'end_time' => now()->subHour()->addMinutes(1),
            'status' => 'completed',
            'cost' => 0.05
        ]);

        // Filter by date range (last 24 hours)
        $response = $this->actingAs($user)
            ->get('/customer/call-history?from=' . now()->subDay()->format('Y-m-d') . 
                  '&to=' . now()->format('Y-m-d'));

        $response->assertStatus(200);
        $response->assertSee('recent-call');
        $response->assertSee('13105551234');
        $response->assertDontSee('old-call');
        $response->assertDontSee('12125551234');
    }

    /** @test */
    public function complete_call_workflow_with_billing()
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'account_type' => 'prepaid',
            'balance' => 10.00
        ]);

        CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'call_rate_per_minute' => 0.05,
            'billing_increment' => 6,
            'is_active' => true
        ]);

        // Step 1: Initiate call
        $this->mockCallManagementService
            ->shouldReceive('initiateCall')
            ->once()
            ->andReturn([
                'success' => true,
                'call_id' => 'complete-workflow-call',
                'status' => 'initiated'
            ]);

        $response = $this->actingAs($user)
            ->post('/customer/calls/initiate', [
                'destination' => '12125551234'
            ]);

        $response->assertStatus(200);

        // Step 2: Simulate call progression and completion
        $callRecord = CallRecord::where('call_id', 'complete-workflow-call')->first();
        $this->assertNotNull($callRecord);

        // Update call to completed status with duration
        $callRecord->update([
            'status' => 'completed',
            'end_time' => $callRecord->start_time->addMinutes(2) // 2 minute call
        ]);

        // Step 3: Process billing
        $billingService = app(AdvancedBillingService::class);
        $result = $billingService->processAdvancedCallBilling($callRecord);
        $this->assertTrue($result);

        // Step 4: Verify final state
        $callRecord->refresh();
        $user->refresh();

        $this->assertEquals('completed', $callRecord->status);
        $this->assertEquals('paid', $callRecord->billing_status);
        $this->assertEquals(0.10, $callRecord->cost); // 2 minutes * $0.05
        $this->assertEquals(9.90, $user->balance); // 10.00 - 0.10

        // Verify transaction record
        $transaction = BalanceTransaction::where('user_id', $user->id)
            ->where('type', 'call_charge')
            ->first();

        $this->assertNotNull($transaction);
        $this->assertEquals(-0.10, $transaction->amount);
        $this->assertEquals($callRecord->id, $transaction->reference_id);

        // Step 5: Verify call appears in history
        $response = $this->actingAs($user)
            ->get('/customer/call-history');

        $response->assertStatus(200);
        $response->assertSee('complete-workflow-call');
        $response->assertSee('12125551234');
        $response->assertSee('$0.10');
        $response->assertSee('paid');
    }
}