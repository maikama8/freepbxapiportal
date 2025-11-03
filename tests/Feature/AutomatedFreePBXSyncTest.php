<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Console\Commands\AutomatedFreePBXSync;
use App\Services\FreePBX\ExtensionService;
use App\Services\FreePBX\FreePBXApiClient;
use App\Models\SipAccount;
use App\Models\User;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Mockery;

class AutomatedFreePBXSyncTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $mockExtensionService;
    protected $mockApiClient;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockExtensionService = Mockery::mock(ExtensionService::class);
        $this->mockApiClient = Mockery::mock(FreePBXApiClient::class);
        
        $this->app->instance(ExtensionService::class, $this->mockExtensionService);
        $this->app->instance(FreePBXApiClient::class, $this->mockApiClient);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_performs_incremental_sync_successfully()
    {
        $user = User::factory()->create();
        
        $sipAccount = SipAccount::create([
            'user_id' => $user->id,
            'sip_username' => '1001',
            'sip_password' => 'password123',
            'freepbx_sync_status' => 'pending'
        ]);

        $this->mockExtensionService
            ->shouldReceive('testConnection')
            ->once()
            ->andReturn(true);

        $this->mockExtensionService
            ->shouldReceive('extensionExists')
            ->once()
            ->with('1001')
            ->andReturn(false);

        $this->mockExtensionService
            ->shouldReceive('createExtension')
            ->once()
            ->with($user, '1001', 'password123')
            ->andReturn(true);

        $exitCode = Artisan::call('freepbx:automated-sync', [
            '--sync-mode' => 'incremental',
            '--batch-size' => 20
        ]);

        $this->assertEquals(0, $exitCode);
        
        $sipAccount->refresh();
        $this->assertEquals('synced', $sipAccount->freepbx_sync_status);
        $this->assertNotNull($sipAccount->freepbx_last_sync_at);
    }

    /** @test */
    public function it_performs_full_sync_successfully()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $sipAccount1 = SipAccount::create([
            'user_id' => $user1->id,
            'sip_username' => '1001',
            'sip_password' => 'password123',
            'freepbx_sync_status' => 'synced'
        ]);

        $sipAccount2 = SipAccount::create([
            'user_id' => $user2->id,
            'sip_username' => '1002',
            'sip_password' => 'password456',
            'freepbx_sync_status' => 'pending'
        ]);

        $this->mockExtensionService
            ->shouldReceive('testConnection')
            ->once()
            ->andReturn(true);

        $this->mockExtensionService
            ->shouldReceive('extensionExists')
            ->twice()
            ->andReturn(true, false);

        $this->mockExtensionService
            ->shouldReceive('updateExtension')
            ->once()
            ->with('1001', [
                'name' => $user1->name,
                'email' => $user1->email
            ])
            ->andReturn(true);

        $this->mockExtensionService
            ->shouldReceive('createExtension')
            ->once()
            ->with($user2, '1002', 'password456')
            ->andReturn(true);

        $exitCode = Artisan::call('freepbx:automated-sync', [
            '--sync-mode' => 'full',
            '--batch-size' => 20
        ]);

        $this->assertEquals(0, $exitCode);
        
        $sipAccount1->refresh();
        $sipAccount2->refresh();
        
        $this->assertEquals('synced', $sipAccount1->freepbx_sync_status);
        $this->assertEquals('synced', $sipAccount2->freepbx_sync_status);
    }

    /** @test */
    public function it_performs_bidirectional_sync()
    {
        $user = User::factory()->create();
        
        $sipAccount = SipAccount::create([
            'user_id' => $user->id,
            'sip_username' => '1001',
            'sip_password' => 'password123',
            'freepbx_sync_status' => 'pending'
        ]);

        // Mock FreePBX extensions
        $freepbxExtensions = [
            [
                'extension' => '1001',
                'name' => $user->name,
                'email' => $user->email
            ],
            [
                'extension' => '1002',
                'name' => 'External User',
                'email' => 'external@example.com'
            ]
        ];

        $this->mockExtensionService
            ->shouldReceive('testConnection')
            ->once()
            ->andReturn(true);

        $this->mockExtensionService
            ->shouldReceive('extensionExists')
            ->once()
            ->with('1001')
            ->andReturn(true);

        $this->mockExtensionService
            ->shouldReceive('updateExtension')
            ->once()
            ->andReturn(true);

        $this->mockExtensionService
            ->shouldReceive('getAllExtensions')
            ->once()
            ->andReturn($freepbxExtensions);

        $exitCode = Artisan::call('freepbx:automated-sync', [
            '--sync-mode' => 'bidirectional'
        ]);

        $this->assertEquals(0, $exitCode);
        
        $sipAccount->refresh();
        $this->assertEquals('synced', $sipAccount->freepbx_sync_status);
        $this->assertNotNull($sipAccount->freepbx_extension_data);
    }

    /** @test */
    public function it_handles_connection_failure()
    {
        $this->mockExtensionService
            ->shouldReceive('testConnection')
            ->once()
            ->andReturn(false);

        $exitCode = Artisan::call('freepbx:automated-sync');

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('connection test failed', Artisan::output());
    }

    /** @test */
    public function it_handles_sync_lock()
    {
        // Set sync lock
        Cache::put('freepbx_sync_lock', [
            'started_at' => now()->toISOString(),
            'pid' => 12345
        ], 3600);

        $exitCode = Artisan::call('freepbx:automated-sync');

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('already running', Artisan::output());
    }

    /** @test */
    public function it_forces_sync_when_force_option_used()
    {
        // Set sync lock
        Cache::put('freepbx_sync_lock', [
            'started_at' => now()->toISOString(),
            'pid' => 12345
        ], 3600);

        $this->mockExtensionService
            ->shouldReceive('testConnection')
            ->once()
            ->andReturn(true);

        $exitCode = Artisan::call('freepbx:automated-sync', ['--force' => true]);

        $this->assertEquals(0, $exitCode);
    }

    /** @test */
    public function it_retries_failed_syncs()
    {
        $user = User::factory()->create();
        
        $sipAccount = SipAccount::create([
            'user_id' => $user->id,
            'sip_username' => '1001',
            'sip_password' => 'password123',
            'freepbx_sync_status' => 'pending'
        ]);

        $this->mockExtensionService
            ->shouldReceive('testConnection')
            ->once()
            ->andReturn(true);

        $this->mockExtensionService
            ->shouldReceive('extensionExists')
            ->times(3) // Will retry 3 times
            ->with('1001')
            ->andThrow(new \Exception('Temporary failure'), new \Exception('Temporary failure'))
            ->andReturn(false);

        $this->mockExtensionService
            ->shouldReceive('createExtension')
            ->once()
            ->andReturn(true);

        $exitCode = Artisan::call('freepbx:automated-sync', [
            '--max-retries' => 3
        ]);

        $this->assertEquals(0, $exitCode);
        
        $sipAccount->refresh();
        $this->assertEquals('synced', $sipAccount->freepbx_sync_status);
    }

    /** @test */
    public function it_marks_as_failed_after_max_retries()
    {
        $user = User::factory()->create();
        
        $sipAccount = SipAccount::create([
            'user_id' => $user->id,
            'sip_username' => '1001',
            'sip_password' => 'password123',
            'freepbx_sync_status' => 'pending'
        ]);

        $this->mockExtensionService
            ->shouldReceive('testConnection')
            ->once()
            ->andReturn(true);

        $this->mockExtensionService
            ->shouldReceive('extensionExists')
            ->times(4) // Initial + 3 retries
            ->with('1001')
            ->andThrow(new \Exception('Permanent failure'));

        $exitCode = Artisan::call('freepbx:automated-sync', [
            '--max-retries' => 3
        ]);

        $this->assertEquals(0, $exitCode);
        
        $sipAccount->refresh();
        $this->assertEquals('failed', $sipAccount->freepbx_sync_status);
        $this->assertEquals(4, $sipAccount->sync_retry_count);
        $this->assertNotNull($sipAccount->sync_last_error);
    }

    /** @test */
    public function it_shows_dry_run_preview()
    {
        $user = User::factory()->create();
        
        SipAccount::create([
            'user_id' => $user->id,
            'sip_username' => '1001',
            'sip_password' => 'password123',
            'freepbx_sync_status' => 'pending'
        ]);

        $this->mockExtensionService
            ->shouldReceive('testConnection')
            ->once()
            ->andReturn(true);

        $this->mockExtensionService
            ->shouldReceive('extensionExists')
            ->once()
            ->with('1001')
            ->andReturn(false);

        $exitCode = Artisan::call('freepbx:automated-sync', [
            '--dry-run' => true
        ]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Sync Preview', Artisan::output());
        $this->assertStringContainsString('Dry Run', Artisan::output());
    }

    /** @test */
    public function it_updates_system_metrics_after_sync()
    {
        $user = User::factory()->create();
        
        SipAccount::create([
            'user_id' => $user->id,
            'sip_username' => '1001',
            'sip_password' => 'password123',
            'freepbx_sync_status' => 'pending'
        ]);

        $this->mockExtensionService
            ->shouldReceive('testConnection')
            ->once()
            ->andReturn(true);

        $this->mockExtensionService
            ->shouldReceive('extensionExists')
            ->once()
            ->andReturn(false);

        $this->mockExtensionService
            ->shouldReceive('createExtension')
            ->once()
            ->andReturn(true);

        $exitCode = Artisan::call('freepbx:automated-sync');

        $this->assertEquals(0, $exitCode);
        
        // Check that metrics were updated
        $this->assertNotNull(SystemSetting::get('last_freepbx_sync_at'));
        $this->assertNotNull(SystemSetting::get('last_freepbx_sync_mode'));
        $this->assertNotNull(SystemSetting::get('freepbx_sync_stats'));
    }

    /** @test */
    public function it_processes_batches_correctly()
    {
        $users = User::factory()->count(5)->create();
        
        foreach ($users as $index => $user) {
            SipAccount::create([
                'user_id' => $user->id,
                'sip_username' => '100' . ($index + 1),
                'sip_password' => 'password' . ($index + 1),
                'freepbx_sync_status' => 'pending'
            ]);
        }

        $this->mockExtensionService
            ->shouldReceive('testConnection')
            ->once()
            ->andReturn(true);

        $this->mockExtensionService
            ->shouldReceive('extensionExists')
            ->times(5)
            ->andReturn(false);

        $this->mockExtensionService
            ->shouldReceive('createExtension')
            ->times(5)
            ->andReturn(true);

        $exitCode = Artisan::call('freepbx:automated-sync', [
            '--batch-size' => 2 // Process in batches of 2
        ]);

        $this->assertEquals(0, $exitCode);
        
        // All accounts should be synced
        $syncedCount = SipAccount::where('freepbx_sync_status', 'synced')->count();
        $this->assertEquals(5, $syncedCount);
    }

    /** @test */
    public function it_handles_sync_errors_gracefully()
    {
        $this->mockExtensionService
            ->shouldReceive('testConnection')
            ->once()
            ->andThrow(new \Exception('FreePBX service unavailable'));

        $exitCode = Artisan::call('freepbx:automated-sync');

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('FreePBX sync failed', Artisan::output());
    }

    /** @test */
    public function it_skips_sync_when_no_accounts_need_syncing()
    {
        // Create already synced account
        $user = User::factory()->create();
        
        SipAccount::create([
            'user_id' => $user->id,
            'sip_username' => '1001',
            'sip_password' => 'password123',
            'freepbx_sync_status' => 'synced',
            'freepbx_last_sync_at' => now()
        ]);

        // Set last sync time to recent
        SystemSetting::create([
            'key' => 'last_freepbx_sync_at',
            'value' => now()->subMinutes(5)->toISOString(),
            'type' => 'string'
        ]);

        $this->mockExtensionService
            ->shouldReceive('testConnection')
            ->once()
            ->andReturn(true);

        $exitCode = Artisan::call('freepbx:automated-sync');

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('No SIP accounts need synchronization', Artisan::output());
    }
}