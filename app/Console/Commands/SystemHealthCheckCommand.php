<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MonitoringService;
use Illuminate\Support\Facades\Log;

class SystemHealthCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'system:health-check 
                            {--alert : Send alerts for critical issues}
                            {--detailed : Include detailed metrics}';

    /**
     * The console command description.
     */
    protected $description = 'Perform system health check and log metrics';

    /**
     * Execute the console command.
     */
    public function handle(MonitoringService $monitoring): int
    {
        $this->info('Starting system health check...');

        try {
            // Get system health metrics
            $health = $monitoring->getSystemHealth();
            
            // Display basic health status
            $this->displayHealthStatus($health);
            
            // Get performance metrics if detailed flag is set
            if ($this->option('detailed')) {
                $performance = $monitoring->getPerformanceMetrics();
                $this->displayPerformanceMetrics($performance);
            }
            
            // Log metrics
            $monitoring->logSystemMetrics();
            
            // Check for critical issues
            $criticalIssues = $this->checkForCriticalIssues($health);
            
            if (!empty($criticalIssues)) {
                $this->error('Critical issues detected:');
                foreach ($criticalIssues as $issue) {
                    $this->error("- {$issue}");
                }
                
                if ($this->option('alert')) {
                    $this->sendAlerts($criticalIssues);
                }
                
                return Command::FAILURE;
            }
            
            $this->info('System health check completed successfully.');
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Health check failed: {$e->getMessage()}");
            Log::error('System health check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return Command::FAILURE;
        }
    }

    /**
     * Display health status information
     */
    protected function displayHealthStatus(array $health): void
    {
        $this->info('=== System Health Status ===');
        
        // Database status
        $dbStatus = $health['database']['status'];
        $dbColor = $dbStatus === 'healthy' ? 'green' : 'red';
        $this->line("<fg={$dbColor}>Database: {$dbStatus}</>");
        
        if (isset($health['database']['response_time_ms'])) {
            $this->line("  Response time: {$health['database']['response_time_ms']}ms");
        }
        
        // Cache status
        $cacheStatus = $health['cache']['status'];
        $cacheColor = $cacheStatus === 'healthy' ? 'green' : 'red';
        $this->line("<fg={$cacheColor}>Cache: {$cacheStatus}</>");
        
        // Disk space
        $diskStatus = $health['disk_space']['status'];
        $diskColor = $diskStatus === 'healthy' ? 'green' : ($diskStatus === 'warning' ? 'yellow' : 'red');
        $this->line("<fg={$diskColor}>Disk Space: {$diskStatus}</>");
        $this->line("  Usage: {$health['disk_space']['usage_percentage']}% ({$health['disk_space']['free_gb']}GB free)");
        
        // Memory usage
        $this->line("<fg=cyan>Memory Usage: {$health['memory_usage']['current_mb']}MB (Peak: {$health['memory_usage']['peak_mb']}MB)</>");
        
        // Active users
        $this->line("<fg=blue>Active Users (24h): {$health['active_users']}</>");
        
        // Call metrics
        $callMetrics = $health['call_metrics'];
        $this->line("<fg=magenta>Calls (24h): {$callMetrics['total_calls']} total, {$callMetrics['successful_calls']} successful</>");
        
        // Payment metrics
        $paymentMetrics = $health['payment_metrics'];
        $this->line("<fg=yellow>Payments (24h): {$paymentMetrics['total_transactions']} total, {$paymentMetrics['successful_payments']} successful</>");
    }

    /**
     * Display performance metrics
     */
    protected function displayPerformanceMetrics(array $performance): void
    {
        $this->info('=== Performance Metrics ===');
        
        $this->line("Response Times:");
        $this->line("  API Calls: {$performance['response_times']['api_calls']}ms");
        $this->line("  Web Requests: {$performance['response_times']['web_requests']}ms");
        $this->line("  Database Queries: {$performance['response_times']['database_queries']}ms");
        
        $this->line("Error Rates:");
        $this->line("  4xx Errors: {$performance['error_rates']['http_4xx_rate']}%");
        $this->line("  5xx Errors: {$performance['error_rates']['http_5xx_rate']}%");
        $this->line("  Exceptions: {$performance['error_rates']['exception_rate']}%");
        
        $this->line("Throughput:");
        $this->line("  Requests/min: {$performance['throughput']['requests_per_minute']}");
        $this->line("  API Calls/min: {$performance['throughput']['api_calls_per_minute']}");
        $this->line("  Concurrent Users: {$performance['throughput']['concurrent_users']}");
    }

    /**
     * Check for critical issues
     */
    protected function checkForCriticalIssues(array $health): array
    {
        $issues = [];
        
        if ($health['database']['status'] !== 'healthy') {
            $issues[] = 'Database is unhealthy';
        }
        
        if ($health['cache']['status'] !== 'healthy') {
            $issues[] = 'Cache system is unhealthy';
        }
        
        if ($health['disk_space']['status'] === 'critical') {
            $issues[] = "Disk space critically low ({$health['disk_space']['usage_percentage']}% used)";
        }
        
        // Check if database response time is too high
        if (isset($health['database']['response_time_ms']) && $health['database']['response_time_ms'] > 1000) {
            $issues[] = "Database response time is high ({$health['database']['response_time_ms']}ms)";
        }
        
        return $issues;
    }

    /**
     * Send alerts for critical issues
     */
    protected function sendAlerts(array $issues): void
    {
        foreach ($issues as $issue) {
            Log::channel('alerts')->critical('System Health Alert', [
                'issue' => $issue,
                'timestamp' => now()->toISOString(),
                'server' => gethostname(),
            ]);
        }
        
        $this->info('Alerts sent for critical issues.');
    }
}