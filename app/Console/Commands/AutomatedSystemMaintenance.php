<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MonitoringService;
use App\Services\Email\PaymentNotificationService;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

class AutomatedSystemMaintenance extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'system:automated-maintenance 
                            {--task=all : Specific task to run (health-check, low-balance, database-cleanup, backup)}
                            {--force : Force maintenance even if recently run}
                            {--dry-run : Show what would be done without executing}
                            {--alert-threshold=critical : Alert threshold (info, warning, critical)}';

    /**
     * The console command description.
     */
    protected $description = 'Automated system maintenance including health checks, cleanup, and notifications';

    protected $monitoringService;
    protected $notificationService;
    protected $startTime;
    protected $lockKey = 'system_maintenance_lock';

    public function __construct(
        MonitoringService $monitoringService,
        PaymentNotificationService $notificationService
    ) {
        parent::__construct();
        $this->monitoringService = $monitoringService;
        $this->notificationService = $notificationService;
        $this->startTime = now();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Check if maintenance is already running
        if (!$this->acquireLock()) {
            $this->warn('System maintenance is already running. Use --force to override.');
            return 1;
        }

        try {
            $this->info('Starting automated system maintenance...');
            
            $task = $this->option('task');
            $dryRun = $this->option('dry-run');
            
            $results = [];

            // Determine which tasks to run
            $tasks = $task === 'all' ? ['health-check', 'low-balance', 'database-cleanup'] : [$task];
            
            foreach ($tasks as $taskName) {
                if ($this->shouldRunTask($taskName)) {
                    $this->info("Running task: {$taskName}");
                    $results[$taskName] = $this->runMaintenanceTask($taskName, $dryRun);
                } else {
                    $this->info("Skipping task: {$taskName} (recently completed)");
                    $results[$taskName] = ['status' => 'skipped', 'reason' => 'recently_completed'];
                }
            }

            // Log maintenance summary
            $this->logMaintenanceSummary($results);

            // Update system metrics
            $this->updateMaintenanceMetrics($results);

            $this->info('Automated system maintenance completed successfully.');
            return 0;

        } catch (\Exception $e) {
            $this->error('System maintenance failed: ' . $e->getMessage());
            Log::error('Automated system maintenance failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Acquire maintenance lock
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
        ], 7200); // 2 hour lock
    }

    /**
     * Release maintenance lock
     */
    protected function releaseLock(): void
    {
        Cache::forget($this->lockKey);
    }

    /**
     * Check if task should run based on schedule
     */
    protected function shouldRunTask(string $task): bool
    {
        if ($this->option('force')) {
            return true;
        }

        $lastRun = SystemSetting::get("last_{$task}_maintenance_at");
        
        if (!$lastRun) {
            return true;
        }

        $lastRunTime = Carbon::parse($lastRun);
        $interval = $this->getTaskInterval($task);
        
        return now()->diffInMinutes($lastRunTime) >= $interval;
    }

    /**
     * Get task interval in minutes
     */
    protected function getTaskInterval(string $task): int
    {
        $intervals = [
            'health-check' => 60,        // Every hour
            'low-balance' => 1440,       // Daily (24 hours)
            'database-cleanup' => 10080, // Weekly (7 days)
            'backup' => 1440             // Daily (24 hours)
        ];

        return $intervals[$task] ?? 60;
    }

    /**
     * Run specific maintenance task
     */
    protected function runMaintenanceTask(string $task, bool $dryRun): array
    {
        try {
            switch ($task) {
                case 'health-check':
                    return $this->runHealthCheck($dryRun);
                case 'low-balance':
                    return $this->runLowBalanceCheck($dryRun);
                case 'database-cleanup':
                    return $this->runDatabaseCleanup($dryRun);
                case 'backup':
                    return $this->runSystemBackup($dryRun);
                default:
                    throw new \InvalidArgumentException("Unknown task: {$task}");
            }
        } catch (\Exception $e) {
            Log::error("Maintenance task failed: {$task}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'task' => $task
            ];
        }
    }

    /**
     * Run system health check
     */
    protected function runHealthCheck(bool $dryRun): array
    {
        $this->info('Performing system health check...');
        
        try {
            $health = $this->monitoringService->getSystemHealth();
            $performance = $this->monitoringService->getPerformanceMetrics();
            
            // Check for issues
            $issues = $this->analyzeHealthMetrics($health, $performance);
            
            if ($dryRun) {
                $this->displayHealthPreview($health, $issues);
                return [
                    'status' => 'dry_run',
                    'issues_found' => count($issues),
                    'health_score' => $this->calculateHealthScore($health)
                ];
            }

            // Log metrics
            $this->monitoringService->logSystemMetrics();
            
            // Send alerts if necessary
            $alertsSent = 0;
            $alertThreshold = $this->option('alert-threshold');
            
            foreach ($issues as $issue) {
                if ($this->shouldSendAlert($issue, $alertThreshold)) {
                    $this->sendHealthAlert($issue);
                    $alertsSent++;
                }
            }

            // Update last run time
            SystemSetting::set('last_health-check_maintenance_at', now()->toISOString());

            return [
                'status' => 'completed',
                'issues_found' => count($issues),
                'alerts_sent' => $alertsSent,
                'health_score' => $this->calculateHealthScore($health),
                'metrics' => [
                    'database_response_time' => $health['database']['response_time_ms'] ?? null,
                    'disk_usage_percentage' => $health['disk_space']['usage_percentage'] ?? null,
                    'memory_usage_mb' => $health['memory_usage']['current_mb'] ?? null,
                    'active_users_24h' => $health['active_users'] ?? null
                ]
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Run low balance check and send warnings
     */
    protected function runLowBalanceCheck(bool $dryRun): array
    {
        $this->info('Checking for low balance users...');
        
        try {
            $threshold = config('voip.low_balance_threshold', 5.00);
            
            // Get users with low balance
            $lowBalanceUsers = User::where('balance', '<', $threshold)
                ->where('balance', '>', 0) // Exclude negative balances
                ->where('status', 'active')
                ->get();

            if ($dryRun) {
                $this->displayLowBalancePreview($lowBalanceUsers, $threshold);
                return [
                    'status' => 'dry_run',
                    'users_found' => $lowBalanceUsers->count(),
                    'threshold' => $threshold
                ];
            }

            // Send warnings
            $warningsSent = 0;
            $warningsFailed = 0;

            foreach ($lowBalanceUsers as $user) {
                try {
                    // Check if warning was sent recently (within 24 hours)
                    $lastWarning = SystemSetting::get("last_low_balance_warning_user_{$user->id}");
                    
                    if ($lastWarning && Carbon::parse($lastWarning)->diffInHours() < 24) {
                        continue; // Skip if warning sent recently
                    }

                    $success = $this->notificationService->sendLowBalanceWarning($user, $threshold);
                    
                    if ($success) {
                        $warningsSent++;
                        SystemSetting::set("last_low_balance_warning_user_{$user->id}", now()->toISOString());
                        $this->line("✓ Warning sent to {$user->name} ({$user->email})");
                    } else {
                        $warningsFailed++;
                        $this->warn("✗ Failed to send warning to {$user->name}");
                    }

                } catch (\Exception $e) {
                    $warningsFailed++;
                    Log::error("Failed to send low balance warning", [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Update last run time
            SystemSetting::set('last_low-balance_maintenance_at', now()->toISOString());

            return [
                'status' => 'completed',
                'users_checked' => $lowBalanceUsers->count(),
                'warnings_sent' => $warningsSent,
                'warnings_failed' => $warningsFailed,
                'threshold' => $threshold
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Run database cleanup
     */
    protected function runDatabaseCleanup(bool $dryRun): array
    {
        $this->info('Performing database cleanup...');
        
        try {
            $retentionDays = config('voip.data_retention_days', 90);
            $cutoffDate = Carbon::now()->subDays($retentionDays);
            
            $cleanupStats = [];

            if ($dryRun) {
                // Count records that would be deleted
                $cleanupStats = $this->getCleanupPreview($cutoffDate);
                $this->displayCleanupPreview($cleanupStats, $retentionDays);
                
                return [
                    'status' => 'dry_run',
                    'retention_days' => $retentionDays,
                    'records_to_delete' => array_sum($cleanupStats)
                ];
            }

            // Perform actual cleanup
            DB::beginTransaction();
            
            try {
                // Clean up old audit logs (keep for 1 year)
                $auditCutoff = Carbon::now()->subDays(365);
                $cleanupStats['audit_logs'] = DB::table('audit_logs')
                    ->where('created_at', '<', $auditCutoff)
                    ->delete();

                // Clean up old failed payment transactions
                $cleanupStats['failed_payments'] = DB::table('payment_transactions')
                    ->where('status', 'failed')
                    ->where('created_at', '<', $cutoffDate)
                    ->delete();

                // Clean up old call records
                $callRetentionDays = config('voip.call_retention_days', 180);
                $callCutoff = Carbon::now()->subDays($callRetentionDays);
                $cleanupStats['call_records'] = DB::table('call_records')
                    ->where('created_at', '<', $callCutoff)
                    ->delete();

                // Clean up expired sessions
                $cleanupStats['expired_sessions'] = DB::table('sessions')
                    ->where('last_activity', '<', now()->subHours(24)->timestamp)
                    ->delete();

                // Clean up expired cache entries
                $cleanupStats['expired_cache'] = DB::table('cache')
                    ->where('expiration', '<', now()->timestamp)
                    ->delete();

                DB::commit();

                // Optimize database after cleanup
                $this->optimizeDatabase();

                // Update last run time
                SystemSetting::set('last_database-cleanup_maintenance_at', now()->toISOString());

                return [
                    'status' => 'completed',
                    'retention_days' => $retentionDays,
                    'records_deleted' => $cleanupStats,
                    'total_deleted' => array_sum($cleanupStats)
                ];

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Run system backup
     */
    protected function runSystemBackup(bool $dryRun): array
    {
        $this->info('Performing system backup...');
        
        try {
            if ($dryRun) {
                $backupSize = $this->estimateBackupSize();
                $this->info("Estimated backup size: {$backupSize}MB");
                
                return [
                    'status' => 'dry_run',
                    'estimated_size_mb' => $backupSize
                ];
            }

            // Run backup command
            $exitCode = Artisan::call('backup:system', [
                '--compress' => true,
                '--retention' => 30
            ]);

            if ($exitCode === 0) {
                SystemSetting::set('last_backup_maintenance_at', now()->toISOString());
                
                return [
                    'status' => 'completed',
                    'backup_created' => true,
                    'timestamp' => now()->format('Y-m-d_H-i-s')
                ];
            } else {
                return [
                    'status' => 'failed',
                    'error' => 'Backup command failed with exit code: ' . $exitCode
                ];
            }

        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Analyze health metrics for issues
     */
    protected function analyzeHealthMetrics(array $health, array $performance): array
    {
        $issues = [];

        // Database issues
        if ($health['database']['status'] !== 'healthy') {
            $issues[] = [
                'type' => 'database',
                'severity' => 'critical',
                'message' => 'Database is unhealthy',
                'details' => $health['database']
            ];
        }

        if (isset($health['database']['response_time_ms']) && $health['database']['response_time_ms'] > 1000) {
            $issues[] = [
                'type' => 'database',
                'severity' => 'warning',
                'message' => "Database response time is high ({$health['database']['response_time_ms']}ms)",
                'details' => ['response_time_ms' => $health['database']['response_time_ms']]
            ];
        }

        // Disk space issues
        if ($health['disk_space']['status'] === 'critical') {
            $issues[] = [
                'type' => 'disk_space',
                'severity' => 'critical',
                'message' => "Disk space critically low ({$health['disk_space']['usage_percentage']}% used)",
                'details' => $health['disk_space']
            ];
        } elseif ($health['disk_space']['status'] === 'warning') {
            $issues[] = [
                'type' => 'disk_space',
                'severity' => 'warning',
                'message' => "Disk space running low ({$health['disk_space']['usage_percentage']}% used)",
                'details' => $health['disk_space']
            ];
        }

        // Cache issues
        if ($health['cache']['status'] !== 'healthy') {
            $issues[] = [
                'type' => 'cache',
                'severity' => 'warning',
                'message' => 'Cache system is unhealthy',
                'details' => $health['cache']
            ];
        }

        // Performance issues
        if (isset($performance['error_rates']['http_5xx_rate']) && $performance['error_rates']['http_5xx_rate'] > 5) {
            $issues[] = [
                'type' => 'performance',
                'severity' => 'warning',
                'message' => "High 5xx error rate ({$performance['error_rates']['http_5xx_rate']}%)",
                'details' => $performance['error_rates']
            ];
        }

        return $issues;
    }

    /**
     * Calculate overall health score
     */
    protected function calculateHealthScore(array $health): int
    {
        $score = 100;

        // Deduct points for issues
        if ($health['database']['status'] !== 'healthy') {
            $score -= 30;
        }

        if ($health['cache']['status'] !== 'healthy') {
            $score -= 10;
        }

        if ($health['disk_space']['status'] === 'critical') {
            $score -= 25;
        } elseif ($health['disk_space']['status'] === 'warning') {
            $score -= 10;
        }

        // Deduct for high response times
        if (isset($health['database']['response_time_ms']) && $health['database']['response_time_ms'] > 1000) {
            $score -= 15;
        }

        return max(0, $score);
    }

    /**
     * Check if alert should be sent based on severity and threshold
     */
    protected function shouldSendAlert(array $issue, string $threshold): bool
    {
        $severityLevels = ['info' => 1, 'warning' => 2, 'critical' => 3];
        $thresholdLevel = $severityLevels[$threshold] ?? 3;
        $issueLevel = $severityLevels[$issue['severity']] ?? 1;
        
        return $issueLevel >= $thresholdLevel;
    }

    /**
     * Send health alert
     */
    protected function sendHealthAlert(array $issue): void
    {
        Log::channel('alerts')->log($issue['severity'], 'System Health Alert', [
            'type' => $issue['type'],
            'message' => $issue['message'],
            'details' => $issue['details'],
            'timestamp' => now()->toISOString(),
            'server' => gethostname()
        ]);
    }

    /**
     * Display health preview for dry run
     */
    protected function displayHealthPreview(array $health, array $issues): void
    {
        $this->info('Health Check Preview:');
        
        $this->line('System Status:');
        $this->line('- Database: ' . $health['database']['status']);
        $this->line('- Cache: ' . $health['cache']['status']);
        $this->line('- Disk Space: ' . $health['disk_space']['status'] . ' (' . $health['disk_space']['usage_percentage'] . '% used)');
        
        if (!empty($issues)) {
            $this->warn('Issues Found:');
            foreach ($issues as $issue) {
                $this->line("- [{$issue['severity']}] {$issue['message']}");
            }
        } else {
            $this->info('No issues found.');
        }
    }

    /**
     * Display low balance preview
     */
    protected function displayLowBalancePreview($users, float $threshold): void
    {
        $this->info("Low Balance Users (threshold: \${$threshold}):");
        
        if ($users->isEmpty()) {
            $this->info('No users with low balance found.');
            return;
        }

        $headers = ['User', 'Email', 'Balance', 'Last Warning'];
        $rows = [];

        foreach ($users->take(10) as $user) {
            $lastWarning = SystemSetting::get("last_low_balance_warning_user_{$user->id}");
            $lastWarningFormatted = $lastWarning ? Carbon::parse($lastWarning)->diffForHumans() : 'Never';
            
            $rows[] = [
                $user->name,
                $user->email,
                '$' . number_format($user->balance, 2),
                $lastWarningFormatted
            ];
        }

        $this->table($headers, $rows);
        
        if ($users->count() > 10) {
            $this->info('... and ' . ($users->count() - 10) . ' more users');
        }
    }

    /**
     * Get cleanup preview counts
     */
    protected function getCleanupPreview(Carbon $cutoffDate): array
    {
        $auditCutoff = Carbon::now()->subDays(365);
        $callCutoff = Carbon::now()->subDays(config('voip.call_retention_days', 180));
        
        return [
            'audit_logs' => DB::table('audit_logs')->where('created_at', '<', $auditCutoff)->count(),
            'failed_payments' => DB::table('payment_transactions')
                ->where('status', 'failed')
                ->where('created_at', '<', $cutoffDate)
                ->count(),
            'call_records' => DB::table('call_records')->where('created_at', '<', $callCutoff)->count(),
            'expired_sessions' => DB::table('sessions')
                ->where('last_activity', '<', now()->subHours(24)->timestamp)
                ->count(),
            'expired_cache' => DB::table('cache')
                ->where('expiration', '<', now()->timestamp)
                ->count()
        ];
    }

    /**
     * Display cleanup preview
     */
    protected function displayCleanupPreview(array $stats, int $retentionDays): void
    {
        $this->info("Database Cleanup Preview (retention: {$retentionDays} days):");
        
        foreach ($stats as $table => $count) {
            if ($count > 0) {
                $this->line("- {$table}: {$count} records to delete");
            }
        }
        
        $total = array_sum($stats);
        $this->info("Total records to delete: {$total}");
    }

    /**
     * Optimize database after cleanup
     */
    protected function optimizeDatabase(): void
    {
        try {
            $driver = config('database.connections.' . config('database.default') . '.driver');
            
            if ($driver === 'sqlite') {
                DB::statement("VACUUM");
            } else {
                // For MySQL/MariaDB, optimize key tables
                $keyTables = ['call_records', 'payment_transactions', 'balance_transactions', 'audit_logs'];
                
                foreach ($keyTables as $table) {
                    try {
                        DB::statement("OPTIMIZE TABLE `{$table}`");
                    } catch (\Exception $e) {
                        // Continue with other tables if one fails
                        Log::warning("Failed to optimize table {$table}: " . $e->getMessage());
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning("Database optimization failed: " . $e->getMessage());
        }
    }

    /**
     * Estimate backup size
     */
    protected function estimateBackupSize(): float
    {
        try {
            $driver = config('database.connections.' . config('database.default') . '.driver');
            
            if ($driver === 'sqlite') {
                $dbPath = config('database.connections.sqlite.database');
                return file_exists($dbPath) ? round(filesize($dbPath) / 1024 / 1024, 2) : 0;
            } else {
                // Estimate MySQL database size
                $databaseName = config('database.connections.' . config('database.default') . '.database');
                $result = DB::select("
                    SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                    FROM information_schema.TABLES 
                    WHERE table_schema = ?
                ", [$databaseName]);
                
                return $result[0]->size_mb ?? 0;
            }
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Log maintenance summary
     */
    protected function logMaintenanceSummary(array $results): void
    {
        $duration = now()->diffInSeconds($this->startTime);
        
        $summary = [
            'execution_time_seconds' => $duration,
            'tasks_executed' => array_keys($results),
            'results' => $results,
            'timestamp' => now()->toISOString()
        ];

        Log::info('Automated system maintenance summary', $summary);

        // Display summary to console
        $this->info('Maintenance Summary:');
        $this->line('- Execution time: ' . $duration . ' seconds');
        $this->line('- Tasks executed: ' . implode(', ', array_keys($results)));
        
        foreach ($results as $task => $result) {
            $status = $result['status'] ?? 'unknown';
            $this->line("- {$task}: {$status}");
        }
    }

    /**
     * Update maintenance metrics
     */
    protected function updateMaintenanceMetrics(array $results): void
    {
        try {
            $metrics = [
                'last_system_maintenance_at' => now()->toISOString(),
                'last_system_maintenance_duration' => now()->diffInSeconds($this->startTime),
                'last_system_maintenance_results' => json_encode($results)
            ];

            foreach ($metrics as $key => $value) {
                SystemSetting::set($key, $value);
            }

        } catch (\Exception $e) {
            Log::warning('Failed to update maintenance metrics', [
                'error' => $e->getMessage()
            ]);
        }
    }
}