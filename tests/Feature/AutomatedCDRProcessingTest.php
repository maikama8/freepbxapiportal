<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Console\Commands\AutomatedCDRProcessing;
use App\Services\EnhancedCDRService;
use App\Services\FreePBX\CDRService;
use App\Models\CallRecord;
use App\Models\User;
use App\Models\CountryRate;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Mockery;

class AutomatedCDRProcessingTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $mockEnhancedCDRService;
    protected $mockCDRService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockEnhancedCDRService = Mockery::mock(EnhancedCDRService::class);
        $this->mockCDRService = Mockery::mock(CDRService::class);
        
        $this->app->instance(EnhancedCDRService::class, $this->mockEnhancedCDRService);
        $this->app->instance(CDRService::class, $this->mockCDRService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_processes_new_cdr_records_successfully()
    {
        // Mock CDR records from FreePBX
        $mockCDRRecords = [
            [
                'call_id' => 'test-call-1',
                'caller_id' => '12125551234',
                'destination' => '13105551234',
                'start_time' => now()->subMinutes(5)->toISOString(),
                'end_time' => now()->subMinutes(3)->toISOString(),
                'duration' => 120
            ],
            [
                'call_id' => 'test-call-2',
                'caller_id' => '12125551235',
                'destination' => '14155551234',
                'start_time' => now()->subMinutes(8)->toISOString(),
                'end_time' => now()->subMinutes(6)->toISOString(),
                'duration' => 90
            ]
        ];

        $this->mockCDRService
            ->shouldReceive('getCDRRecords')
            ->once()
            ->andReturn($mockCDRRecords);

        $this->mockEnhancedCDRService
            ->shouldReceive('processEnhancedCDR')
            ->once()
            ->with($mockCDRRecords)
            ->andReturn([
                'success' => true,
                'processed' => 2,
                'failed' => 0
            ]);

        $exitCode = Artisan::call('cdr:automated-processing', [
            '--batch-size' => 50,
            '--timeout' => 300
        ]);

        $this->assertEquals(0, $exitCode);
        
        // Check that system settings were updated
        $this->assertNotNull(SystemSetting::get('last_cdr_processing_at'));
    }

    /** @test */
    public function it_processes_unprocessed_records()
    {
        // Create unprocessed call records
        $user = User::factory()->create(['account_type' => 'prepaid', 'balance' => 10.00]);
        
        CallRecord::create([
            'user_id' => $user->id,
            'call_id' => 'unprocessed-call-1',
            'destination' => '12125551234',
            'start_time' => now()->subHour(),
            'end_time' => now()->subHour()->addMinutes(2),
            'status' => 'completed',
            'billing_status' => 'pending'
        ]);

        $this->mockCDRService
            ->shouldReceive('getCDRRecords')
            ->once()
            ->andReturn([]);

        $this->mockEnhancedCDRService
            ->shouldReceive('processUnprocessedCDRs')
            ->once()
            ->with(50)
            ->andReturn([
                'success' => true,
                'processed' => 1,
                'failed' => 0
            ]);

        $exitCode = Artisan::call('cdr:automated-processing', [
            '--batch-size' => 50
        ]);

        $this->assertEquals(0, $exitCode);
    }

    /** @test */
    public function it_retries_failed_records()
    {
        $user = User::factory()->create(['account_type' => 'prepaid', 'balance' => 10.00]);
        
        // Create failed call record
        $failedRecord = CallRecord::create([
            'user_id' => $user->id,
            'call_id' => 'failed-call-1',
            'destination' => '12125551234',
            'start_time' => now()->subHour(),
            'end_time' => now()->subHour()->addMinutes(2),
            'status' => 'completed',
            'billing_status' => 'failed',
            'retry_count' => 1,
            'updated_at' => now()->subMinutes(10) // Eligible for retry
        ]);

        CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'call_rate_per_minute' => 0.05,
            'billing_increment' => 6,
            'is_active' => true
        ]);

        $this->mockCDRService
            ->shouldReceive('getCDRRecords')
            ->once()
            ->andReturn([]);

        $this->mockEnhancedCDRService
            ->shouldReceive('processUnprocessedCDRs')
            ->once()
            ->andReturn([
                'success' => true,
                'processed' => 0,
                'failed' => 0
            ]);

        $this->mockEnhancedCDRService
            ->shouldReceive('enhanceCallRecord')
            ->once()
            ->with($failedRecord, [])
            ->andReturn(['enhanced' => true]);

        $this->mockEnhancedCDRService
            ->shouldReceive('processCallBilling')
            ->once()
            ->andReturn([
                'success' => true,
                'cost' => 0.10
            ]);

        $exitCode = Artisan::call('cdr:automated-processing', [
            '--max-retries' => 3
        ]);

        $this->assertEquals(0, $exitCode);
        
        $failedRecord->refresh();
        $this->assertEquals(2, $failedRecord->retry_count);
    }

    /** @test */
    public function it_handles_processing_lock()
    {
        // Set processing lock
        Cache::put('cdr_processing_lock', [
            'started_at' => now()->toISOString(),
            'pid' => 12345
        ], 3600);

        $exitCode = Artisan::call('cdr:automated-processing');

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('already running', Artisan::output());
    }

    /** @test */
    public function it_forces_processing_when_force_option_used()
    {
        // Set processing lock
        Cache::put('cdr_processing_lock', [
            'started_at' => now()->toISOString(),
            'pid' => 12345
        ], 3600);

        $this->mockCDRService
            ->shouldReceive('getCDRRecords')
            ->once()
            ->andReturn([]);

        $this->mockEnhancedCDRService
            ->shouldReceive('processUnprocessedCDRs')
            ->once()
            ->andReturn([
                'success' => true,
                'processed' => 0,
                'failed' => 0
            ]);

        $exitCode = Artisan::call('cdr:automated-processing', ['--force' => true]);

        $this->assertEquals(0, $exitCode);
    }

    /** @test */
    public function it_handles_processing_errors_gracefully()
    {
        $this->mockCDRService
            ->shouldReceive('getCDRRecords')
            ->once()
            ->andThrow(new \Exception('CDR service unavailable'));

        $exitCode = Artisan::call('cdr:automated-processing');

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('CDR processing failed', Artisan::output());
    }

    /** @test */
    public function it_marks_records_as_permanently_failed_after_max_retries()
    {
        $user = User::factory()->create(['account_type' => 'prepaid', 'balance' => 10.00]);
        
        // Create failed call record with max retries reached
        $failedRecord = CallRecord::create([
            'user_id' => $user->id,
            'call_id' => 'max-retry-call',
            'destination' => '12125551234',
            'start_time' => now()->subHour(),
            'end_time' => now()->subHour()->addMinutes(2),
            'status' => 'completed',
            'billing_status' => 'failed',
            'retry_count' => 2, // Will reach max of 3
            'updated_at' => now()->subMinutes(10)
        ]);

        $this->mockCDRService
            ->shouldReceive('getCDRRecords')
            ->once()
            ->andReturn([]);

        $this->mockEnhancedCDRService
            ->shouldReceive('processUnprocessedCDRs')
            ->once()
            ->andReturn([
                'success' => true,
                'processed' => 0,
                'failed' => 0
            ]);

        $this->mockEnhancedCDRService
            ->shouldReceive('enhanceCallRecord')
            ->once()
            ->andThrow(new \Exception('Permanent processing error'));

        $exitCode = Artisan::call('cdr:automated-processing', [
            '--max-retries' => 3
        ]);

        $this->assertEquals(0, $exitCode);
        
        $failedRecord->refresh();
        $this->assertEquals('permanently_failed', $failedRecord->billing_status);
        $this->assertEquals(3, $failedRecord->retry_count);
    }

    /** @test */
    public function it_processes_zero_duration_calls_correctly()
    {
        $user = User::factory()->create(['account_type' => 'prepaid', 'balance' => 10.00]);
        
        $zeroDurationRecord = CallRecord::create([
            'user_id' => $user->id,
            'call_id' => 'zero-duration-call',
            'destination' => '12125551234',
            'start_time' => now()->subHour(),
            'end_time' => now()->subHour(), // Same time = zero duration
            'status' => 'completed',
            'billing_status' => 'failed',
            'updated_at' => now()->subMinutes(10)
        ]);

        $this->mockCDRService
            ->shouldReceive('getCDRRecords')
            ->once()
            ->andReturn([]);

        $this->mockEnhancedCDRService
            ->shouldReceive('processUnprocessedCDRs')
            ->once()
            ->andReturn([
                'success' => true,
                'processed' => 0,
                'failed' => 0
            ]);

        $exitCode = Artisan::call('cdr:automated-processing');

        $this->assertEquals(0, $exitCode);
        
        $zeroDurationRecord->refresh();
        $this->assertEquals(0, $zeroDurationRecord->cost);
        $this->assertEquals('no_billing_required', $zeroDurationRecord->billing_status);
    }

    /** @test */
    public function it_updates_system_metrics_after_processing()
    {
        $this->mockCDRService
            ->shouldReceive('getCDRRecords')
            ->once()
            ->andReturn([]);

        $this->mockEnhancedCDRService
            ->shouldReceive('processUnprocessedCDRs')
            ->once()
            ->andReturn([
                'success' => true,
                'processed' => 5,
                'failed' => 1
            ]);

        $this->mockEnhancedCDRService
            ->shouldReceive('getCDRProcessingStats')
            ->once()
            ->andReturn([
                'total_processed_today' => 10,
                'success_rate' => 95.5
            ]);

        $exitCode = Artisan::call('cdr:automated-processing');

        $this->assertEquals(0, $exitCode);
        
        // Check that metrics were updated
        $this->assertNotNull(SystemSetting::get('last_cdr_processing_at'));
        $this->assertNotNull(SystemSetting::get('last_cdr_processing_duration'));
        $this->assertNotNull(SystemSetting::get('cdr_processing_stats'));
    }
}