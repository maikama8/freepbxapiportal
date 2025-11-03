<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Console\Commands\MonthlyDidBilling;
use App\Services\BalanceService;
use App\Services\Email\EmailService;
use App\Models\DidNumber;
use App\Models\User;
use App\Models\CountryRate;
use App\Models\SystemSetting;
use App\Models\BalanceTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;
use Mockery;

class MonthlyDidBillingTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $mockBalanceService;
    protected $mockEmailService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockBalanceService = Mockery::mock(BalanceService::class);
        $this->mockEmailService = Mockery::mock(EmailService::class);
        
        $this->app->instance(BalanceService::class, $this->mockBalanceService);
        $this->app->instance(EmailService::class, $this->mockEmailService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_processes_monthly_did_charges_successfully()
    {
        $user = User::factory()->create([
            'account_type' => 'prepaid',
            'balance' => 50.00
        ]);

        $countryRate = CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'did_monthly_cost' => 2.50,
            'is_active' => true
        ]);

        $didNumber = DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'user_id' => $user->id,
            'status' => 'assigned',
            'monthly_cost' => 2.50,
            'setup_cost' => 5.00,
            'assigned_at' => now()->subMonth()
        ]);

        $this->mockBalanceService
            ->shouldReceive('deductBalance')
            ->once()
            ->with(
                $user,
                2.50,
                'did_monthly_charge',
                Mockery::type('string'),
                Mockery::type('array')
            )
            ->andReturn(true);

        $exitCode = Artisan::call('billing:monthly-did-charges', [
            '--month' => now()->format('Y-m')
        ]);

        $this->assertEquals(0, $exitCode);
        
        $didNumber->refresh();
        $this->assertNotNull($didNumber->billing_history);
        $this->assertNotNull($didNumber->last_billed_at);
        
        $billingHistory = json_decode($didNumber->billing_history, true);
        $this->assertCount(1, $billingHistory);
        $this->assertEquals('charged', $billingHistory[0]['status']);
        $this->assertEquals(2.50, $billingHistory[0]['amount']);
    }

    /** @test */
    public function it_suspends_dids_for_insufficient_balance()
    {
        $user = User::factory()->create([
            'account_type' => 'prepaid',
            'balance' => 1.00 // Insufficient for $2.50 charge
        ]);

        $didNumber = DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'user_id' => $user->id,
            'status' => 'assigned',
            'monthly_cost' => 2.50,
            'assigned_at' => now()->subMonth()
        ]);

        $this->mockEmailService
            ->shouldReceive('sendEmail')
            ->once()
            ->with(
                $user->email,
                'DID Number Suspended - Insufficient Balance',
                'emails.did.suspension',
                Mockery::type('array')
            )
            ->andReturn(true);

        $exitCode = Artisan::call('billing:monthly-did-charges', [
            '--month' => now()->format('Y-m'),
            '--suspend-insufficient' => true
        ]);

        $this->assertEquals(0, $exitCode);
        
        $didNumber->refresh();
        $this->assertEquals('suspended', $didNumber->status);
        $this->assertEquals('insufficient_balance', $didNumber->suspension_reason);
        $this->assertNotNull($didNumber->suspended_at);
        
        $billingHistory = json_decode($didNumber->billing_history, true);
        $this->assertEquals('suspended_insufficient_balance', $billingHistory[0]['status']);
    }

    /** @test */
    public function it_charges_overdue_when_suspension_disabled()
    {
        $user = User::factory()->create([
            'account_type' => 'prepaid',
            'balance' => 1.00 // Insufficient for $2.50 charge
        ]);

        $didNumber = DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'user_id' => $user->id,
            'status' => 'assigned',
            'monthly_cost' => 2.50,
            'assigned_at' => now()->subMonth()
        ]);

        $this->mockEmailService
            ->shouldReceive('sendEmail')
            ->once()
            ->with(
                $user->email,
                'DID Monthly Charge - Account Overdue',
                'emails.did.overdue',
                Mockery::type('array')
            )
            ->andReturn(true);

        $exitCode = Artisan::call('billing:monthly-did-charges', [
            '--month' => now()->format('Y-m')
            // No --suspend-insufficient flag
        ]);

        $this->assertEquals(0, $exitCode);
        
        $didNumber->refresh();
        $this->assertEquals('assigned', $didNumber->status); // Not suspended
        
        $user->refresh();
        $this->assertEquals(-1.50, $user->balance); // Negative balance
        
        // Check overdue transaction was created
        $overdueTransaction = BalanceTransaction::where('user_id', $user->id)
            ->where('type', 'did_monthly_charge_overdue')
            ->first();
        
        $this->assertNotNull($overdueTransaction);
        $this->assertEquals(-2.50, $overdueTransaction->amount);
        
        $billingHistory = json_decode($didNumber->billing_history, true);
        $this->assertEquals('charged_overdue', $billingHistory[0]['status']);
    }

    /** @test */
    public function it_handles_billing_lock()
    {
        // Set billing lock
        Cache::put('monthly_did_billing_lock', [
            'started_at' => now()->toISOString(),
            'pid' => 12345
        ], 7200);

        $exitCode = Artisan::call('billing:monthly-did-charges');

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('already running', Artisan::output());
    }

    /** @test */
    public function it_forces_billing_when_force_option_used()
    {
        // Set billing lock
        Cache::put('monthly_did_billing_lock', [
            'started_at' => now()->toISOString(),
            'pid' => 12345
        ], 7200);

        $exitCode = Artisan::call('billing:monthly-did-charges', ['--force' => true]);

        $this->assertEquals(0, $exitCode);
    }

    /** @test */
    public function it_skips_already_processed_month()
    {
        $currentMonth = now()->format('Y-m');
        
        // Mark current month as already processed
        SystemSetting::create([
            'key' => 'last_did_billing_month',
            'value' => $currentMonth,
            'type' => 'string'
        ]);

        $exitCode = Artisan::call('billing:monthly-did-charges', [
            '--month' => $currentMonth
        ]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('already processed', Artisan::output());
    }

    /** @test */
    public function it_processes_specific_month()
    {
        $user = User::factory()->create([
            'account_type' => 'prepaid',
            'balance' => 50.00
        ]);

        $didNumber = DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'user_id' => $user->id,
            'status' => 'assigned',
            'monthly_cost' => 2.50,
            'assigned_at' => Carbon::parse('2024-01-15') // Assigned in January
        ]);

        $this->mockBalanceService
            ->shouldReceive('deductBalance')
            ->once()
            ->andReturn(true);

        $exitCode = Artisan::call('billing:monthly-did-charges', [
            '--month' => '2024-02' // Process February
        ]);

        $this->assertEquals(0, $exitCode);
        
        $billingHistory = json_decode($didNumber->fresh()->billing_history, true);
        $this->assertEquals('2024-02', $billingHistory[0]['month']);
    }

    /** @test */
    public function it_shows_dry_run_preview()
    {
        $user = User::factory()->create([
            'account_type' => 'prepaid',
            'balance' => 50.00
        ]);

        DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'user_id' => $user->id,
            'status' => 'assigned',
            'monthly_cost' => 2.50,
            'assigned_at' => now()->subMonth()
        ]);

        $exitCode = Artisan::call('billing:monthly-did-charges', [
            '--dry-run' => true
        ]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Monthly DID Billing Preview', Artisan::output());
        $this->assertStringContainsString('Dry Run', Artisan::output());
    }

    /** @test */
    public function it_processes_batches_correctly()
    {
        $users = User::factory()->count(5)->create([
            'account_type' => 'prepaid',
            'balance' => 50.00
        ]);

        foreach ($users as $index => $user) {
            DidNumber::create([
                'did_number' => '1212555123' . $index,
                'country_code' => 'US',
                'user_id' => $user->id,
                'status' => 'assigned',
                'monthly_cost' => 2.50,
                'assigned_at' => now()->subMonth()
            ]);
        }

        $this->mockBalanceService
            ->shouldReceive('deductBalance')
            ->times(5)
            ->andReturn(true);

        $exitCode = Artisan::call('billing:monthly-did-charges', [
            '--batch-size' => 2 // Process in batches of 2
        ]);

        $this->assertEquals(0, $exitCode);
        
        // All DIDs should have billing history
        $billedDids = DidNumber::whereNotNull('billing_history')->count();
        $this->assertEquals(5, $billedDids);
    }

    /** @test */
    public function it_handles_billing_errors_gracefully()
    {
        $user = User::factory()->create([
            'account_type' => 'prepaid',
            'balance' => 50.00
        ]);

        $didNumber = DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'user_id' => $user->id,
            'status' => 'assigned',
            'monthly_cost' => 2.50,
            'assigned_at' => now()->subMonth()
        ]);

        $this->mockBalanceService
            ->shouldReceive('deductBalance')
            ->once()
            ->andThrow(new \Exception('Balance service unavailable'));

        $exitCode = Artisan::call('billing:monthly-did-charges');

        $this->assertEquals(0, $exitCode); // Should continue processing other DIDs
        
        $billingHistory = json_decode($didNumber->fresh()->billing_history, true);
        $this->assertEmpty($billingHistory); // No billing history due to error
    }

    /** @test */
    public function it_updates_system_metrics_after_billing()
    {
        $user = User::factory()->create([
            'account_type' => 'prepaid',
            'balance' => 50.00
        ]);

        DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'user_id' => $user->id,
            'status' => 'assigned',
            'monthly_cost' => 2.50,
            'assigned_at' => now()->subMonth()
        ]);

        $this->mockBalanceService
            ->shouldReceive('deductBalance')
            ->once()
            ->andReturn(true);

        $exitCode = Artisan::call('billing:monthly-did-charges');

        $this->assertEquals(0, $exitCode);
        
        // Check that metrics were updated
        $this->assertNotNull(SystemSetting::get('last_did_billing_month'));
        $this->assertNotNull(SystemSetting::get('last_did_billing_processed_at'));
        $this->assertNotNull(SystemSetting::get('last_did_billing_results'));
    }

    /** @test */
    public function it_skips_unassigned_dids()
    {
        $user = User::factory()->create([
            'account_type' => 'prepaid',
            'balance' => 50.00
        ]);

        // Create available (unassigned) DID
        DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'status' => 'available', // Not assigned
            'monthly_cost' => 2.50
        ]);

        // Create assigned DID
        DidNumber::create([
            'did_number' => '12125551235',
            'country_code' => 'US',
            'user_id' => $user->id,
            'status' => 'assigned',
            'monthly_cost' => 2.50,
            'assigned_at' => now()->subMonth()
        ]);

        $this->mockBalanceService
            ->shouldReceive('deductBalance')
            ->once() // Only called for assigned DID
            ->andReturn(true);

        $exitCode = Artisan::call('billing:monthly-did-charges');

        $this->assertEquals(0, $exitCode);
        
        // Only assigned DID should have billing history
        $billedDids = DidNumber::whereNotNull('billing_history')->count();
        $this->assertEquals(1, $billedDids);
    }

    /** @test */
    public function it_handles_invalid_month_format()
    {
        $exitCode = Artisan::call('billing:monthly-did-charges', [
            '--month' => 'invalid-format'
        ]);

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('Invalid month format', Artisan::output());
    }

    /** @test */
    public function it_sends_notifications_for_suspensions()
    {
        $user = User::factory()->create([
            'account_type' => 'prepaid',
            'balance' => 1.00
        ]);

        DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'user_id' => $user->id,
            'status' => 'assigned',
            'monthly_cost' => 2.50,
            'assigned_at' => now()->subMonth()
        ]);

        $this->mockEmailService
            ->shouldReceive('sendEmail')
            ->once()
            ->andReturn(true);

        $exitCode = Artisan::call('billing:monthly-did-charges', [
            '--suspend-insufficient' => true
        ]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Notifications sent: 1', Artisan::output());
    }
}