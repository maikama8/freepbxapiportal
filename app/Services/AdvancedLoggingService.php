<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdvancedLoggingService
{
    protected array $logChannels = [
        'billing' => 'billing',
        'did_management' => 'did',
        'real_time_billing' => 'realtime_billing',
        'cron_monitoring' => 'cron',
        'performance' => 'performance',
        'security' => 'security',
        'api' => 'api',
        'freepbx_integration' => 'freepbx',
        'payment_processing' => 'payments',
        'system_alerts' => 'alerts'
    ];

    /**
     * Log billing-related events
     */
    public function logBilling(string $level, string $message, array $context = []): void
    {
        $context = $this->enrichContext($context, 'billing');
        Log::channel('billing')->$level($message, $context);
        
        // Also log critical billing events to alerts channel
        if (in_array($level, ['critical', 'emergency', 'alert'])) {
            Log::channel('alerts')->$level("[BILLING] {$message}", $context);
        }
    }

    /**
     * Log DID management events
     */
    public function logDidManagement(string $level, string $message, array $context = []): void
    {
        $context = $this->enrichContext($context, 'did_management');
        Log::channel('did')->$level($message, $context);
        
        if (in_array($level, ['critical', 'emergency', 'alert'])) {
            Log::channel('alerts')->$level("[DID] {$message}", $context);
        }
    }

    /**
     * Log real-time billing events
     */
    public function logRealTimeBilling(string $level, string $message, array $context = []): void
    {
        $context = $this->enrichContext($context, 'real_time_billing');
        Log::channel('realtime_billing')->$level($message, $context);
        
        if (in_array($level, ['critical', 'emergency', 'alert'])) {
            Log::channel('alerts')->$level("[REALTIME_BILLING] {$message}", $context);
        }
    }

    /**
     * Log cron job monitoring events
     */
    public function logCronMonitoring(string $level, string $message, array $context = []): void
    {
        $context = $this->enrichContext($context, 'cron_monitoring');
        Log::channel('cron')->$level($message, $context);
        
        if (in_array($level, ['critical', 'emergency', 'alert'])) {
            Log::channel('alerts')->$level("[CRON] {$message}", $context);
        }
    }

    /**
     * Log performance-related events
     */
    public function logPerformance(string $level, string $message, array $context = []): void
    {
        $context = $this->enrichContext($context, 'performance');
        Log::channel('performance')->$level($message, $context);
        
        if (in_array($level, ['critical', 'emergency', 'alert'])) {
            Log::channel('alerts')->$level("[PERFORMANCE] {$message}", $context);
        }
    }

    /**
     * Log security events
     */
    public function logSecurity(string $level, string $message, array $context = []): void
    {
        $context = $this->enrichContext($context, 'security');
        Log::channel('security')->$level($message, $context);
        
        // All security events should also go to alerts
        Log::channel('alerts')->$level("[SECURITY] {$message}", $context);
    }

    /**
     * Log API events
     */
    public function logApi(string $level, string $message, array $context = []): void
    {
        $context = $this->enrichContext($context, 'api');
        Log::channel('api')->$level($message, $context);
        
        if (in_array($level, ['critical', 'emergency', 'alert'])) {
            Log::channel('alerts')->$level("[API] {$message}", $context);
        }
    }

    /**
     * Log FreePBX integration events
     */
    public function logFreePBXIntegration(string $level, string $message, array $context = []): void
    {
        $context = $this->enrichContext($context, 'freepbx_integration');
        Log::channel('freepbx')->$level($message, $context);
        
        if (in_array($level, ['critical', 'emergency', 'alert'])) {
            Log::channel('alerts')->$level("[FREEPBX] {$message}", $context);
        }
    }

    /**
     * Log payment processing events
     */
    public function logPaymentProcessing(string $level, string $message, array $context = []): void
    {
        $context = $this->enrichContext($context, 'payment_processing');
        Log::channel('payments')->$level($message, $context);
        
        if (in_array($level, ['critical', 'emergency', 'alert'])) {
            Log::channel('alerts')->$level("[PAYMENTS] {$message}", $context);
        }
    }

    /**
     * Log system alerts
     */
    public function logSystemAlert(string $level, string $message, array $context = []): void
    {
        $context = $this->enrichContext($context, 'system_alerts');
        Log::channel('alerts')->$level($message, $context);
    }

    /**
     * Log call events with detailed context
     */
    public function logCallEvent(string $event, array $callData, array $additionalContext = []): void
    {
        $context = array_merge([
            'event_type' => 'call_event',
            'call_event' => $event,
            'call_id' => $callData['call_id'] ?? null,
            'user_id' => $callData['user_id'] ?? null,
            'destination' => $callData['destination'] ?? null,
            'duration' => $callData['duration'] ?? null,
            'cost' => $callData['cost'] ?? null,
            'billing_increment' => $callData['billing_increment'] ?? null,
            'rate_per_minute' => $callData['rate_per_minute'] ?? null
        ], $additionalContext);

        $message = "Call event: {$event}";
        if (isset($callData['call_id'])) {
            $message .= " (Call ID: {$callData['call_id']})";
        }

        $this->logBilling('info', $message, $context);
    }

    /**
     * Log DID events with detailed context
     */
    public function logDidEvent(string $event, array $didData, array $additionalContext = []): void
    {
        $context = array_merge([
            'event_type' => 'did_event',
            'did_event' => $event,
            'did_number' => $didData['did_number'] ?? null,
            'country_code' => $didData['country_code'] ?? null,
            'user_id' => $didData['user_id'] ?? null,
            'status' => $didData['status'] ?? null,
            'monthly_cost' => $didData['monthly_cost'] ?? null,
            'setup_cost' => $didData['setup_cost'] ?? null
        ], $additionalContext);

        $message = "DID event: {$event}";
        if (isset($didData['did_number'])) {
            $message .= " (DID: {$didData['did_number']})";
        }

        $this->logDidManagement('info', $message, $context);
    }

    /**
     * Log payment events with detailed context
     */
    public function logPaymentEvent(string $event, array $paymentData, array $additionalContext = []): void
    {
        $context = array_merge([
            'event_type' => 'payment_event',
            'payment_event' => $event,
            'transaction_id' => $paymentData['transaction_id'] ?? null,
            'user_id' => $paymentData['user_id'] ?? null,
            'amount' => $paymentData['amount'] ?? null,
            'currency' => $paymentData['currency'] ?? null,
            'gateway' => $paymentData['gateway'] ?? null,
            'status' => $paymentData['status'] ?? null
        ], $additionalContext);

        $message = "Payment event: {$event}";
        if (isset($paymentData['transaction_id'])) {
            $message .= " (Transaction: {$paymentData['transaction_id']})";
        }

        $this->logPaymentProcessing('info', $message, $context);
    }

    /**
     * Log balance events with detailed context
     */
    public function logBalanceEvent(string $event, array $balanceData, array $additionalContext = []): void
    {
        $context = array_merge([
            'event_type' => 'balance_event',
            'balance_event' => $event,
            'user_id' => $balanceData['user_id'] ?? null,
            'amount' => $balanceData['amount'] ?? null,
            'type' => $balanceData['type'] ?? null,
            'balance_before' => $balanceData['balance_before'] ?? null,
            'balance_after' => $balanceData['balance_after'] ?? null,
            'reference_type' => $balanceData['reference_type'] ?? null,
            'reference_id' => $balanceData['reference_id'] ?? null
        ], $additionalContext);

        $message = "Balance event: {$event}";
        if (isset($balanceData['user_id'])) {
            $message .= " (User: {$balanceData['user_id']})";
        }

        $this->logBilling('info', $message, $context);
    }

    /**
     * Log cron job execution with performance metrics
     */
    public function logCronExecution(string $jobName, array $executionData, array $additionalContext = []): void
    {
        $context = array_merge([
            'event_type' => 'cron_execution',
            'job_name' => $jobName,
            'execution_id' => $executionData['execution_id'] ?? null,
            'status' => $executionData['status'] ?? null,
            'duration_seconds' => $executionData['duration_seconds'] ?? null,
            'memory_usage' => $executionData['memory_usage'] ?? null,
            'records_processed' => $executionData['records_processed'] ?? null,
            'errors_count' => $executionData['errors_count'] ?? null
        ], $additionalContext);

        $level = ($executionData['status'] ?? 'unknown') === 'completed' ? 'info' : 'error';
        $message = "Cron job execution: {$jobName} - {$executionData['status']}";

        $this->logCronMonitoring($level, $message, $context);
    }

    /**
     * Log performance metrics
     */
    public function logPerformanceMetrics(string $operation, array $metrics, array $additionalContext = []): void
    {
        $context = array_merge([
            'event_type' => 'performance_metrics',
            'operation' => $operation,
            'execution_time_ms' => $metrics['execution_time_ms'] ?? null,
            'memory_usage_mb' => $metrics['memory_usage_mb'] ?? null,
            'database_queries' => $metrics['database_queries'] ?? null,
            'cache_hits' => $metrics['cache_hits'] ?? null,
            'cache_misses' => $metrics['cache_misses'] ?? null
        ], $additionalContext);

        $message = "Performance metrics for: {$operation}";
        $this->logPerformance('info', $message, $context);
    }

    /**
     * Log security events with threat assessment
     */
    public function logSecurityEvent(string $event, array $securityData, array $additionalContext = []): void
    {
        $context = array_merge([
            'event_type' => 'security_event',
            'security_event' => $event,
            'user_id' => $securityData['user_id'] ?? null,
            'ip_address' => $securityData['ip_address'] ?? null,
            'user_agent' => $securityData['user_agent'] ?? null,
            'threat_level' => $securityData['threat_level'] ?? 'low',
            'action_taken' => $securityData['action_taken'] ?? null,
            'additional_data' => $securityData['additional_data'] ?? null
        ], $additionalContext);

        $level = match($securityData['threat_level'] ?? 'low') {
            'critical' => 'critical',
            'high' => 'error',
            'medium' => 'warning',
            default => 'info'
        };

        $message = "Security event: {$event}";
        if (isset($securityData['ip_address'])) {
            $message .= " from {$securityData['ip_address']}";
        }

        $this->logSecurity($level, $message, $context);
    }

    /**
     * Log API requests with detailed information
     */
    public function logApiRequest(string $method, string $endpoint, array $requestData, array $additionalContext = []): void
    {
        $context = array_merge([
            'event_type' => 'api_request',
            'http_method' => $method,
            'endpoint' => $endpoint,
            'user_id' => $requestData['user_id'] ?? null,
            'ip_address' => $requestData['ip_address'] ?? null,
            'user_agent' => $requestData['user_agent'] ?? null,
            'response_status' => $requestData['response_status'] ?? null,
            'response_time_ms' => $requestData['response_time_ms'] ?? null,
            'request_size' => $requestData['request_size'] ?? null,
            'response_size' => $requestData['response_size'] ?? null
        ], $additionalContext);

        $level = ($requestData['response_status'] ?? 200) >= 400 ? 'warning' : 'info';
        $message = "{$method} {$endpoint} - {$requestData['response_status']}";

        $this->logApi($level, $message, $context);
    }

    /**
     * Enrich context with common metadata
     */
    protected function enrichContext(array $context, string $category): array
    {
        return array_merge([
            'timestamp' => Carbon::now()->toISOString(),
            'category' => $category,
            'server' => gethostname(),
            'process_id' => getmypid(),
            'memory_usage' => memory_get_usage(true),
            'request_id' => request()->header('X-Request-ID') ?? uniqid('req_'),
            'user_id' => auth()->id(),
            'session_id' => session()->getId()
        ], $context);
    }

    /**
     * Get log statistics for monitoring dashboard
     */
    public function getLogStatistics(int $hours = 24): array
    {
        $statistics = [];
        
        foreach ($this->logChannels as $category => $channel) {
            $statistics[$category] = $this->getChannelStatistics($channel, $hours);
        }
        
        return $statistics;
    }

    /**
     * Get statistics for a specific log channel
     */
    protected function getChannelStatistics(string $channel, int $hours): array
    {
        // This would require a log aggregation system like ELK stack
        // For now, we'll return simulated statistics
        return [
            'total_entries' => rand(100, 1000),
            'error_entries' => rand(0, 50),
            'warning_entries' => rand(0, 100),
            'info_entries' => rand(50, 800),
            'last_entry_time' => Carbon::now()->subMinutes(rand(1, 60))->toISOString()
        ];
    }

    /**
     * Search logs by criteria
     */
    public function searchLogs(array $criteria): array
    {
        // This would integrate with your log aggregation system
        // For now, we'll return a simulated search result
        return [
            'total_matches' => rand(0, 100),
            'entries' => [],
            'search_time_ms' => rand(10, 500)
        ];
    }

    /**
     * Generate log analysis report
     */
    public function generateLogAnalysisReport(int $days = 7): array
    {
        $report = [
            'period' => [
                'start' => Carbon::now()->subDays($days)->toISOString(),
                'end' => Carbon::now()->toISOString(),
                'days' => $days
            ],
            'summary' => [],
            'trends' => [],
            'top_errors' => [],
            'performance_insights' => []
        ];

        foreach ($this->logChannels as $category => $channel) {
            $report['summary'][$category] = $this->getChannelStatistics($channel, $days * 24);
        }

        return $report;
    }

    /**
     * Clean up old log files
     */
    public function cleanupOldLogs(int $daysToKeep = 30): array
    {
        $cleaned = [];
        $logPath = storage_path('logs');
        
        if (is_dir($logPath)) {
            $files = glob($logPath . '/*.log');
            
            foreach ($files as $file) {
                $fileAge = (time() - filemtime($file)) / (24 * 3600); // Age in days
                
                if ($fileAge > $daysToKeep) {
                    if (unlink($file)) {
                        $cleaned[] = basename($file);
                    }
                }
            }
        }
        
        return [
            'cleaned_files' => $cleaned,
            'cleaned_count' => count($cleaned)
        ];
    }

    /**
     * Archive logs for long-term storage
     */
    public function archiveLogs(int $daysOld = 7): array
    {
        $archived = [];
        $logPath = storage_path('logs');
        $archivePath = storage_path('logs/archive');
        
        if (!is_dir($archivePath)) {
            mkdir($archivePath, 0755, true);
        }
        
        if (is_dir($logPath)) {
            $files = glob($logPath . '/*.log');
            
            foreach ($files as $file) {
                $fileAge = (time() - filemtime($file)) / (24 * 3600);
                
                if ($fileAge > $daysOld && $fileAge <= 30) { // Archive files 7-30 days old
                    $archiveFile = $archivePath . '/' . basename($file) . '.' . date('Y-m-d', filemtime($file)) . '.gz';
                    
                    if (file_put_contents($archiveFile, gzencode(file_get_contents($file)))) {
                        unlink($file);
                        $archived[] = basename($file);
                    }
                }
            }
        }
        
        return [
            'archived_files' => $archived,
            'archived_count' => count($archived)
        ];
    }
}