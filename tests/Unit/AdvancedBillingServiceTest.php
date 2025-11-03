<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\AdvancedBillingService;
use App\Models\CallRate;
use App\Models\CountryRate;
use App\Models\CallRecord;
use App\Models\User;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class AdvancedBillingServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected AdvancedBillingService $billingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->billingService = new AdvancedBillingService();
    }

    /** @test */
    public function it_returns_available_billing_increments()
    {
        $increments = $this->billingService->getAvailableBillingIncrements();
        
        $this->assertIsArray($increments);
        $this->assertArrayHasKey('1/1', $increments);
        $this->assertArrayHasKey('6/6', $increments);
        $this->assertArrayHasKey('30/30', $increments);
        $this->assertArrayHasKey('60/60', $increments);
        
        // Check structure of increment data
        $this->assertArrayHasKey('initial', $increments['1/1']);
        $this->assertArrayHasKey('subsequent', $increments['1/1']);
        $this->assertArrayHasKey('label', $increments['1/1']);
    }

    /** @test */
    public function it_calculates_billable_duration_with_1_1_increment()
    {
        $billingConfig = ['initial' => 1, 'subsequent' => 1];
        
        // Test exact increment
        $this->assertEquals(30, $this->billingService->calculateBillableDuration(30, $billingConfig));
        
        // Test partial increment (should round up)
        $this->assertEquals(31, $this->billingService->calculateBillableDuration(31, $billingConfig));
        
        // Test zero duration
        $this->assertEquals(0, $this->billingService->calculateBillableDuration(0, $billingConfig));
        
        // Test with minimum duration
        $this->assertEquals(10, $this->billingService->calculateBillableDuration(5, $billingConfig, 10));
    }

    /** @test */
    public function it_calculates_billable_duration_with_6_6_increment()
    {
        $billingConfig = ['initial' => 6, 'subsequent' => 6];
        
        // Test duration less than initial increment
        $this->assertEquals(6, $this->billingService->calculateBillableDuration(3, $billingConfig));
        
        // Test duration equal to initial increment
        $this->assertEquals(6, $this->billingService->calculateBillableDuration(6, $billingConfig));
        
        // Test duration requiring subsequent increments
        $this->assertEquals(12, $this->billingService->calculateBillableDuration(7, $billingConfig));
        $this->assertEquals(12, $this->billingService->calculateBillableDuration(12, $billingConfig));
        $this->assertEquals(18, $this->billingService->calculateBillableDuration(13, $billingConfig));
    }

    /** @test */
    public function it_calculates_billable_duration_with_mixed_increment()
    {
        $billingConfig = ['initial' => 6, 'subsequent' => 60];
        
        // Test duration less than initial increment
        $this->assertEquals(6, $this->billingService->calculateBillableDuration(3, $billingConfig));
        
        // Test duration equal to initial increment
        $this->assertEquals(6, $this->billingService->calculateBillableDuration(6, $billingConfig));
        
        // Test duration requiring one subsequent increment
        $this->assertEquals(66, $this->billingService->calculateBillableDuration(7, $billingConfig));
        $this->assertEquals(66, $this->billingService->calculateBillableDuration(66, $billingConfig));
        
        // Test duration requiring multiple subsequent increments
        $this->assertEquals(126, $this->billingService->calculateBillableDuration(67, $billingConfig));
        $this->assertEquals(126, $this->billingService->calculateBillableDuration(126, $billingConfig));
        $this->assertEquals(186, $this->billingService->calculateBillableDuration(127, $billingConfig));
    }

    /** @test */
    public function it_parses_billing_increment_strings()
    {
        $reflection = new \ReflectionClass($this->billingService);
        $method = $reflection->getMethod('parseBillingIncrement');
        $method->setAccessible(true);
        
        // Test predefined increment
        $result = $method->invoke($this->billingService, '6/6');
        $this->assertEquals(['initial' => 6, 'subsequent' => 6, 'label' => '6 seconds / 6 seconds'], $result);
        
        // Test custom format
        $result = $method->invoke($this->billingService, '15/30');
        $this->assertEquals(['initial' => 15, 'subsequent' => 30, 'label' => '15 seconds / 30 seconds'], $result);
        
        // Test invalid format (should fall back to default)
        SystemSetting::create([
            'key' => 'billing.default_increment',
            'value' => '6/6',
            'type' => 'string',
            'group' => 'billing',
            'label' => 'Default Billing Increment',
            'description' => 'Default billing increment for new rates'
        ]);
        
        $result = $method->invoke($this->billingService, 'invalid');
        $this->assertEquals(['initial' => 6, 'subsequent' => 6, 'label' => '6 seconds / 6 seconds'], $result);
    }

    /** @test */
    public function it_calculates_advanced_call_cost_with_call_rate()
    {
        $callRate = CallRate::create([
            'destination_prefix' => '1',
            'destination_name' => 'USA',
            'rate_per_minute' => 0.05,
            'minimum_duration' => 6,
            'billing_increment_config' => '6/6',
            'effective_date' => now()
        ]);
        
        $result = $this->billingService->calculateAdvancedCallCost('12345678901', 65);
        
        $this->assertArrayHasKey('cost', $result);
        $this->assertArrayHasKey('rate', $result);
        $this->assertArrayHasKey('billable_duration', $result);
        $this->assertEquals('call_rate', $result['rate_source']);
        $this->assertEquals(66, $result['billable_duration']); // 6 initial + 60 subsequent (59s rounded up to 60s)
        $this->assertEquals(0.055, $result['cost']); // 66 seconds = 1.1 minutes * 0.05 = 0.055
    }

    /** @test */
    public function it_calculates_advanced_call_cost_with_country_rate()
    {
        $countryRate = CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'call_rate_per_minute' => 0.03,
            'billing_increment' => 30,
            'minimum_duration' => 0,
            'is_active' => true
        ]);
        
        $result = $this->billingService->calculateAdvancedCallCost('12345678901', 45);
        
        $this->assertArrayHasKey('cost', $result);
        $this->assertArrayHasKey('country_rate', $result);
        $this->assertEquals('country_rate', $result['rate_source']);
        $this->assertEquals(60, $result['billable_duration']); // 30 initial + 30 subsequent (15s rounded up to 30s)
        $this->assertEquals(0.03, $result['cost']); // 60 seconds = 1 minute * 0.03 = 0.03
    }

    /** @test */
    public function it_processes_advanced_call_billing_for_prepaid_user()
    {
        $user = User::factory()->create([
            'account_type' => 'prepaid',
            'balance' => 10.00
        ]);
        
        $callRecord = CallRecord::create([
            'user_id' => $user->id,
            'call_id' => 'test-call-123',
            'destination' => '12345678901',
            'start_time' => now()->subMinutes(2),
            'end_time' => now()->subMinutes(1),
            'status' => 'completed'
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
        
        $result = $this->billingService->processAdvancedCallBilling($callRecord);
        
        $this->assertTrue($result);
        $callRecord->refresh();
        $this->assertNotNull($callRecord->cost);
        $this->assertEquals('paid', $callRecord->billing_status);
        
        $user->refresh();
        $this->assertLessThan(10.00, $user->balance);
    }

    /** @test */
    public function it_handles_zero_duration_calls()
    {
        $user = User::factory()->create(['account_type' => 'prepaid']);
        
        $callRecord = CallRecord::create([
            'user_id' => $user->id,
            'call_id' => 'test-call-zero',
            'destination' => '12345678901',
            'start_time' => now(),
            'end_time' => now(),
            'status' => 'completed'
        ]);
        
        $result = $this->billingService->processAdvancedCallBilling($callRecord);
        
        $this->assertTrue($result);
        $callRecord->refresh();
        $this->assertEquals(0, $callRecord->cost);
        $this->assertEquals('completed', $callRecord->billing_status);
    }

    /** @test */
    public function it_handles_postpaid_billing()
    {
        $user = User::factory()->create([
            'account_type' => 'postpaid',
            'credit_limit' => 100.00
        ]);
        
        $callRecord = CallRecord::create([
            'user_id' => $user->id,
            'call_id' => 'test-call-postpaid',
            'destination' => '12345678901',
            'start_time' => now()->subMinutes(2),
            'end_time' => now()->subMinutes(1),
            'status' => 'completed'
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
        
        $result = $this->billingService->processAdvancedCallBilling($callRecord);
        
        $this->assertTrue($result);
        $callRecord->refresh();
        $this->assertNotNull($callRecord->cost);
        $this->assertEquals('unpaid', $callRecord->billing_status);
    }

    /** @test */
    public function it_gets_billing_configuration()
    {
        SystemSetting::create([
            'key' => 'billing.default_increment',
            'value' => '6/6',
            'type' => 'string',
            'group' => 'billing',
            'label' => 'Default Billing Increment',
            'description' => 'Default billing increment for new rates'
        ]);
        
        $config = $this->billingService->getBillingConfiguration();
        
        $this->assertArrayHasKey('default_increment', $config);
        $this->assertArrayHasKey('available_increments', $config);
        $this->assertArrayHasKey('country_specific_rates', $config);
        $this->assertArrayHasKey('call_specific_rates', $config);
        $this->assertEquals('6/6', $config['default_increment']);
    }

    /** @test */
    public function it_updates_billing_configuration()
    {
        $config = [
            'default_increment' => '30/30',
            'billing.enable_real_time' => true,
            'billing.grace_period_seconds' => 60
        ];
        
        $this->billingService->updateBillingConfiguration($config);
        
        $this->assertEquals('30/30', $this->billingService->getDefaultBillingIncrement());
        $this->assertEquals(true, SystemSetting::get('billing.enable_real_time'));
        $this->assertEquals(60, SystemSetting::get('billing.grace_period_seconds'));
    }

    /** @test */
    public function it_throws_exception_for_invalid_increment()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid billing increment: invalid/format');
        
        $this->billingService->setDefaultBillingIncrement('invalid/format');
    }
}