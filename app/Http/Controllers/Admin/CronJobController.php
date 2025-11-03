<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CronJobManager;
use App\Services\MonitoringService;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CronJobController extends Controller
{
    protected CronJobManager $cronJobManager;
    protected MonitoringService $monitoringService;

    public function __construct(
        CronJobManager $cronJobManager,
        MonitoringService $monitoringService
    ) {
        $this->cronJobManager = $cronJobManager;
        $this->monitoringService = $monitoringService;
    }

    /**
     * Display the cron job management dashboard
     */
    public function index()
    {
        $runningJobs = $this->cronJobManager->getRunningJobs();
        $recentStats = $this->cronJobManager->getJobStatistics(null, 1); // Last 24 hours
        $systemHealth = $this->cronJobManager->getSystemHealth();

        return view('admin.cron-jobs.index', compact(
            'runningJobs',
            'recentStats', 
            'systemHealth'
        ));
    }

    /**
     * Get real-time job status via AJAX
     */
    public function status(): JsonResponse
    {
        $runningJobs = $this->cronJobManager->getRunningJobs();
        $systemHealth = $this->cronJobManager->getSystemHealth();

        // Format running jobs for display
        $formattedJobs = array_map(function ($job) {
            $startTime = Carbon::parse($job->started_at);
            return [
                'execution_id' => $job->execution_id,
                'job_name' => $job->job_name,
                'started_at' => $startTime->format('Y-m-d H:i:s'),
                'runtime' => $startTime->diffForHumans(null, true),
                'runtime_seconds' => $startTime->diffInSeconds(),
                'pid' => $job->pid,
                'memory_mb' => $job->memory_start ? round($job->memory_start / 1024 / 1024, 2) : null,
            ];
        }, $runningJobs);

        return response()->json([
            'running_jobs' => $formattedJobs,
            'system_health' => $systemHealth,
            'timestamp' => Carbon::now()->toISOString(),
        ]);
    }

    /**
     * Get job execution history
     */
    public function history(Request $request): JsonResponse
    {
        $jobName = $request->get('job_name');
        $limit = min((int) $request->get('limit', 50), 200); // Max 200 records
        $page = (int) $request->get('page', 1);
        $offset = ($page - 1) * $limit;

        $history = $this->cronJobManager->getJobHistory($jobName, $limit + $offset);
        $paginatedHistory = array_slice($history, $offset, $limit);

        // Format history for display
        $formattedHistory = array_map(function ($job) {
            return [
                'execution_id' => $job->execution_id,
                'job_name' => $job->job_name,
                'status' => $job->status,
                'started_at' => Carbon::parse($job->started_at)->format('Y-m-d H:i:s'),
                'completed_at' => $job->completed_at ? Carbon::parse($job->completed_at)->format('Y-m-d H:i:s') : null,
                'duration_seconds' => $job->duration_seconds,
                'memory_peak_mb' => $job->memory_peak ? round($job->memory_peak / 1024 / 1024, 2) : null,
                'result' => $job->result ? json_decode($job->result, true) : null,
            ];
        }, $paginatedHistory);

        return response()->json([
            'history' => $formattedHistory,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => count($history),
                'has_more' => count($history) > ($offset + $limit),
            ],
        ]);
    }

    /**
     * Get job statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $days = min((int) $request->get('days', 7), 30); // Max 30 days
        $jobName = $request->get('job_name');

        $stats = $this->cronJobManager->getJobStatistics($jobName, $days);

        // Format statistics for display
        $formattedStats = array_map(function ($stat) {
            $successRate = $stat->total_executions > 0 
                ? round(($stat->successful_executions / $stat->total_executions) * 100, 1) 
                : 0;

            return [
                'job_name' => $stat->job_name,
                'total_executions' => $stat->total_executions,
                'successful_executions' => $stat->successful_executions,
                'failed_executions' => $stat->failed_executions,
                'success_rate' => $successRate,
                'avg_duration' => $stat->avg_duration ? round($stat->avg_duration, 2) : null,
                'max_duration' => $stat->max_duration,
                'min_duration' => $stat->min_duration,
                'avg_memory_mb' => $stat->avg_memory_usage ? round($stat->avg_memory_usage / 1024 / 1024, 2) : null,
                'max_memory_mb' => $stat->max_memory_usage ? round($stat->max_memory_usage / 1024 / 1024, 2) : null,
            ];
        }, $stats);

        return response()->json([
            'statistics' => $formattedStats,
            'period_days' => $days,
        ]);
    }

    /**
     * Kill a stuck job
     */
    public function killJob(Request $request): JsonResponse
    {
        $executionId = $request->get('execution_id');
        
        if (!$executionId) {
            return response()->json(['error' => 'Execution ID is required'], 400);
        }

        try {
            $killedJobs = $this->cronJobManager->killStuckJobs(0); // Kill immediately
            
            $wasKilled = in_array($executionId, $killedJobs);
            
            return response()->json([
                'success' => $wasKilled,
                'message' => $wasKilled ? 'Job killed successfully' : 'Job not found or already completed',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to kill job: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clean up old job records
     */
    public function cleanup(Request $request): JsonResponse
    {
        $days = min((int) $request->get('days', 30), 90); // Max 90 days
        
        try {
            $deletedCount = $this->cronJobManager->cleanupOldRecords($days);
            
            return response()->json([
                'success' => true,
                'deleted_count' => $deletedCount,
                'message' => "Cleaned up {$deletedCount} old records",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to cleanup records: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get job execution details
     */
    public function jobDetails(string $executionId): JsonResponse
    {
        try {
            // Try to get from cache first (for running jobs)
            $cacheKey = 'cron_job_' . $executionId;
            $jobData = cache()->get($cacheKey);
            
            if (!$jobData) {
                // Get from database
                $jobData = \DB::table('cron_job_executions')
                    ->where('execution_id', $executionId)
                    ->first();
                
                if (!$jobData) {
                    return response()->json(['error' => 'Job not found'], 404);
                }
                
                // Convert to array for consistency
                $jobData = (array) $jobData;
                $jobData['metadata'] = json_decode($jobData['metadata'] ?? '{}', true);
                $jobData['result'] = json_decode($jobData['result'] ?? '{}', true);
            }

            // Calculate additional metrics
            if (isset($jobData['started_at'])) {
                $startTime = Carbon::parse($jobData['started_at']);
                $jobData['runtime_human'] = $startTime->diffForHumans(null, true);
                
                if ($jobData['status'] === 'running') {
                    $jobData['current_runtime_seconds'] = $startTime->diffInSeconds();
                }
            }

            if (isset($jobData['memory_start'], $jobData['memory_peak'])) {
                $jobData['memory_usage_mb'] = round(($jobData['memory_peak'] - $jobData['memory_start']) / 1024 / 1024, 2);
            }

            return response()->json(['job' => $jobData]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get job details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available job names for filtering
     */
    public function jobNames(): JsonResponse
    {
        $jobNames = \DB::table('cron_job_executions')
            ->select('job_name')
            ->distinct()
            ->orderBy('job_name')
            ->pluck('job_name');

        return response()->json(['job_names' => $jobNames]);
    }

    /**
     * Advanced automation monitoring dashboard
     */
    public function monitoringDashboard(): View
    {
        $systemHealth = $this->getSystemHealthOverview();
        $automationMetrics = $this->getAutomationMetrics();
        $alertsConfig = $this->getAlertsConfiguration();
        
        return view('admin.automation.monitoring', compact(
            'systemHealth',
            'automationMetrics', 
            'alertsConfig'
        ));
    }

    /**
     * Get comprehensive automation monitoring data
     */
    public function getMonitoringData(): JsonResponse
    {
        try {
            $data = [
                'system_health' => $this->getSystemHealthOverview(),
                'automation_metrics' => $this->getAutomationMetrics(),
                'job_performance' => $this->getJobPerformanceMetrics(),
                'failure_analysis' => $this->getFailureAnalysis(),
                'resource_usage' => $this->getResourceUsageMetrics(),
                'alerts' => $this->getActiveAlerts(),
                'trends' => $this->getPerformanceTrends(),
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve monitoring data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get automation performance analytics
     */
    public function getPerformanceAnalytics(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'required|string|in:hour,day,week,month',
            'job_name' => 'nullable|string',
            'metric' => 'required|string|in:execution_time,success_rate,resource_usage,frequency',
        ]);

        try {
            $analytics = $this->generatePerformanceAnalytics(
                $request->period,
                $request->job_name,
                $request->metric
            );

            return response()->json([
                'success' => true,
                'analytics' => $analytics,
                'generated_at' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate performance analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Configure automation alerts
     */
    public function configureAlerts(Request $request): JsonResponse
    {
        $request->validate([
            'alerts' => 'required|array',
            'alerts.*.type' => 'required|string|in:failure_rate,execution_time,resource_usage,missed_execution',
            'alerts.*.threshold' => 'required|numeric',
            'alerts.*.enabled' => 'required|boolean',
            'alerts.*.notification_channels' => 'required|array',
            'alerts.*.notification_channels.*' => 'string|in:email,slack,webhook',
        ]);

        try {
            DB::beginTransaction();

            foreach ($request->alerts as $alertConfig) {
                $alertKey = 'automation_alert_' . $alertConfig['type'];
                
                SystemSetting::updateOrCreate(
                    ['key' => $alertKey],
                    ['value' => json_encode($alertConfig)]
                );
            }

            // Log the configuration change
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'automation_alerts_configured',
                'description' => 'Updated automation monitoring alerts configuration',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'alerts_count' => count($request->alerts),
                ]
            ]);

            // Clear alerts cache
            Cache::forget('automation_alerts_config');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Automation alerts configured successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to configure alerts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test automation alert
     */
    public function testAlert(Request $request): JsonResponse
    {
        $request->validate([
            'alert_type' => 'required|string',
            'test_data' => 'required|array',
        ]);

        try {
            $alertResult = $this->evaluateAlert($request->alert_type, $request->test_data);

            return response()->json([
                'success' => true,
                'alert_triggered' => $alertResult['triggered'],
                'alert_message' => $alertResult['message'],
                'threshold_comparison' => $alertResult['comparison']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to test alert',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get automation health report
     */
    public function getHealthReport(): JsonResponse
    {
        try {
            $report = [
                'overall_health' => $this->calculateOverallHealth(),
                'critical_issues' => $this->getCriticalIssues(),
                'performance_summary' => $this->getPerformanceSummary(),
                'recommendations' => $this->getHealthRecommendations(),
                'system_status' => $this->getSystemStatusSummary(),
            ];

            return response()->json([
                'success' => true,
                'report' => $report,
                'generated_at' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate health report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export automation monitoring data
     */
    public function exportMonitoringData(Request $request)
    {
        $request->validate([
            'format' => 'required|string|in:json,csv',
            'period' => 'required|string|in:day,week,month',
            'include_logs' => 'boolean',
        ]);

        try {
            $data = $this->prepareExportData($request->period, $request->boolean('include_logs'));
            
            $filename = 'automation_monitoring_' . $request->period . '_' . now()->format('Y-m-d_H-i-s');
            
            if ($request->format === 'json') {
                return response()->json($data)
                    ->header('Content-Type', 'application/json')
                    ->header('Content-Disposition', "attachment; filename=\"{$filename}.json\"");
            } else {
                return $this->exportToCsv($data, $filename);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export monitoring data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Trigger manual automation health check
     */
    public function triggerHealthCheck(): JsonResponse
    {
        try {
            $healthCheck = $this->performComprehensiveHealthCheck();
            
            // Log the manual health check
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'automation_health_check_triggered',
                'description' => 'Manually triggered automation health check',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'metadata' => [
                    'health_score' => $healthCheck['overall_score'],
                    'issues_found' => count($healthCheck['issues']),
                ]
            ]);

            return response()->json([
                'success' => true,
                'health_check' => $healthCheck,
                'message' => 'Health check completed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to perform health check',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to get system health overview
     */
    private function getSystemHealthOverview(): array
    {
        return Cache::remember('automation_system_health', 60, function () {
            $runningJobs = $this->cronJobManager->getRunningJobs();
            $systemHealth = $this->cronJobManager->getSystemHealth();
            
            $recentFailures = DB::table('cron_job_executions')
                ->where('status', 'failed')
                ->where('started_at', '>=', now()->subHour())
                ->count();

            $stuckJobs = collect($runningJobs)->filter(function ($job) {
                return Carbon::parse($job->started_at)->diffInMinutes() > 60;
            })->count();

            return [
                'status' => $this->determineOverallStatus($systemHealth, $recentFailures, $stuckJobs),
                'running_jobs_count' => count($runningJobs),
                'recent_failures' => $recentFailures,
                'stuck_jobs' => $stuckJobs,
                'system_load' => $systemHealth['system_load'] ?? 0,
                'memory_usage' => $systemHealth['memory_usage_percent'] ?? 0,
                'disk_usage' => $systemHealth['disk_usage_percent'] ?? 0,
                'last_updated' => now()->toISOString(),
            ];
        });
    }

    /**
     * Helper method to get automation metrics
     */
    private function getAutomationMetrics(): array
    {
        return Cache::remember('automation_metrics', 300, function () {
            $last24Hours = now()->subDay();
            
            $metrics = DB::table('cron_job_executions')
                ->where('started_at', '>=', $last24Hours)
                ->selectRaw('
                    COUNT(*) as total_executions,
                    SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as successful_executions,
                    SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_executions,
                    AVG(duration_seconds) as avg_duration,
                    MAX(duration_seconds) as max_duration,
                    AVG(memory_peak) as avg_memory_usage
                ')
                ->first();

            $successRate = $metrics->total_executions > 0 
                ? ($metrics->successful_executions / $metrics->total_executions) * 100 
                : 100;

            return [
                'total_executions_24h' => $metrics->total_executions ?? 0,
                'success_rate_24h' => round($successRate, 2),
                'failed_executions_24h' => $metrics->failed_executions ?? 0,
                'avg_execution_time' => round($metrics->avg_duration ?? 0, 2),
                'max_execution_time' => $metrics->max_duration ?? 0,
                'avg_memory_usage_mb' => round(($metrics->avg_memory_usage ?? 0) / 1024 / 1024, 2),
            ];
        });
    }

    /**
     * Helper method to get job performance metrics
     */
    private function getJobPerformanceMetrics(): array
    {
        $jobMetrics = DB::table('cron_job_executions')
            ->where('started_at', '>=', now()->subWeek())
            ->groupBy('job_name')
            ->selectRaw('
                job_name,
                COUNT(*) as executions,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as successful,
                AVG(duration_seconds) as avg_duration,
                MAX(duration_seconds) as max_duration,
                AVG(memory_peak) as avg_memory
            ')
            ->get();

        return $jobMetrics->map(function ($job) {
            $successRate = $job->executions > 0 ? ($job->successful / $job->executions) * 100 : 0;
            
            return [
                'job_name' => $job->job_name,
                'executions' => $job->executions,
                'success_rate' => round($successRate, 2),
                'avg_duration' => round($job->avg_duration, 2),
                'max_duration' => $job->max_duration,
                'avg_memory_mb' => round(($job->avg_memory ?? 0) / 1024 / 1024, 2),
                'performance_score' => $this->calculatePerformanceScore($successRate, $job->avg_duration),
            ];
        })->toArray();
    }

    /**
     * Helper method to get failure analysis
     */
    private function getFailureAnalysis(): array
    {
        $failures = DB::table('cron_job_executions')
            ->where('status', 'failed')
            ->where('started_at', '>=', now()->subWeek())
            ->get();

        $failuresByJob = $failures->groupBy('job_name')->map->count();
        $failuresByHour = $failures->groupBy(function ($failure) {
            return Carbon::parse($failure->started_at)->format('H:00');
        })->map->count();

        $commonErrors = $failures->filter(function ($failure) {
            return !empty($failure->result);
        })->map(function ($failure) {
            $result = json_decode($failure->result, true);
            return $result['error'] ?? 'Unknown error';
        })->countBy()->take(5);

        return [
            'total_failures' => $failures->count(),
            'failures_by_job' => $failuresByJob->toArray(),
            'failures_by_hour' => $failuresByHour->toArray(),
            'common_errors' => $commonErrors->toArray(),
            'failure_trend' => $this->calculateFailureTrend($failures),
        ];
    }

    /**
     * Helper method to get resource usage metrics
     */
    private function getResourceUsageMetrics(): array
    {
        $resourceData = DB::table('cron_job_executions')
            ->where('started_at', '>=', now()->subDay())
            ->whereNotNull('memory_peak')
            ->selectRaw('
                AVG(memory_peak) as avg_memory,
                MAX(memory_peak) as max_memory,
                MIN(memory_peak) as min_memory,
                COUNT(*) as sample_count
            ')
            ->first();

        return [
            'avg_memory_mb' => round(($resourceData->avg_memory ?? 0) / 1024 / 1024, 2),
            'max_memory_mb' => round(($resourceData->max_memory ?? 0) / 1024 / 1024, 2),
            'min_memory_mb' => round(($resourceData->min_memory ?? 0) / 1024 / 1024, 2),
            'sample_count' => $resourceData->sample_count ?? 0,
            'system_resources' => $this->monitoringService->getSystemResources(),
        ];
    }

    /**
     * Helper method to get active alerts
     */
    private function getActiveAlerts(): array
    {
        $alertsConfig = $this->getAlertsConfiguration();
        $activeAlerts = [];

        foreach ($alertsConfig as $alertType => $config) {
            if (!$config['enabled']) {
                continue;
            }

            $alertData = $this->checkAlert($alertType, $config);
            if ($alertData['triggered']) {
                $activeAlerts[] = $alertData;
            }
        }

        return $activeAlerts;
    }

    /**
     * Helper method to get performance trends
     */
    private function getPerformanceTrends(): array
    {
        $trends = [];
        $periods = ['1 hour', '6 hours', '1 day', '1 week'];

        foreach ($periods as $period) {
            $startTime = now()->sub(new \DateInterval('PT' . str_replace(' ', '', strtoupper($period))));
            
            $metrics = DB::table('cron_job_executions')
                ->where('started_at', '>=', $startTime)
                ->selectRaw('
                    COUNT(*) as executions,
                    AVG(duration_seconds) as avg_duration,
                    SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as successful
                ')
                ->first();

            $successRate = $metrics->executions > 0 
                ? ($metrics->successful / $metrics->executions) * 100 
                : 100;

            $trends[$period] = [
                'executions' => $metrics->executions ?? 0,
                'success_rate' => round($successRate, 2),
                'avg_duration' => round($metrics->avg_duration ?? 0, 2),
            ];
        }

        return $trends;
    }

    /**
     * Helper method to get alerts configuration
     */
    private function getAlertsConfiguration(): array
    {
        return Cache::remember('automation_alerts_config', 300, function () {
            $alertSettings = SystemSetting::where('key', 'like', 'automation_alert_%')
                ->pluck('value', 'key');

            $defaultAlerts = [
                'failure_rate' => [
                    'enabled' => true,
                    'threshold' => 10.0, // 10% failure rate
                    'notification_channels' => ['email'],
                ],
                'execution_time' => [
                    'enabled' => true,
                    'threshold' => 300, // 5 minutes
                    'notification_channels' => ['email'],
                ],
                'resource_usage' => [
                    'enabled' => true,
                    'threshold' => 80.0, // 80% memory usage
                    'notification_channels' => ['email'],
                ],
                'missed_execution' => [
                    'enabled' => true,
                    'threshold' => 2, // 2 missed executions
                    'notification_channels' => ['email'],
                ],
            ];

            foreach ($alertSettings as $key => $value) {
                $alertType = str_replace('automation_alert_', '', $key);
                $defaultAlerts[$alertType] = json_decode($value, true);
            }

            return $defaultAlerts;
        });
    }

    /**
     * Helper method to determine overall system status
     */
    private function determineOverallStatus(array $systemHealth, int $recentFailures, int $stuckJobs): string
    {
        if ($stuckJobs > 0 || $recentFailures > 5) {
            return 'critical';
        }

        if ($recentFailures > 2 || ($systemHealth['memory_usage_percent'] ?? 0) > 90) {
            return 'warning';
        }

        return 'healthy';
    }

    /**
     * Helper method to calculate performance score
     */
    private function calculatePerformanceScore(float $successRate, float $avgDuration): int
    {
        $successScore = $successRate;
        $durationScore = max(0, 100 - ($avgDuration / 10)); // Penalize long durations
        
        return round(($successScore + $durationScore) / 2);
    }

    /**
     * Helper method to calculate failure trend
     */
    private function calculateFailureTrend($failures): string
    {
        if ($failures->count() < 2) {
            return 'stable';
        }

        $recent = $failures->where('started_at', '>=', now()->subHours(12))->count();
        $older = $failures->where('started_at', '<', now()->subHours(12))->count();

        if ($recent > $older * 1.5) {
            return 'increasing';
        } elseif ($recent < $older * 0.5) {
            return 'decreasing';
        }

        return 'stable';
    }

    /**
     * Helper method to check individual alert
     */
    private function checkAlert(string $alertType, array $config): array
    {
        switch ($alertType) {
            case 'failure_rate':
                return $this->checkFailureRateAlert($config);
            case 'execution_time':
                return $this->checkExecutionTimeAlert($config);
            case 'resource_usage':
                return $this->checkResourceUsageAlert($config);
            case 'missed_execution':
                return $this->checkMissedExecutionAlert($config);
            default:
                return ['triggered' => false, 'message' => 'Unknown alert type'];
        }
    }

    /**
     * Helper method to check failure rate alert
     */
    private function checkFailureRateAlert(array $config): array
    {
        $metrics = $this->getAutomationMetrics();
        $failureRate = 100 - $metrics['success_rate_24h'];
        
        $triggered = $failureRate > $config['threshold'];
        
        return [
            'type' => 'failure_rate',
            'triggered' => $triggered,
            'current_value' => $failureRate,
            'threshold' => $config['threshold'],
            'message' => $triggered 
                ? "Failure rate ({$failureRate}%) exceeds threshold ({$config['threshold']}%)"
                : "Failure rate is within acceptable limits",
            'severity' => $triggered ? ($failureRate > $config['threshold'] * 2 ? 'critical' : 'warning') : 'info',
        ];
    }

    /**
     * Helper method to check execution time alert
     */
    private function checkExecutionTimeAlert(array $config): array
    {
        $maxDuration = DB::table('cron_job_executions')
            ->where('started_at', '>=', now()->subHour())
            ->max('duration_seconds') ?? 0;
        
        $triggered = $maxDuration > $config['threshold'];
        
        return [
            'type' => 'execution_time',
            'triggered' => $triggered,
            'current_value' => $maxDuration,
            'threshold' => $config['threshold'],
            'message' => $triggered 
                ? "Job execution time ({$maxDuration}s) exceeds threshold ({$config['threshold']}s)"
                : "Execution times are within acceptable limits",
            'severity' => $triggered ? 'warning' : 'info',
        ];
    }

    /**
     * Helper method to check resource usage alert
     */
    private function checkResourceUsageAlert(array $config): array
    {
        $systemResources = $this->monitoringService->getSystemResources();
        $memoryUsage = $systemResources['memory_usage_percent'] ?? 0;
        
        $triggered = $memoryUsage > $config['threshold'];
        
        return [
            'type' => 'resource_usage',
            'triggered' => $triggered,
            'current_value' => $memoryUsage,
            'threshold' => $config['threshold'],
            'message' => $triggered 
                ? "Memory usage ({$memoryUsage}%) exceeds threshold ({$config['threshold']}%)"
                : "Resource usage is within acceptable limits",
            'severity' => $triggered ? ($memoryUsage > 95 ? 'critical' : 'warning') : 'info',
        ];
    }

    /**
     * Helper method to check missed execution alert
     */
    private function checkMissedExecutionAlert(array $config): array
    {
        // This would need to be implemented based on expected job schedules
        // For now, return a placeholder
        return [
            'type' => 'missed_execution',
            'triggered' => false,
            'current_value' => 0,
            'threshold' => $config['threshold'],
            'message' => 'No missed executions detected',
            'severity' => 'info',
        ];
    }

    /**
     * Helper method to evaluate alert for testing
     */
    private function evaluateAlert(string $alertType, array $testData): array
    {
        $alertsConfig = $this->getAlertsConfiguration();
        $config = $alertsConfig[$alertType] ?? null;
        
        if (!$config) {
            throw new \Exception("Alert type '{$alertType}' not found");
        }

        $value = $testData['value'] ?? 0;
        $threshold = $config['threshold'];
        $triggered = $value > $threshold;

        return [
            'triggered' => $triggered,
            'message' => $triggered 
                ? "Test value ({$value}) exceeds threshold ({$threshold})"
                : "Test value ({$value}) is within threshold ({$threshold})",
            'comparison' => [
                'value' => $value,
                'threshold' => $threshold,
                'operator' => '>',
            ],
        ];
    }

    /**
     * Helper method to calculate overall health
     */
    private function calculateOverallHealth(): array
    {
        $metrics = $this->getAutomationMetrics();
        $systemHealth = $this->getSystemHealthOverview();
        
        $healthScore = 100;
        $issues = [];

        // Deduct points for failures
        if ($metrics['success_rate_24h'] < 95) {
            $deduction = (95 - $metrics['success_rate_24h']) * 2;
            $healthScore -= $deduction;
            $issues[] = "Success rate below 95% ({$metrics['success_rate_24h']}%)";
        }

        // Deduct points for system issues
        if ($systemHealth['stuck_jobs'] > 0) {
            $healthScore -= 20;
            $issues[] = "{$systemHealth['stuck_jobs']} stuck jobs detected";
        }

        if ($systemHealth['recent_failures'] > 5) {
            $healthScore -= 15;
            $issues[] = "{$systemHealth['recent_failures']} recent failures";
        }

        $healthScore = max(0, $healthScore);
        
        return [
            'score' => $healthScore,
            'status' => $healthScore >= 90 ? 'excellent' : ($healthScore >= 70 ? 'good' : ($healthScore >= 50 ? 'fair' : 'poor')),
            'issues' => $issues,
        ];
    }

    /**
     * Helper method to get critical issues
     */
    private function getCriticalIssues(): array
    {
        $issues = [];
        $systemHealth = $this->getSystemHealthOverview();
        
        if ($systemHealth['stuck_jobs'] > 0) {
            $issues[] = [
                'type' => 'stuck_jobs',
                'severity' => 'critical',
                'message' => "{$systemHealth['stuck_jobs']} jobs are stuck and may need manual intervention",
                'action' => 'Review and kill stuck jobs if necessary',
            ];
        }

        if ($systemHealth['recent_failures'] > 10) {
            $issues[] = [
                'type' => 'high_failure_rate',
                'severity' => 'critical',
                'message' => "High failure rate: {$systemHealth['recent_failures']} failures in the last hour",
                'action' => 'Investigate job failures and fix underlying issues',
            ];
        }

        if ($systemHealth['memory_usage'] > 95) {
            $issues[] = [
                'type' => 'high_memory_usage',
                'severity' => 'critical',
                'message' => "System memory usage is critically high: {$systemHealth['memory_usage']}%",
                'action' => 'Free up memory or add more system resources',
            ];
        }

        return $issues;
    }

    /**
     * Helper method to get performance summary
     */
    private function getPerformanceSummary(): array
    {
        $metrics = $this->getAutomationMetrics();
        
        return [
            'total_executions' => $metrics['total_executions_24h'],
            'success_rate' => $metrics['success_rate_24h'],
            'avg_execution_time' => $metrics['avg_execution_time'],
            'performance_grade' => $this->calculatePerformanceGrade($metrics),
        ];
    }

    /**
     * Helper method to get health recommendations
     */
    private function getHealthRecommendations(): array
    {
        $recommendations = [];
        $metrics = $this->getAutomationMetrics();
        $systemHealth = $this->getSystemHealthOverview();

        if ($metrics['success_rate_24h'] < 95) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'reliability',
                'message' => 'Improve job success rate by investigating and fixing common failure causes',
            ];
        }

        if ($metrics['avg_execution_time'] > 120) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'performance',
                'message' => 'Optimize job execution time to improve overall system performance',
            ];
        }

        if ($systemHealth['memory_usage'] > 80) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'resources',
                'message' => 'Monitor memory usage and consider optimizing resource-intensive jobs',
            ];
        }

        return $recommendations;
    }

    /**
     * Helper method to get system status summary
     */
    private function getSystemStatusSummary(): array
    {
        $systemHealth = $this->getSystemHealthOverview();
        
        return [
            'overall_status' => $systemHealth['status'],
            'running_jobs' => $systemHealth['running_jobs_count'],
            'system_load' => $systemHealth['system_load'],
            'uptime' => $this->monitoringService->getSystemUptime(),
        ];
    }

    /**
     * Helper method to calculate performance grade
     */
    private function calculatePerformanceGrade(array $metrics): string
    {
        $score = 0;
        
        // Success rate (40% weight)
        $score += ($metrics['success_rate_24h'] / 100) * 40;
        
        // Execution time (30% weight) - lower is better
        $timeScore = max(0, 30 - ($metrics['avg_execution_time'] / 10));
        $score += $timeScore;
        
        // Execution count (30% weight) - more executions indicate active system
        $countScore = min(30, $metrics['total_executions_24h'] / 10);
        $score += $countScore;
        
        if ($score >= 85) return 'A';
        if ($score >= 75) return 'B';
        if ($score >= 65) return 'C';
        if ($score >= 55) return 'D';
        return 'F';
    }

    /**
     * Helper method to generate performance analytics
     */
    private function generatePerformanceAnalytics(string $period, ?string $jobName, string $metric): array
    {
        $query = DB::table('cron_job_executions');
        
        // Apply time filter
        switch ($period) {
            case 'hour':
                $query->where('started_at', '>=', now()->subHour());
                break;
            case 'day':
                $query->where('started_at', '>=', now()->subDay());
                break;
            case 'week':
                $query->where('started_at', '>=', now()->subWeek());
                break;
            case 'month':
                $query->where('started_at', '>=', now()->subMonth());
                break;
        }

        // Apply job filter
        if ($jobName) {
            $query->where('job_name', $jobName);
        }

        // Generate analytics based on metric
        switch ($metric) {
            case 'execution_time':
                return $this->generateExecutionTimeAnalytics($query);
            case 'success_rate':
                return $this->generateSuccessRateAnalytics($query);
            case 'resource_usage':
                return $this->generateResourceUsageAnalytics($query);
            case 'frequency':
                return $this->generateFrequencyAnalytics($query);
            default:
                throw new \Exception("Unknown metric: {$metric}");
        }
    }

    /**
     * Helper method to generate execution time analytics
     */
    private function generateExecutionTimeAnalytics($query): array
    {
        $data = $query->selectRaw('
            DATE_FORMAT(started_at, "%Y-%m-%d %H:00:00") as hour,
            AVG(duration_seconds) as avg_duration,
            MIN(duration_seconds) as min_duration,
            MAX(duration_seconds) as max_duration,
            COUNT(*) as executions
        ')
        ->groupBy('hour')
        ->orderBy('hour')
        ->get();

        return [
            'type' => 'execution_time',
            'data' => $data->toArray(),
            'summary' => [
                'total_executions' => $data->sum('executions'),
                'overall_avg' => round($data->avg('avg_duration'), 2),
                'overall_max' => $data->max('max_duration'),
                'overall_min' => $data->min('min_duration'),
            ],
        ];
    }

    /**
     * Helper method to generate success rate analytics
     */
    private function generateSuccessRateAnalytics($query): array
    {
        $data = $query->selectRaw('
            DATE_FORMAT(started_at, "%Y-%m-%d %H:00:00") as hour,
            COUNT(*) as total_executions,
            SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as successful_executions
        ')
        ->groupBy('hour')
        ->orderBy('hour')
        ->get()
        ->map(function ($item) {
            $successRate = $item->total_executions > 0 
                ? ($item->successful_executions / $item->total_executions) * 100 
                : 0;
            
            return [
                'hour' => $item->hour,
                'success_rate' => round($successRate, 2),
                'total_executions' => $item->total_executions,
                'successful_executions' => $item->successful_executions,
            ];
        });

        return [
            'type' => 'success_rate',
            'data' => $data->toArray(),
            'summary' => [
                'overall_success_rate' => round($data->avg('success_rate'), 2),
                'total_executions' => $data->sum('total_executions'),
                'total_successful' => $data->sum('successful_executions'),
            ],
        ];
    }

    /**
     * Helper method to generate resource usage analytics
     */
    private function generateResourceUsageAnalytics($query): array
    {
        $data = $query->whereNotNull('memory_peak')
            ->selectRaw('
                DATE_FORMAT(started_at, "%Y-%m-%d %H:00:00") as hour,
                AVG(memory_peak) as avg_memory,
                MAX(memory_peak) as max_memory,
                COUNT(*) as executions
            ')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->map(function ($item) {
                return [
                    'hour' => $item->hour,
                    'avg_memory_mb' => round($item->avg_memory / 1024 / 1024, 2),
                    'max_memory_mb' => round($item->max_memory / 1024 / 1024, 2),
                    'executions' => $item->executions,
                ];
            });

        return [
            'type' => 'resource_usage',
            'data' => $data->toArray(),
            'summary' => [
                'overall_avg_memory_mb' => round($data->avg('avg_memory_mb'), 2),
                'overall_max_memory_mb' => $data->max('max_memory_mb'),
                'total_executions' => $data->sum('executions'),
            ],
        ];
    }

    /**
     * Helper method to generate frequency analytics
     */
    private function generateFrequencyAnalytics($query): array
    {
        $data = $query->selectRaw('
            DATE_FORMAT(started_at, "%Y-%m-%d %H:00:00") as hour,
            COUNT(*) as executions
        ')
        ->groupBy('hour')
        ->orderBy('hour')
        ->get();

        return [
            'type' => 'frequency',
            'data' => $data->toArray(),
            'summary' => [
                'total_executions' => $data->sum('executions'),
                'avg_executions_per_hour' => round($data->avg('executions'), 2),
                'max_executions_per_hour' => $data->max('executions'),
            ],
        ];
    }

    /**
     * Helper method to prepare export data
     */
    private function prepareExportData(string $period, bool $includeLogs): array
    {
        $data = [
            'export_info' => [
                'period' => $period,
                'generated_at' => now()->toISOString(),
                'generated_by' => auth()->user()->name,
            ],
            'system_health' => $this->getSystemHealthOverview(),
            'automation_metrics' => $this->getAutomationMetrics(),
            'job_performance' => $this->getJobPerformanceMetrics(),
            'failure_analysis' => $this->getFailureAnalysis(),
        ];

        if ($includeLogs) {
            $data['execution_logs'] = $this->getExecutionLogsForExport($period);
        }

        return $data;
    }

    /**
     * Helper method to get execution logs for export
     */
    private function getExecutionLogsForExport(string $period): array
    {
        $query = DB::table('cron_job_executions');
        
        switch ($period) {
            case 'day':
                $query->where('started_at', '>=', now()->subDay());
                break;
            case 'week':
                $query->where('started_at', '>=', now()->subWeek());
                break;
            case 'month':
                $query->where('started_at', '>=', now()->subMonth());
                break;
        }

        return $query->orderBy('started_at', 'desc')
            ->limit(1000) // Limit to prevent huge exports
            ->get()
            ->toArray();
    }

    /**
     * Helper method to export data to CSV
     */
    private function exportToCsv(array $data, string $filename)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            
            // Write job performance data
            if (isset($data['job_performance'])) {
                fputcsv($file, ['Job Performance Data']);
                fputcsv($file, ['Job Name', 'Executions', 'Success Rate', 'Avg Duration', 'Max Duration', 'Avg Memory MB']);
                
                foreach ($data['job_performance'] as $job) {
                    fputcsv($file, [
                        $job['job_name'],
                        $job['executions'],
                        $job['success_rate'],
                        $job['avg_duration'],
                        $job['max_duration'],
                        $job['avg_memory_mb'],
                    ]);
                }
                
                fputcsv($file, []); // Empty line
            }

            // Write execution logs if included
            if (isset($data['execution_logs'])) {
                fputcsv($file, ['Execution Logs']);
                fputcsv($file, ['Execution ID', 'Job Name', 'Status', 'Started At', 'Duration', 'Memory Peak']);
                
                foreach ($data['execution_logs'] as $log) {
                    fputcsv($file, [
                        $log->execution_id,
                        $log->job_name,
                        $log->status,
                        $log->started_at,
                        $log->duration_seconds,
                        $log->memory_peak,
                    ]);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Helper method to perform comprehensive health check
     */
    private function performComprehensiveHealthCheck(): array
    {
        $healthCheck = [
            'overall_score' => 0,
            'issues' => [],
            'recommendations' => [],
            'system_status' => [],
        ];

        // Check system resources
        $systemResources = $this->monitoringService->getSystemResources();
        if ($systemResources['memory_usage_percent'] > 90) {
            $healthCheck['issues'][] = [
                'type' => 'high_memory_usage',
                'severity' => 'critical',
                'message' => 'System memory usage is critically high',
            ];
        }

        // Check job performance
        $metrics = $this->getAutomationMetrics();
        if ($metrics['success_rate_24h'] < 90) {
            $healthCheck['issues'][] = [
                'type' => 'low_success_rate',
                'severity' => 'warning',
                'message' => 'Job success rate is below 90%',
            ];
        }

        // Check for stuck jobs
        $runningJobs = $this->cronJobManager->getRunningJobs();
        $stuckJobs = collect($runningJobs)->filter(function ($job) {
            return Carbon::parse($job->started_at)->diffInMinutes() > 60;
        });

        if ($stuckJobs->count() > 0) {
            $healthCheck['issues'][] = [
                'type' => 'stuck_jobs',
                'severity' => 'critical',
                'message' => "Found {$stuckJobs->count()} stuck jobs",
            ];
        }

        // Calculate overall score
        $healthCheck['overall_score'] = max(0, 100 - (count($healthCheck['issues']) * 15));
        
        // Add recommendations based on issues
        foreach ($healthCheck['issues'] as $issue) {
            switch ($issue['type']) {
                case 'high_memory_usage':
                    $healthCheck['recommendations'][] = 'Consider optimizing memory usage or adding more system memory';
                    break;
                case 'low_success_rate':
                    $healthCheck['recommendations'][] = 'Investigate and fix common job failure causes';
                    break;
                case 'stuck_jobs':
                    $healthCheck['recommendations'][] = 'Review and kill stuck jobs, investigate root causes';
                    break;
            }
        }

        return $healthCheck;
    }
}