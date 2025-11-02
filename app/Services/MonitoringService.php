<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\User;
use App\Models\CallRecord;
use App\Models\PaymentTransaction;

class MonitoringService
{
    /**
     * Get system health metrics
     */
    public function getSystemHealth(): array
    {
        return [
            'database' => $this->checkDatabaseHealth(),
            'cache' => $this->checkCacheHealth(),
            'disk_space' => $this->checkDiskSpace(),
            'memory_usage' => $this->getMemoryUsage(),
            'active_users' => $this->getActiveUsersCount(),
            'call_metrics' => $this->getCallMetrics(),
            'payment_metrics' => $this->getPaymentMetrics(),
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Check database connectivity and performance
     */
    protected function checkDatabaseHealth(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $responseTime = (microtime(true) - $start) * 1000;

            return [
                'status' => 'healthy',
                'response_time_ms' => round($responseTime, 2),
                'connections' => $this->getDatabaseConnections(),
            ];
        } catch (\Exception $e) {
            Log::error('Database health check failed', ['error' => $e->getMessage()]);
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check cache system health
     */
    protected function checkCacheHealth(): array
    {
        try {
            $testKey = 'health_check_' . time();
            $testValue = 'test_value';
            
            Cache::put($testKey, $testValue, 60);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);

            return [
                'status' => $retrieved === $testValue ? 'healthy' : 'unhealthy',
                'driver' => config('cache.default'),
            ];
        } catch (\Exception $e) {
            Log::error('Cache health check failed', ['error' => $e->getMessage()]);
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check available disk space
     */
    protected function checkDiskSpace(): array
    {
        $path = storage_path();
        $totalBytes = disk_total_space($path);
        $freeBytes = disk_free_space($path);
        $usedBytes = $totalBytes - $freeBytes;
        $usagePercentage = ($usedBytes / $totalBytes) * 100;

        return [
            'total_gb' => round($totalBytes / 1024 / 1024 / 1024, 2),
            'free_gb' => round($freeBytes / 1024 / 1024 / 1024, 2),
            'used_gb' => round($usedBytes / 1024 / 1024 / 1024, 2),
            'usage_percentage' => round($usagePercentage, 2),
            'status' => $usagePercentage > 90 ? 'critical' : ($usagePercentage > 80 ? 'warning' : 'healthy'),
        ];
    }

    /**
     * Get memory usage information
     */
    protected function getMemoryUsage(): array
    {
        return [
            'current_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'limit_mb' => ini_get('memory_limit'),
        ];
    }

    /**
     * Get active users count
     */
    protected function getActiveUsersCount(): int
    {
        return User::where('last_login_at', '>=', now()->subHours(24))->count();
    }

    /**
     * Get call metrics for the last 24 hours
     */
    protected function getCallMetrics(): array
    {
        $since = now()->subHours(24);
        
        return [
            'total_calls' => CallRecord::where('created_at', '>=', $since)->count(),
            'successful_calls' => CallRecord::where('created_at', '>=', $since)
                ->where('status', 'completed')->count(),
            'failed_calls' => CallRecord::where('created_at', '>=', $since)
                ->where('status', 'failed')->count(),
            'total_duration_minutes' => CallRecord::where('created_at', '>=', $since)
                ->where('status', 'completed')->sum('duration'),
            'average_duration_seconds' => CallRecord::where('created_at', '>=', $since)
                ->where('status', 'completed')->avg('duration'),
        ];
    }

    /**
     * Get payment metrics for the last 24 hours
     */
    protected function getPaymentMetrics(): array
    {
        $since = now()->subHours(24);
        
        return [
            'total_transactions' => PaymentTransaction::where('created_at', '>=', $since)->count(),
            'successful_payments' => PaymentTransaction::where('created_at', '>=', $since)
                ->where('status', 'completed')->count(),
            'failed_payments' => PaymentTransaction::where('created_at', '>=', $since)
                ->where('status', 'failed')->count(),
            'total_amount' => PaymentTransaction::where('created_at', '>=', $since)
                ->where('status', 'completed')->sum('amount'),
        ];
    }

    /**
     * Get database connection count
     */
    protected function getDatabaseConnections(): int
    {
        try {
            $result = DB::select('SHOW STATUS LIKE "Threads_connected"');
            return $result[0]->Value ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Log system metrics
     */
    public function logSystemMetrics(): void
    {
        $metrics = $this->getSystemHealth();
        
        Log::channel('monitoring')->info('System Health Check', $metrics);
        
        // Alert on critical conditions
        if ($metrics['disk_space']['status'] === 'critical') {
            Log::channel('alerts')->critical('Disk space critically low', [
                'usage_percentage' => $metrics['disk_space']['usage_percentage'],
                'free_gb' => $metrics['disk_space']['free_gb'],
            ]);
        }
        
        if ($metrics['database']['status'] === 'unhealthy') {
            Log::channel('alerts')->critical('Database health check failed', [
                'error' => $metrics['database']['error'] ?? 'Unknown error',
            ]);
        }
    }

    /**
     * Get application performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        return [
            'response_times' => $this->getAverageResponseTimes(),
            'error_rates' => $this->getErrorRates(),
            'throughput' => $this->getThroughputMetrics(),
        ];
    }

    /**
     * Get average response times by endpoint
     */
    protected function getAverageResponseTimes(): array
    {
        // This would typically integrate with APM tools
        // For now, return placeholder data
        return [
            'api_calls' => 150, // ms
            'web_requests' => 200, // ms
            'database_queries' => 25, // ms
        ];
    }

    /**
     * Get error rates
     */
    protected function getErrorRates(): array
    {
        // This would typically integrate with error tracking
        return [
            'http_4xx_rate' => 2.5, // percentage
            'http_5xx_rate' => 0.1, // percentage
            'exception_rate' => 0.05, // percentage
        ];
    }

    /**
     * Get throughput metrics
     */
    protected function getThroughputMetrics(): array
    {
        return [
            'requests_per_minute' => 120,
            'api_calls_per_minute' => 80,
            'concurrent_users' => 15,
        ];
    }
}