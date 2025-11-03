<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Models\DidNumber;
use App\Models\CallRecord;
use App\Models\BalanceTransaction;
use App\Models\User;
use App\Services\MonitoringService;

class AdvancedSystemMonitorCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'system:advanced-monitor 
                            {--alert : Send alerts for critical issues}
                            {--email= : Email address for alerts}
                            {--slack= : Slack webhook URL for alerts}
                            {--report : Generate monitoring report}
                            {--fix : Attempt to fix detected issues automatically}';

    /**
     * The console command description.
     */
    protected $description = 'Advanced system monitoring for DID inventory, billing accuracy, and performance';

    protected MonitoringService $monitoringService;

    public function __construct(MonitoringService $monitoringService)
    {
        parent::__construct();
        $this->monitoringService = $monitoringService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting advanced system monitoring...');

        try {
            $monitoringResults = [
                'did_inventory' => $this->monitorDidInventory(),
                'billing_accuracy' => $this->monitorBillingAccuracy(),
                'real_time_billing' => $this->monitorRealTimeBilling(),
                'system_performance' => $this->monitorSystemPerformance(),
                'cron_jobs' => $this->monitorCronJobs(),
                'payment_gateways' => $this->monitorPaymentGateways(),
                'freepbx_integration' => $this->monitorFreePBXIntegration(),
                'data_integrity' => $this->monitorDataIntegrity()
            ];

            $criticalIssues = $this->analyzeCriticalIssues($monitoringResults);
            
            if ($this->option('report')) {
                $this->generateMonitoringReport($monitoringResults, $criticalIssues);
            }

            if (!empty($criticalIssues)) {
                $this->displayCriticalIssues($criticalIssues);
                
                if ($this->option('alert')) {
                    $this->sendAlerts($criticalIssues);
                }
                
                if ($this->option('fix')) {
                    $this->attemptAutoFix($criticalIssues);
                }
                
                return Command::FAILURE;
            } else {
                $this->info('All systems operating normally.');
                return Command::SUCCESS;
            }
            
        } catch (\Exception $e) {
            $this->error('Advanced monitoring failed: ' . $e->getMessage());
            Log::error('Advanced monitoring error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Monitor DID inventory and billing
     */
    protected function monitorDidInventory(): array
    {
        $this->info('Monitoring DID inventory...');
        
        $results = [
            'status' => 'healthy',
            'issues' => [],
            'metrics' => []
        ];

        try {
            // Check DID inventory levels by country
            $inventoryLevels = DB::table('did_numbers')
                ->select('country_code', 
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(CASE WHEN status = "available" THEN 1 ELSE 0 END) as available'),
                    DB::raw('SUM(CASE WHEN status = "assigned" THEN 1 ELSE 0 END) as assigned'),
                    DB::raw('SUM(CASE WHEN status = "suspended" THEN 1 ELSE 0 END) as suspended')
                )
                ->groupBy('country_code')
                ->get();

            foreach ($inventoryLevels as $inventory) {
                $availablePercentage = $inventory->total > 0 ? 
                    ($inventory->available / $inventory->total) * 100 : 0;
                
                $results['metrics'][$inventory->country_code] = [
                    'total' => $inventory->total,
                    'available' => $inventory->available,
                    'assigned' => $inventory->assigned,
                    'suspended' => $inventory->suspended,
                    'available_percentage' => round($availablePercentage, 2)
                ];
                
                // Alert if available inventory is low (< 10%)
                if ($availablePercentage < 10 && $inventory->total > 0) {
                    $results['issues'][] = [
                        'type' => 'low_did_inventory',
                        'severity' => 'warning',
                        'country' => $inventory->country_code,
                        'message' => "Low DID inventory for {$inventory->country_code}: {$inventory->available} available ({$availablePercentage}%)",
                        'available' => $inventory->available,
                        'total' => $inventory->total
                    ];
                    $results['status'] = 'warning';
                }
            }

            // Check for expired DIDs that should be suspended
            $expiredDids = DidNumber::where('status', 'assigned')
                ->where('expires_at', '<', Carbon::now())
                ->count();

            if ($expiredDids > 0) {
                $results['issues'][] = [
                    'type' => 'expired_dids',
                    'severity' => 'critical',
                    'message' => "{$expiredDids} DIDs have expired but are still assigned",
                    'count' => $expiredDids
                ];
                $results['status'] = 'critical';
            }

            // Check for DIDs with billing issues
            $billingIssues = DB::table('did_numbers as d')
                ->leftJoin('users as u', 'd.user_id', '=', 'u.id')
                ->where('d.status', 'assigned')
                ->where('u.balance', '<', DB::raw('d.monthly_cost'))
                ->count();

            if ($billingIssues > 0) {
                $results['issues'][] = [
                    'type' => 'did_billing_issues',
                    'severity' => 'warning',
                    'message' => "{$billingIssues} assigned DIDs have users with insufficient balance for monthly charges",
                    'count' => $billingIssues
                ];
                if ($results['status'] === 'healthy') {
                    $results['status'] = 'warning';
                }
            }

        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['issues'][] = [
                'type' => 'monitoring_error',
                'severity' => 'critical',
                'message' => 'Failed to monitor DID inventory: ' . $e->getMessage()
            ];
        }

        return $results;
    }

    /**
     * Monitor billing accuracy
     */
    protected function monitorBillingAccuracy(): array
    {
        $this->info('Monitoring billing accuracy...');
        
        $results = [
            'status' => 'healthy',
            'issues' => [],
            'metrics' => []
        ];

        try {
            // Check for unprocessed call records
            $unprocessedCalls = CallRecord::whereNull('processed_at')
                ->where('status', 'completed')
                ->where('created_at', '<', Carbon::now()->subMinutes(10))
                ->count();

            $results['metrics']['unprocessed_calls'] = $unprocessedCalls;

            if ($unprocessedCalls > 0) {
                $results['issues'][] = [
                    'type' => 'unprocessed_calls',
                    'severity' => 'warning',
                    'message' => "{$unprocessedCalls} completed calls have not been processed for billing",
                    'count' => $unprocessedCalls
                ];
                $results['status'] = 'warning';
            }

            // Check for billing discrepancies
            $billingDiscrepancies = DB::select("
                SELECT 
                    cr.user_id,
                    COUNT(*) as call_count,
                    SUM(cr.cost) as total_call_cost,
                    SUM(CASE WHEN bt.amount IS NULL THEN cr.cost ELSE 0 END) as unbilled_amount
                FROM call_records cr
                LEFT JOIN balance_transactions bt ON bt.reference_type = 'call_record' 
                    AND bt.reference_id = cr.id 
                    AND bt.type = 'debit'
                WHERE cr.status = 'completed' 
                    AND cr.cost > 0
                    AND cr.created_at >= ?
                GROUP BY cr.user_id
                HAVING unbilled_amount > 0
            ", [Carbon::now()->subDays(1)]);

            if (!empty($billingDiscrepancies)) {
                $totalUnbilled = array_sum(array_column($billingDiscrepancies, 'unbilled_amount'));
                $results['issues'][] = [
                    'type' => 'billing_discrepancies',
                    'severity' => 'critical',
                    'message' => "Billing discrepancies detected: $" . number_format($totalUnbilled, 2) . " in unbilled calls",
                    'affected_users' => count($billingDiscrepancies),
                    'unbilled_amount' => $totalUnbilled
                ];
                $results['status'] = 'critical';
            }

            // Check balance transaction integrity
            $balanceIntegrityIssues = DB::select("
                SELECT 
                    user_id,
                    COUNT(*) as transaction_count,
                    SUM(CASE WHEN type = 'credit' THEN amount ELSE -amount END) as calculated_balance,
                    MAX(balance_after) as last_balance_after
                FROM balance_transactions
                WHERE created_at >= ?
                GROUP BY user_id
                HAVING ABS(calculated_balance - last_balance_after) > 0.01
            ", [Carbon::now()->subDays(7)]);

            if (!empty($balanceIntegrityIssues)) {
                $results['issues'][] = [
                    'type' => 'balance_integrity',
                    'severity' => 'critical',
                    'message' => count($balanceIntegrityIssues) . " users have balance calculation discrepancies",
                    'affected_users' => count($balanceIntegrityIssues)
                ];
                $results['status'] = 'critical';
            }

            // Check for negative balances on prepaid accounts
            $negativeBalances = User::where('account_type', 'prepaid')
                ->where('balance', '<', 0)
                ->count();

            $results['metrics']['negative_balances'] = $negativeBalances;

            if ($negativeBalances > 0) {
                $results['issues'][] = [
                    'type' => 'negative_prepaid_balances',
                    'severity' => 'warning',
                    'message' => "{$negativeBalances} prepaid accounts have negative balances",
                    'count' => $negativeBalances
                ];
                if ($results['status'] === 'healthy') {
                    $results['status'] = 'warning';
                }
            }

        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['issues'][] = [
                'type' => 'monitoring_error',
                'severity' => 'critical',
                'message' => 'Failed to monitor billing accuracy: ' . $e->getMessage()
            ];
        }

        return $results;
    }

    /**
     * Monitor real-time billing performance
     */
    protected function monitorRealTimeBilling(): array
    {
        $this->info('Monitoring real-time billing...');
        
        $results = [
            'status' => 'healthy',
            'issues' => [],
            'metrics' => []
        ];

        try {
            // Check for active calls that may need termination
            $activeCalls = CallRecord::where('status', 'connected')
                ->where('start_time', '<', Carbon::now()->subMinutes(5))
                ->get();

            $results['metrics']['active_calls'] = $activeCalls->count();

            foreach ($activeCalls as $call) {
                $user = $call->user;
                if (!$user) continue;

                $callDuration = Carbon::now()->diffInSeconds($call->start_time);
                $estimatedCost = ($callDuration / 60) * $call->rate_per_minute;

                if ($user->account_type === 'prepaid' && $user->balance < $estimatedCost) {
                    $results['issues'][] = [
                        'type' => 'call_termination_needed',
                        'severity' => 'critical',
                        'message' => "Call {$call->id} should be terminated due to insufficient balance",
                        'call_id' => $call->id,
                        'user_id' => $user->id,
                        'user_balance' => $user->balance,
                        'estimated_cost' => $estimatedCost
                    ];
                    $results['status'] = 'critical';
                }
            }

            // Check billing increment accuracy
            $recentCalls = CallRecord::where('status', 'completed')
                ->where('created_at', '>', Carbon::now()->subHour())
                ->get();

            $billingErrors = 0;
            foreach ($recentCalls as $call) {
                if ($call->duration > 0 && $call->billing_increment > 0) {
                    $expectedBilledDuration = ceil($call->duration / $call->billing_increment) * $call->billing_increment;
                    $actualBilledDuration = ($call->cost / $call->rate_per_minute) * 60;
                    
                    if (abs($expectedBilledDuration - $actualBilledDuration) > 1) {
                        $billingErrors++;
                    }
                }
            }

            $results['metrics']['billing_errors'] = $billingErrors;

            if ($billingErrors > 0) {
                $results['issues'][] = [
                    'type' => 'billing_increment_errors',
                    'severity' => 'warning',
                    'message' => "{$billingErrors} calls in the last hour have billing increment calculation errors",
                    'count' => $billingErrors
                ];
                if ($results['status'] === 'healthy') {
                    $results['status'] = 'warning';
                }
            }

        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['issues'][] = [
                'type' => 'monitoring_error',
                'severity' => 'critical',
                'message' => 'Failed to monitor real-time billing: ' . $e->getMessage()
            ];
        }

        return $results;
    }

    /**
     * Monitor system performance
     */
    protected function monitorSystemPerformance(): array
    {
        $this->info('Monitoring system performance...');
        
        $results = [
            'status' => 'healthy',
            'issues' => [],
            'metrics' => []
        ];

        try {
            // Check database performance
            $slowQueries = DB::select("SHOW GLOBAL STATUS LIKE 'Slow_queries'");
            $results['metrics']['slow_queries'] = $slowQueries[0]->Value ?? 0;

            // Check memory usage
            $memoryInfo = $this->getMemoryInfo();
            $results['metrics']['memory'] = $memoryInfo;

            if ($memoryInfo['usage_percentage'] > 90) {
                $results['issues'][] = [
                    'type' => 'high_memory_usage',
                    'severity' => 'critical',
                    'message' => "Memory usage is {$memoryInfo['usage_percentage']}%",
                    'usage' => $memoryInfo['usage_percentage']
                ];
                $results['status'] = 'critical';
            }

            // Check disk space
            $diskInfo = $this->getDiskInfo();
            $results['metrics']['disk'] = $diskInfo;

            if ($diskInfo['usage_percentage'] > 90) {
                $results['issues'][] = [
                    'type' => 'high_disk_usage',
                    'severity' => 'critical',
                    'message' => "Disk usage is {$diskInfo['usage_percentage']}%",
                    'usage' => $diskInfo['usage_percentage']
                ];
                $results['status'] = 'critical';
            }

            // Check response times
            $avgResponseTime = $this->measureAverageResponseTime();
            $results['metrics']['avg_response_time'] = $avgResponseTime;

            if ($avgResponseTime > 2000) { // 2 seconds
                $results['issues'][] = [
                    'type' => 'slow_response_time',
                    'severity' => 'warning',
                    'message' => "Average response time is {$avgResponseTime}ms",
                    'response_time' => $avgResponseTime
                ];
                if ($results['status'] === 'healthy') {
                    $results['status'] = 'warning';
                }
            }

        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['issues'][] = [
                'type' => 'monitoring_error',
                'severity' => 'critical',
                'message' => 'Failed to monitor system performance: ' . $e->getMessage()
            ];
        }

        return $results;
    }

    /**
     * Monitor cron jobs
     */
    protected function monitorCronJobs(): array
    {
        $this->info('Monitoring cron jobs...');
        
        $results = [
            'status' => 'healthy',
            'issues' => [],
            'metrics' => []
        ];

        try {
            $criticalJobs = [
                'cdr:automated-processing' => 10, // Should run every 5 minutes
                'freepbx:automated-sync' => 35,   // Should run every 30 minutes
                'billing:monitor-realtime' => 5,  // Should run every 2 minutes
                'system:health-check' => 10,      // Should run every 5 minutes
            ];

            foreach ($criticalJobs as $jobName => $maxMinutesSinceRun) {
                $lastRun = DB::table('cron_job_executions')
                    ->where('job_name', $jobName)
                    ->where('status', 'completed')
                    ->orderBy('completed_at', 'desc')
                    ->first();

                if (!$lastRun) {
                    $results['issues'][] = [
                        'type' => 'cron_job_never_run',
                        'severity' => 'critical',
                        'message' => "Critical job {$jobName} has never run successfully",
                        'job_name' => $jobName
                    ];
                    $results['status'] = 'critical';
                } else {
                    $minutesSinceRun = Carbon::now()->diffInMinutes(Carbon::parse($lastRun->completed_at));
                    $results['metrics'][$jobName] = [
                        'last_run' => $lastRun->completed_at,
                        'minutes_since_run' => $minutesSinceRun
                    ];
                    
                    if ($minutesSinceRun > $maxMinutesSinceRun) {
                        $results['issues'][] = [
                            'type' => 'cron_job_overdue',
                            'severity' => 'critical',
                            'message' => "Critical job {$jobName} is overdue (last run: {$minutesSinceRun} minutes ago)",
                            'job_name' => $jobName,
                            'minutes_overdue' => $minutesSinceRun - $maxMinutesSinceRun
                        ];
                        $results['status'] = 'critical';
                    }
                }
            }

        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['issues'][] = [
                'type' => 'monitoring_error',
                'severity' => 'critical',
                'message' => 'Failed to monitor cron jobs: ' . $e->getMessage()
            ];
        }

        return $results;
    }

    /**
     * Monitor payment gateways
     */
    protected function monitorPaymentGateways(): array
    {
        $results = [
            'status' => 'healthy',
            'issues' => [],
            'metrics' => []
        ];

        // This would include actual gateway health checks
        // For now, we'll simulate the monitoring
        $results['metrics']['gateway_status'] = 'simulated';

        return $results;
    }

    /**
     * Monitor FreePBX integration
     */
    protected function monitorFreePBXIntegration(): array
    {
        $results = [
            'status' => 'healthy',
            'issues' => [],
            'metrics' => []
        ];

        // This would include actual FreePBX API health checks
        // For now, we'll simulate the monitoring
        $results['metrics']['freepbx_status'] = 'simulated';

        return $results;
    }

    /**
     * Monitor data integrity
     */
    protected function monitorDataIntegrity(): array
    {
        $results = [
            'status' => 'healthy',
            'issues' => [],
            'metrics' => []
        ];

        try {
            // Check for orphaned records
            $orphanedCallRecords = DB::table('call_records')
                ->leftJoin('users', 'call_records.user_id', '=', 'users.id')
                ->whereNull('users.id')
                ->count();

            if ($orphanedCallRecords > 0) {
                $results['issues'][] = [
                    'type' => 'orphaned_records',
                    'severity' => 'warning',
                    'message' => "{$orphanedCallRecords} call records have invalid user references",
                    'count' => $orphanedCallRecords
                ];
                $results['status'] = 'warning';
            }

        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['issues'][] = [
                'type' => 'monitoring_error',
                'severity' => 'critical',
                'message' => 'Failed to monitor data integrity: ' . $e->getMessage()
            ];
        }

        return $results;
    }

    /**
     * Get memory information
     */
    protected function getMemoryInfo(): array
    {
        $memInfo = [];
        
        if (function_exists('memory_get_usage')) {
            $memInfo['php_memory_usage'] = memory_get_usage(true);
            $memInfo['php_memory_peak'] = memory_get_peak_usage(true);
        }

        // Try to get system memory info (Linux)
        if (file_exists('/proc/meminfo')) {
            $memInfoContent = file_get_contents('/proc/meminfo');
            preg_match('/MemTotal:\s+(\d+)/', $memInfoContent, $total);
            preg_match('/MemAvailable:\s+(\d+)/', $memInfoContent, $available);
            
            if ($total && $available) {
                $totalMem = $total[1] * 1024; // Convert to bytes
                $availableMem = $available[1] * 1024;
                $usedMem = $totalMem - $availableMem;
                
                $memInfo['total'] = $totalMem;
                $memInfo['used'] = $usedMem;
                $memInfo['available'] = $availableMem;
                $memInfo['usage_percentage'] = round(($usedMem / $totalMem) * 100, 2);
            }
        }

        return $memInfo;
    }

    /**
     * Get disk information
     */
    protected function getDiskInfo(): array
    {
        $diskInfo = [];
        
        $totalSpace = disk_total_space('/');
        $freeSpace = disk_free_space('/');
        
        if ($totalSpace && $freeSpace) {
            $usedSpace = $totalSpace - $freeSpace;
            $diskInfo = [
                'total' => $totalSpace,
                'used' => $usedSpace,
                'free' => $freeSpace,
                'usage_percentage' => round(($usedSpace / $totalSpace) * 100, 2)
            ];
        }

        return $diskInfo;
    }

    /**
     * Measure average response time
     */
    protected function measureAverageResponseTime(): float
    {
        $startTime = microtime(true);
        
        // Perform a simple database query to measure response time
        DB::table('users')->count();
        
        $endTime = microtime(true);
        
        return round(($endTime - $startTime) * 1000, 2); // Convert to milliseconds
    }

    /**
     * Analyze critical issues from all monitoring results
     */
    protected function analyzeCriticalIssues(array $monitoringResults): array
    {
        $criticalIssues = [];
        
        foreach ($monitoringResults as $category => $results) {
            if (isset($results['issues'])) {
                foreach ($results['issues'] as $issue) {
                    if (in_array($issue['severity'], ['critical', 'warning'])) {
                        $issue['category'] = $category;
                        $criticalIssues[] = $issue;
                    }
                }
            }
        }
        
        // Sort by severity (critical first)
        usort($criticalIssues, function($a, $b) {
            $severityOrder = ['critical' => 0, 'warning' => 1, 'info' => 2];
            return $severityOrder[$a['severity']] <=> $severityOrder[$b['severity']];
        });
        
        return $criticalIssues;
    }

    /**
     * Display critical issues
     */
    protected function displayCriticalIssues(array $issues): void
    {
        $this->error('Critical issues detected:');
        $this->newLine();

        foreach ($issues as $issue) {
            $severity = strtoupper($issue['severity']);
            $category = strtoupper($issue['category']);
            
            $color = match($issue['severity']) {
                'critical' => 'error',
                'warning' => 'comment',
                default => 'info'
            };
            
            $this->$color("[{$severity}] [{$category}] {$issue['message']}");
        }
        
        $this->newLine();
    }

    /**
     * Send alerts for critical issues
     */
    protected function sendAlerts(array $issues): void
    {
        $criticalCount = count(array_filter($issues, fn($i) => $i['severity'] === 'critical'));
        $warningCount = count(array_filter($issues, fn($i) => $i['severity'] === 'warning'));
        
        Log::channel('alerts')->critical('Advanced system monitoring detected issues', [
            'critical_issues' => $criticalCount,
            'warning_issues' => $warningCount,
            'total_issues' => count($issues),
            'issues' => $issues,
            'timestamp' => Carbon::now()
        ]);
        
        $this->info("Alerts logged. Critical: {$criticalCount}, Warnings: {$warningCount}");
        
        // Send email alerts if configured
        if ($this->option('email')) {
            $this->sendEmailAlert($this->option('email'), $issues);
        }
        
        // Send Slack alerts if configured
        if ($this->option('slack')) {
            $this->sendSlackAlert($this->option('slack'), $issues);
        }
    }

    /**
     * Send email alert
     */
    protected function sendEmailAlert(string $email, array $issues): void
    {
        $this->info("Email alert would be sent to: {$email}");
        // Implementation would depend on your email service
    }

    /**
     * Send Slack alert
     */
    protected function sendSlackAlert(string $webhook, array $issues): void
    {
        $this->info("Slack alert would be sent to webhook");
        // Implementation would use Slack webhook API
    }

    /**
     * Attempt automatic fixes for detected issues
     */
    protected function attemptAutoFix(array $issues): void
    {
        $this->info('Attempting automatic fixes...');
        
        foreach ($issues as $issue) {
            switch ($issue['type']) {
                case 'unprocessed_calls':
                    $this->fixUnprocessedCalls();
                    break;
                    
                case 'expired_dids':
                    $this->fixExpiredDids();
                    break;
                    
                case 'call_termination_needed':
                    $this->fixCallTermination($issue);
                    break;
            }
        }
    }

    /**
     * Fix unprocessed calls
     */
    protected function fixUnprocessedCalls(): void
    {
        $this->info('Processing unprocessed calls...');
        $this->call('cdr:automated-processing', ['--batch-size' => 50]);
    }

    /**
     * Fix expired DIDs
     */
    protected function fixExpiredDids(): void
    {
        $this->info('Suspending expired DIDs...');
        $this->call('billing:monthly-did-charges', ['--suspend-expired' => true]);
    }

    /**
     * Fix call termination
     */
    protected function fixCallTermination(array $issue): void
    {
        $this->info("Terminating call {$issue['call_id']} due to insufficient balance...");
        $this->call('billing:monitor-realtime', ['--terminate' => true]);
    }

    /**
     * Generate monitoring report
     */
    protected function generateMonitoringReport(array $results, array $issues): void
    {
        $reportPath = storage_path('app/reports/advanced-monitoring-' . date('Y-m-d-H-i-s') . '.json');
        
        $report = [
            'timestamp' => Carbon::now()->toISOString(),
            'server' => gethostname(),
            'monitoring_results' => $results,
            'critical_issues' => $issues,
            'summary' => [
                'total_categories_monitored' => count($results),
                'categories_with_issues' => count(array_filter($results, fn($r) => $r['status'] !== 'healthy')),
                'total_issues' => count($issues),
                'critical_issues' => count(array_filter($issues, fn($i) => $i['severity'] === 'critical')),
                'warning_issues' => count(array_filter($issues, fn($i) => $i['severity'] === 'warning'))
            ]
        ];
        
        if (!is_dir(dirname($reportPath))) {
            mkdir(dirname($reportPath), 0755, true);
        }
        
        file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT));
        
        $this->info("Advanced monitoring report generated: {$reportPath}");
    }
}