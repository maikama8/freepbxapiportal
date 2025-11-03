<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\RealTimeBillingService;
use App\Services\AdvancedBillingService;
use App\Services\FreePBX\CallManagementService;
use App\Models\CallRecord;
use App\Models\User;
use App\Models\CountryRate;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Mockery;

class RealTimeBillingServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected RealTimeBillingService $realTimeBillingService;
    protected $mockAdvancedBillingService;
    protected $mockCallManagementService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockAdvancedBillingService = Mockery::mock(AdvancedBillingService::class);
        $this->mockCallManagementService = Mockery::mock(CallManagementService::class);
        
        $this->realTimeBillingService = new RealTimeBillingService(
            $this->mockAdvancedBillingService,
            $this->mockCallManagementService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_starts_real_time_billing_successfully()
    {
        // Enable real-time billing
        SystemSetting::create([
            'key' => 'billing.enable_real_time',
            'value' => true,
            'type' => 'boolean',
            'group' => 'billing'
        ]);

        $user = User::factory()->create([
            'account_type' => 'prepaid',
            'balance' => 10.00
        ]);

        $callRecord = CallRecord::create([
            'user_id' => $user->id,
            'call_id' => 'test-call-123',
            'destination' => '12125551234',
            'start_time' => now(),
            'status' => 'initiated'
        ]);

        $billingConfig = ['initial' => 6, 'subsequent' => 6];
        
        $this->mockAdvancedBillingService
            ->shouldReceive('getBillingIncrementForDestination')
            ->with('12125551234')
            ->andReturn($billingConfig);

        $this->mockAdvancedBillingService
            ->shouldReceive('calculateAdvancedCallCost')
            ->with('12125551234', 6)
            ->andReturn(['cost' => 0.01]);

        $result = $this->realTimeBillingService->startRealTimeBilling($callRecord);

        $this->assertTrue($result);
        $this->assertNotNull(Cache::get("billing_session_{$callRecord->call_id}"));
    }

    /** @test */
    public function it_does_not_start_billing_when_disabled()
    {
        SystemSetting::create([
            'key' => 'billing.enable_real_time',
            'value' => false,
            'type' => 'boolean',
            'group' => 'billing'
        ]);

        $user = User::factory()->create(['account_type' => 'prepaid']);
        $callRecord = CallRecord::create([
            'user_id' => $user->id,
            'call_id' => 'test-call-123',
            'destination' => '12125551234',
            'start_time' => now(),
            'status' => 'initiated'
        ]);

        $result = $this->realTimeBillingService->startRealTimeBilling($callRecord);

        $this->assertFalse($result);
        $this->assertNull(Cache::get("billing_session_{$callRecord->call_id}"));
    }

    /** @test */
    public function it_processes_periodic_billing()
    {
        SystemSetting::create([
            'key' => 'billing.enable_real_time',
            'value' => true,
            'type' => 'boolean',
            'group' => 'billing'
        ]);

        $user = User::factory()->create([
            'account_type' => 'prepaid',
            'balance' => 10.00
        ]);

        $callRecord = CallRecord::create([
            'user_id' => $user->id,
            'call_id' => 'test-call-123',
            'destination' => '12125551234',
            'start_time' => now()->subSeconds(30),
            'status' => 'in_progress'
        ]);

        // Set up billing session
        $billingSession = [
            'call_id' => 'test-call-123',
            'user_id' => $user->id,
            'destination' => '12125551234',
            'billing_config' => ['initial' => 6, 'subsequent' => 6],
            'current_cost' => 0.01,
            'start_time' => now()->subSeconds(30)->toISOString()
        ];
        Cache::put("billing_session_{$callRecord->call_id}", $billingSession, 3600);

        $this->mockAdvancedBillingService
            ->shouldReceive('calculateAdvancedCallCost')
            ->with('12125551234', 30)
            ->andReturn(['cost' => 0.025]);

        $result = $this->realTimeBillingService->processPeriodicBilling($callRecord);

        $this->assertTrue($result);
        
        $updatedSession = Cache::get("billing_session_{$callRecord->call_id}");
        $this->assertEquals(0.025, $updatedSession['current_cost']);
        $this->assertEquals(1, $updatedSession['periodic_checks']);
    }

    /** @test */
    public function it_terminates_call_for_insufficient_balance()
    {
        SystemSetting::create([
            'key' => 'billing.enable_real_time',
            'value' => true,
            'type' => 'boolean',
            'group' => 'billing'
        ]);

        SystemSetting::create([
            'key' => 'billing.auto_terminate_on_zero_balance',
            'value' => true,
            'type' => 'boolean',
            'group' => 'billing'
        ]);

        SystemSetting::create([
            'key' => 'billing.grace_period_seconds',
            'value' => 0,
            'type' => 'integer',
            'group' => 'billing'
        ]);

        $user = User::factory()->create([
            'account_type' => 'prepaid',
            'balance' => 0.01 // Very low balance
        ]);

        $callRecord = CallRecord::create([
            'user_id' => $user->id,
            'call_id' => 'test-call-123',
            'destination' => '12125551234',
            'start_time' => now()->subSeconds(60),
            'status' => 'in_progress'
        ]);

        // Set up billing session with high cost
        $billingSession = [
            'call_id' => 'test-call-123',
            'user_id' => $user->id,
            'destination' => '12125551234',
            'billing_config' => ['initial' => 6, 'subsequent' => 6],
            'current_cost' => 5.00, // More than user balance
            'start_time' => now()->subSeconds(60)->toISOString()
        ];
        Cache::put("billing_session_{$callRecord->call_id}", $billingSession, 3600);

        $this->mockCallManagementService
            ->shouldReceive('terminateCall')
            ->with('test-call-123')
            ->andReturn(true);

        $this->mockAdvancedBillingService
            ->shouldReceive('calculateAdvancedCallCost')
            ->andReturn(['cost' => 5.00]);

        $result = $this->realTimeBillingService->terminateCallForInsufficientBalance($callRecord);

        $this->assertTrue($result);
        $callRecord->refresh();
        $this->assertEquals('terminated', $callRecord->status);
        $this->assertEquals('terminated', $callRecord->billing_status);
    }

    /** @test */
    public function it_finalizes_billing_successfully()
    {
        $user = User::factory()->create([
            'account_type' => 'prepaid',
            'balance' => 10.00
        ]);

        $callRecord = CallRecord::create([
            'user_id' => $user->id,
            'call_id' => 'test-call-123',
            'destination' => '12125551234',
            'start_time' => now()->subMinutes(2),
            'end_time' => now()->subMinutes(1),
            'status' => 'completed'
        ]);

        // Set up billing session
        $billingSession = [
            'call_id' => 'test-call-123',
            'user_id' => $user->id,
            'destination' => '12125551234',
            'billing_config' => ['initial' => 6, 'subsequent' => 6],
            'current_cost' => 0.05,
            'billable_duration' => 60,
            'periodic_checks' => 5,
            'reserved_amount' => 0.01
        ];
        Cache::put("billing_session_{$callRecord->call_id}", $billingSession, 3600);

        $this->mockAdvancedBillingService
            ->shouldReceive('calculateAdvancedCallCost')
            ->andReturn(['cost' => 0.05]);

        $result = $this->realTimeBillingService->finalizeBilling($callRecord);

        $this->assertTrue($result);
        $callRecord->refresh();
        $this->assertEquals(0.05, $callRecord->cost);
        $this->assertEquals('paid', $callRecord->billing_status);
        $this->assertNull(Cache::get("billing_session_{$callRecord->call_id}"));
        
        $user->refresh();
        $this->assertEquals(9.95, $user->balance);
    }

    /** @test */
    public function it_falls_back_to_advanced_billing_when_no_session()
    {
        $user = User::factory()->create(['account_type' => 'prepaid']);
        
        $callRecord = CallRecord::create([
            'user_id' => $user->id,
            'call_id' => 'test-call-123',
            'destination' => '12125551234',
            'start_time' => now()->subMinutes(2),
            'end_time' => now()->subMinutes(1),
            'status' => 'completed'
        ]);

        $this->mockAdvancedBillingService
            ->shouldReceive('processAdvancedCallBilling')
            ->with($callRecord)
            ->andReturn(true);

        $result = $this->realTimeBillingService->finalizeBilling($callRecord);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_gets_active_calls_with_billing()
    {
        $user1 = User::factory()->create(['balance' => 10.00]);
        $user2 = User::factory()->create(['balance' => 5.00]);

        $call1 = CallRecord::create([
            'user_id' => $user1->id,
            'call_id' => 'call-1',
            'destination' => '12125551234',
            'start_time' => now()->subMinutes(1),
            'status' => 'in_progress'
        ]);

        $call2 = CallRecord::create([
            'user_id' => $user2->id,
            'call_id' => 'call-2',
            'destination' => '13105551234',
            'start_time' => now()->subMinutes(2),
            'status' => 'in_progress'
        ]);

        // Set up billing sessions
        Cache::put("billing_session_call-1", [
            'call_id' => 'call-1',
            'current_cost' => 0.05,
            'billing_config' => ['initial' => 6, 'subsequent' => 6]
        ], 3600);

        Cache::put("billing_session_call-2", [
            'call_id' => 'call-2',
            'current_cost' => 0.10,
            'billing_config' => ['initial' => 6, 'subsequent' => 6]
        ], 3600);

        $this->mockAdvancedBillingService
            ->shouldReceive('calculateAdvancedCallCost')
            ->twice()
            ->andReturn(['cost' => 0.05], ['cost' => 0.10]);

        $activeCalls = $this->realTimeBillingService->getActiveCallsWithBilling();

        $this->assertCount(2, $activeCalls);
        $this->assertEquals('call-1', $activeCalls[0]['call_record']->call_id);
        $this->assertEquals('call-2', $activeCalls[1]['call_record']->call_id);
    }

    /** @test */
    public function it_gets_real_time_billing_stats()
    {
        SystemSetting::create([
            'key' => 'billing.enable_real_time',
            'value' => true,
            'type' => 'boolean',
            'group' => 'billing'
        ]);

        SystemSetting::create([
            'key' => 'billing.auto_terminate_on_zero_balance',
            'value' => true,
            'type' => 'boolean',
            'group' => 'billing'
        ]);

        SystemSetting::create([
            'key' => 'billing.grace_period_seconds',
            'value' => 30,
            'type' => 'integer',
            'group' => 'billing'
        ]);

        $user = User::factory()->create(['balance' => 1.00]);
        
        $call = CallRecord::create([
            'user_id' => $user->id,
            'call_id' => 'call-1',
            'destination' => '12125551234',
            'start_time' => now()->subMinutes(1),
            'status' => 'in_progress'
        ]);

        Cache::put("billing_session_call-1", [
            'call_id' => 'call-1',
            'current_cost' => 0.75,
            'billing_config' => ['initial' => 6, 'subsequent' => 6]
        ], 3600);

        $this->mockAdvancedBillingService
            ->shouldReceive('calculateAdvancedCallCost')
            ->andReturn(['cost' => 0.75]);

        $stats = $this->realTimeBillingService->getRealTimeBillingStats();

        $this->assertEquals(1, $stats['active_calls_count']);
        $this->assertEquals(0.75, $stats['total_active_cost']);
        $this->assertTrue($stats['real_time_enabled']);
        $this->assertTrue($stats['auto_termination_enabled']);
        $this->assertEquals(30, $stats['grace_period']);
        $this->assertCount(1, $stats['calls_at_risk']); // Balance (1.00) < 2x cost (1.50)
    }
}