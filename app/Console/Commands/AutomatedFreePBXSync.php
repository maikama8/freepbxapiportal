<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SipAccount;
use App\Models\User;
use App\Models\SystemSetting;
use App\Services\FreePBX\ExtensionService;
use App\Services\FreePBX\FreePBXApiClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AutomatedFreePBXSync extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'freepbx:automated-sync 
                            {--batch-size=20 : Number of extensions to process in each batch}
                            {--sync-mode=incremental : Sync mode (incremental, full, bidirectional)}
                            {--max-retries=3 : Maximum number of retry attempts for failed syncs}
                            {--timeout=600 : Maximum execution time in seconds}
                            {--force : Force sync even if another instance is running}
                            {--dry-run : Show what would be synced without taking action}';

    /**
     * The console command description.
     */
    protected $description = 'Automated FreePBX extension synchronization with error handling and monitoring';

    protected $extensionService;
    protected $apiClient;
    protected $startTime;
    protected $lockKey = 'freepbx_sync_lock';

    public function __construct(
        ExtensionService $extensionService,
        FreePBXApiClient $apiClient
    ) {
        parent::__construct();
        $this->extensionService = $extensionService;
        $this->apiClient = $apiClient;
        $this->startTime = now();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Check if sync is already running
        if (!$this->acquireLock()) {
            $this->warn('FreePBX sync is already running. Use --force to override.');
            return 1;
        }

        try {
            $this->info('Starting automated FreePBX synchronization...');
            
            // Set execution timeout
            $timeout = (int) $this->option('timeout');
            set_time_limit($timeout);

            // Test FreePBX connection first
            if (!$this->testFreePBXConnection()) {
                $this->error('FreePBX connection test failed. Aborting sync.');
                return 1;
            }

            // Determine sync mode and execute
            $syncMode = $this->option('sync-mode');
            $dryRun = $this->option('dry-run');

            $results = match ($syncMode) {
                'full' => $this->performFullSync($dryRun),
                'bidirectional' => $this->performBidirectionalSync($dryRun),
                default => $this->performIncrementalSync($dryRun)
            };

            // Log sync summary
            $this->logSyncSummary($results, $syncMode);

            // Update system metrics
            $this->updateSyncMetrics($results, $syncMode);

            $this->info('Automated FreePBX synchronization completed successfully.');
            return 0;

        } catch (\Exception $e) {
            $this->error('FreePBX sync failed: ' . $e->getMessage());
            Log::error('Automated FreePBX sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Acquire sync lock
     */
    protected function acquireLock(): bool
    {
        if ($this->option('force')) {
            Cache::forget($this->lockKey);
        }

        return Cache::add($this->lockKey, [
            'started_at' => now()->toISOString(),
            'pid' => getmypid(),
            'command' => $this->signature
        ], 3600); // 1 hour lock
    }

    /**
     * Release sync lock
     */
    protected function releaseLock(): void
    {
        Cache::forget($this->lockKey);
    }

    /**
     * Test FreePBX connection
     */
    protected function testFreePBXConnection(): bool
    {
        try {
            $this->info('Testing FreePBX connection...');
            $connected = $this->extensionService->testConnection();
            
            if ($connected) {
                $this->info('✓ FreePBX connection successful');
                return true;
            } else {
                $this->error('✗ FreePBX connection failed');
                return false;
            }
        } catch (\Exception $e) {
            $this->error('✗ FreePBX connection test error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Perform incremental sync (only sync new/modified extensions)
     */
    protected function performIncrementalSync(bool $dryRun = false): array
    {
        $this->info('Performing incremental synchronization...');
        
        try {
            $batchSize = (int) $this->option('batch-size');
            $lastSyncTime = SystemSetting::get('last_freepbx_sync_at');
            
            // Get SIP accounts that need syncing
            $query = SipAccount::with('user')
                ->where(function ($q) use ($lastSyncTime) {
                    if ($lastSyncTime) {
                        $q->where('updated_at', '>', $lastSyncTime)
                          ->orWhere('freepbx_sync_status', '!=', 'synced')
                          ->orWhereNull('freepbx_sync_status');
                    } else {
                        // First sync - sync all
                        $q->whereNull('freepbx_sync_status')
                          ->orWhere('freepbx_sync_status', '!=', 'synced');
                    }
                });

            $sipAccounts = $query->limit($batchSize * 2)->get();

            if ($sipAccounts->isEmpty()) {
                $this->info('No SIP accounts need synchronization.');
                return [
                    'created' => 0,
                    'updated' => 0,
                    'failed' => 0,
                    'skipped' => 0,
                    'total' => 0
                ];
            }

            $this->info('Found ' . $sipAccounts->count() . ' SIP accounts to sync');

            if ($dryRun) {
                $this->displaySyncPreview($sipAccounts);
                return [
                    'created' => 0,
                    'updated' => 0,
                    'failed' => 0,
                    'skipped' => 0,
                    'total' => $sipAccounts->count(),
                    'dry_run' => true
                ];
            }

            // Process in batches
            return $this->processSipAccountBatches($sipAccounts, $batchSize);

        } catch (\Exception $e) {
            Log::error('Incremental sync failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'created' => 0,
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'total' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Perform full sync (sync all extensions)
     */
    protected function performFullSync(bool $dryRun = false): array
    {
        $this->info('Performing full synchronization...');
        
        try {
            $batchSize = (int) $this->option('batch-size');
            $sipAccounts = SipAccount::with('user')->get();

            if ($sipAccounts->isEmpty()) {
                $this->info('No SIP accounts found to sync.');
                return [
                    'created' => 0,
                    'updated' => 0,
                    'failed' => 0,
                    'skipped' => 0,
                    'total' => 0
                ];
            }

            $this->info('Found ' . $sipAccounts->count() . ' SIP accounts for full sync');

            if ($dryRun) {
                $this->displaySyncPreview($sipAccounts);
                return [
                    'created' => 0,
                    'updated' => 0,
                    'failed' => 0,
                    'skipped' => 0,
                    'total' => $sipAccounts->count(),
                    'dry_run' => true
                ];
            }

            // Reset sync status for all accounts
            SipAccount::query()->update(['freepbx_sync_status' => 'pending']);

            return $this->processSipAccountBatches($sipAccounts, $batchSize);

        } catch (\Exception $e) {
            Log::error('Full sync failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'created' => 0,
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'total' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Perform bidirectional sync (sync both ways)
     */
    protected function performBidirectionalSync(bool $dryRun = false): array
    {
        $this->info('Performing bidirectional synchronization...');
        
        try {
            // First, sync from Laravel to FreePBX
            $laravelToFreepbx = $this->performIncrementalSync($dryRun);
            
            if ($dryRun) {
                return $laravelToFreepbx;
            }

            // Then, sync from FreePBX to Laravel
            $freepbxToLaravel = $this->syncFromFreePBXToLaravel();

            return [
                'created' => $laravelToFreepbx['created'],
                'updated' => $laravelToFreepbx['updated'] + $freepbxToLaravel['updated'],
                'failed' => $laravelToFreepbx['failed'] + $freepbxToLaravel['failed'],
                'skipped' => $laravelToFreepbx['skipped'],
                'total' => $laravelToFreepbx['total'],
                'freepbx_extensions_found' => $freepbxToLaravel['total'],
                'bidirectional' => true
            ];

        } catch (\Exception $e) {
            Log::error('Bidirectional sync failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'created' => 0,
                'updated' => 0,
                'failed' => 0,
                'skipped' => 0,
                'total' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Sync extensions from FreePBX to Laravel
     */
    protected function syncFromFreePBXToLaravel(): array
    {
        $this->info('Syncing extensions from FreePBX to Laravel...');
        
        try {
            $freepbxExtensions = $this->extensionService->getAllExtensions();
            $updated = 0;
            $failed = 0;

            foreach ($freepbxExtensions as $extension) {
                try {
                    $extensionNumber = $extension['extension'] ?? $extension['id'] ?? null;
                    
                    if (!$extensionNumber) {
                        continue;
                    }

                    // Find corresponding SIP account
                    $sipAccount = SipAccount::where('sip_username', $extensionNumber)->first();
                    
                    if ($sipAccount) {
                        // Update sync status and last sync time
                        $sipAccount->update([
                            'freepbx_sync_status' => 'synced',
                            'freepbx_last_sync_at' => now(),
                            'freepbx_extension_data' => json_encode($extension)
                        ]);
                        $updated++;
                    }

                } catch (\Exception $e) {
                    Log::warning('Failed to sync extension from FreePBX', [
                        'extension' => $extension,
                        'error' => $e->getMessage()
                    ]);
                    $failed++;
                }
            }

            return [
                'total' => count($freepbxExtensions),
                'updated' => $updated,
                'failed' => $failed
            ];

        } catch (\Exception $e) {
            Log::error('Failed to sync from FreePBX to Laravel', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'total' => 0,
                'updated' => 0,
                'failed' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Process SIP accounts in batches
     */
    protected function processSipAccountBatches($sipAccounts, int $batchSize): array
    {
        $created = 0;
        $updated = 0;
        $failed = 0;
        $skipped = 0;
        $maxRetries = (int) $this->option('max-retries');

        $batches = $sipAccounts->chunk($batchSize);

        foreach ($batches as $batchIndex => $batch) {
            $this->info("Processing batch " . ($batchIndex + 1) . " of " . $batches->count());
            
            foreach ($batch as $sipAccount) {
                $result = $this->syncSipAccount($sipAccount, $maxRetries);
                
                switch ($result['status']) {
                    case 'created':
                        $created++;
                        break;
                    case 'updated':
                        $updated++;
                        break;
                    case 'failed':
                        $failed++;
                        break;
                    case 'skipped':
                        $skipped++;
                        break;
                }
            }

            // Brief pause between batches
            usleep(200000); // 0.2 seconds
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'failed' => $failed,
            'skipped' => $skipped,
            'total' => $sipAccounts->count()
        ];
    }

    /**
     * Sync individual SIP account
     */
    protected function syncSipAccount(SipAccount $sipAccount, int $maxRetries): array
    {
        $retryCount = 0;
        
        while ($retryCount <= $maxRetries) {
            try {
                $this->line("Processing: {$sipAccount->sip_username} ({$sipAccount->user->name})");
                
                // Check if extension exists in FreePBX
                $extensionExists = $this->extensionService->extensionExists($sipAccount->sip_username);
                
                if ($extensionExists) {
                    // Update existing extension
                    $this->extensionService->updateExtension($sipAccount->sip_username, [
                        'name' => $sipAccount->user->name,
                        'email' => $sipAccount->user->email
                    ]);
                    
                    $sipAccount->update([
                        'freepbx_sync_status' => 'synced',
                        'freepbx_last_sync_at' => now(),
                        'sync_retry_count' => 0
                    ]);
                    
                    $this->info("  ✓ Updated extension {$sipAccount->sip_username}");
                    return ['status' => 'updated', 'extension' => $sipAccount->sip_username];
                    
                } else {
                    // Create new extension
                    $this->extensionService->createExtension(
                        $sipAccount->user,
                        $sipAccount->sip_username,
                        $sipAccount->sip_password
                    );
                    
                    $sipAccount->update([
                        'freepbx_sync_status' => 'synced',
                        'freepbx_last_sync_at' => now(),
                        'sync_retry_count' => 0
                    ]);
                    
                    $this->info("  ✓ Created extension {$sipAccount->sip_username}");
                    return ['status' => 'created', 'extension' => $sipAccount->sip_username];
                }
                
            } catch (\Exception $e) {
                $retryCount++;
                
                if ($retryCount > $maxRetries) {
                    // Mark as failed after max retries
                    $sipAccount->update([
                        'freepbx_sync_status' => 'failed',
                        'sync_retry_count' => $retryCount,
                        'sync_last_error' => $e->getMessage(),
                        'sync_last_attempt_at' => now()
                    ]);
                    
                    $this->error("  ✗ Failed to sync {$sipAccount->sip_username} after {$maxRetries} retries: " . $e->getMessage());
                    
                    Log::error('SIP account sync failed permanently', [
                        'sip_account_id' => $sipAccount->id,
                        'extension' => $sipAccount->sip_username,
                        'error' => $e->getMessage(),
                        'retry_count' => $retryCount
                    ]);
                    
                    return ['status' => 'failed', 'extension' => $sipAccount->sip_username, 'error' => $e->getMessage()];
                } else {
                    $this->warn("  ⚠ Retry {$retryCount}/{$maxRetries} for {$sipAccount->sip_username}: " . $e->getMessage());
                    
                    // Exponential backoff
                    sleep(pow(2, $retryCount - 1));
                }
            }
        }
        
        return ['status' => 'skipped', 'extension' => $sipAccount->sip_username];
    }

    /**
     * Display sync preview for dry run
     */
    protected function displaySyncPreview($sipAccounts): void
    {
        $this->info('Sync Preview (Dry Run):');
        
        $headers = ['Extension', 'User', 'Status', 'Action'];
        $rows = [];

        foreach ($sipAccounts->take(20) as $sipAccount) {
            try {
                $extensionExists = $this->extensionService->extensionExists($sipAccount->sip_username);
                $action = $extensionExists ? 'Update' : 'Create';
                $status = $sipAccount->freepbx_sync_status ?? 'Not Synced';
                
                $rows[] = [
                    $sipAccount->sip_username,
                    $sipAccount->user->name,
                    $status,
                    $action
                ];
            } catch (\Exception $e) {
                $rows[] = [
                    $sipAccount->sip_username,
                    $sipAccount->user->name,
                    'Error',
                    'Check Connection'
                ];
            }
        }

        $this->table($headers, $rows);
        
        if ($sipAccounts->count() > 20) {
            $this->info('... and ' . ($sipAccounts->count() - 20) . ' more extensions');
        }
    }

    /**
     * Log sync summary
     */
    protected function logSyncSummary(array $results, string $syncMode): void
    {
        $duration = now()->diffInSeconds($this->startTime);
        
        $summary = [
            'sync_mode' => $syncMode,
            'execution_time_seconds' => $duration,
            'results' => $results,
            'timestamp' => now()->toISOString()
        ];

        Log::info('Automated FreePBX sync summary', $summary);

        // Display summary to console
        $this->info('Sync Summary:');
        $this->line('- Sync mode: ' . $syncMode);
        $this->line('- Execution time: ' . $duration . ' seconds');
        $this->line('- Total processed: ' . ($results['total'] ?? 0));
        $this->line('- Created: ' . ($results['created'] ?? 0));
        $this->line('- Updated: ' . ($results['updated'] ?? 0));
        $this->line('- Failed: ' . ($results['failed'] ?? 0));
        $this->line('- Skipped: ' . ($results['skipped'] ?? 0));
        
        if (isset($results['dry_run'])) {
            $this->info('- Mode: Dry Run (no changes made)');
        }
    }

    /**
     * Update sync metrics
     */
    protected function updateSyncMetrics(array $results, string $syncMode): void
    {
        try {
            $metrics = [
                'last_freepbx_sync_at' => now()->toISOString(),
                'last_freepbx_sync_mode' => $syncMode,
                'last_freepbx_sync_duration' => now()->diffInSeconds($this->startTime),
                'last_freepbx_sync_results' => json_encode($results)
            ];

            foreach ($metrics as $key => $value) {
                SystemSetting::set($key, $value);
            }

            // Update sync statistics
            $stats = $this->getSyncStatistics();
            SystemSetting::set('freepbx_sync_stats', json_encode($stats));

        } catch (\Exception $e) {
            Log::warning('Failed to update sync metrics', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get sync statistics
     */
    protected function getSyncStatistics(): array
    {
        return [
            'total_sip_accounts' => SipAccount::count(),
            'synced_accounts' => SipAccount::where('freepbx_sync_status', 'synced')->count(),
            'failed_accounts' => SipAccount::where('freepbx_sync_status', 'failed')->count(),
            'pending_accounts' => SipAccount::where('freepbx_sync_status', 'pending')
                ->orWhereNull('freepbx_sync_status')->count(),
            'last_24h_syncs' => SipAccount::where('freepbx_last_sync_at', '>', now()->subDay())->count(),
            'sync_success_rate' => $this->calculateSyncSuccessRate()
        ];
    }

    /**
     * Calculate sync success rate
     */
    protected function calculateSyncSuccessRate(): float
    {
        $total = SipAccount::whereNotNull('freepbx_sync_status')->count();
        
        if ($total === 0) {
            return 0.0;
        }
        
        $synced = SipAccount::where('freepbx_sync_status', 'synced')->count();
        
        return round(($synced / $total) * 100, 2);
    }
}