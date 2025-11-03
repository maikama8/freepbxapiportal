<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CronJobManager
{
    protected $jobStatuses = [];
    protected $cachePrefix = 'cron_job_';
    protected $logChannel = 'cron';

    /**
     * Register a cron job execution start
     */
    public function startJob(string $jobName, array $metadata = []): string
    {
        $executionId = uniqid('exec_');
        $startTime = Carbon::now();
        
        $jobData = [
            'execution_id' => $executionId,
            'job_name' => $jobName,
            'status' => 'running',
            'started_at' => $startTime->toISOString(),
            'metadata' => $metadata,
            'pid' => getmypid(),
            'memory_start' => memory_get_usage(true),
        ];

        // Store in cache for real-time monitoring
        Cache::put($this->cachePrefix . $executionId, $jobData, 3600);
        
        // Store in database for historical tracking
        DB::table('cron_job_executions')->insert([
            'execution_id' => $executionId,
            'job_name' => $jobName,
            'status' => 'running',
            'started_at' => $startTime,
            'metadata' => json_encode($metadata),
            'pid' => getmypid(),
            'memory_start' => memory_get_usage(true),
            'created_at' => $startTime,
            'updated_at' => $startTime,
        ]);

        Log::channel($this->logChannel)->info("Cron job started: {$jobName}", [
            'execution_id' => $executionId,
            'metadata' => $metadata,
        ]);

        return $executionId;
    }

    /**
     * Register a cron job execution completion
     */
    public function completeJob(string $executionId, bool $success = true, array $result = []): void
    {
        $endTime = Carbon::now();
        $jobData = Cache::get($this->cachePrefix . $executionId);
        
        if (!$jobData) {
            Log::channel($this->logChannel)->warning("Attempted to complete unknown job execution: {$executionId}");
            return;
        }

        $startTime = Carbon::parse($jobData['started_at']);
        $duration = $endTime->diffInSeconds($startTime);
        $memoryEnd = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);

        $updateData = [
            'status' => $success ? 'completed' : 'failed',
            'completed_at' => $endTime,
            'duration_seconds' => $duration,
            'memory_end' => $memoryEnd,
            'memory_peak' => $memoryPeak,
            'result' => json_encode($result),
            'updated_at' => $endTime,
        ];

        // Update cache
        $jobData = array_merge($jobData, $updateData);
        Cache::put($this->cachePrefix . $executionId, $jobData, 3600);

        // Update database
        DB::table('cron_job_executions')
            ->where('execution_id', $executionId)
            ->update($updateData);

        $logLevel = $success ? 'info' : 'error';
        Log::channel($this->logChannel)->$logLevel("Cron job completed: {$jobData['job_name']}", [
            'execution_id' => $executionId,
            'success' => $success,
            'duration' => $duration,
            'memory_usage' => $memoryEnd - $jobData['memory_start'],
            'result' => $result,
        ]);

        // Check for job failure patterns
        if (!$success) {
            $this->checkFailurePatterns($jobData['job_name']);
        }
    }

    /**
     * Register a cron job failure
     */
    public function failJob(string $executionId, \Throwable $exception): void
    {
        $this->completeJob($executionId, false, [
            'error' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Get current running jobs
     */
    public function getRunningJobs(): array
    {
        return DB::table('cron_job_executions')
            ->where('status', 'running')
            ->where('started_at', '>', Carbon::now()->subHours(2))
            ->get()
            ->toArray();
    }

    /**
     * Get job execution history
     */
    public function getJobHistory(string $jobName = null, int $limit = 100): array
    {
        $query = DB::table('cron_job_executions')
            ->orderBy('started_at', 'desc')
            ->limit($limit);

        if ($jobName) {
            $query->where('job_name', $jobName);
        }

        return $query->get()->toArray();
    }

    /**
     * Get job statistics
     */
    public function getJobStatistics(string $jobName = null, int $days = 7): array
    {
        $query = DB::table('cron_job_executions')
            ->where('started_at', '>', Carbon::now()->subDays($days));

        if ($jobName) {
            $query->where('job_name', $jobName);
        }

        $stats = $query->selectRaw('
            job_name,
            COUNT(*) as total_executions,
            SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as successful_executions,
            SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_executions,
            AVG(duration_seconds) as avg_duration,
            MAX(duration_seconds) as max_duration,
            MIN(duration_seconds) as min_duration,
            AVG(memory_peak) as avg_memory_usage,
            MAX(memory_peak) as max_memory_usage
        ')
        ->groupBy('job_name')
        ->get()
        ->toArray();

        return $stats;
    }

    /**
     * Check for failure patterns and alert if necessary
     */
    protected function checkFailurePatterns(string $jobName): void
    {
        // Check for consecutive failures
        $recentFailures = DB::table('cron_job_executions')
            ->where('job_name', $jobName)
            ->where('started_at', '>', Carbon::now()->subHours(24))
            ->orderBy('started_at', 'desc')
            ->limit(5)
            ->pluck('status')
            ->toArray();

        $consecutiveFailures = 0;
        foreach ($recentFailures as $status) {
            if ($status === 'failed') {
                $consecutiveFailures++;
            } else {
                break;
            }
        }

        // Alert if 3 or more consecutive failures
        if ($consecutiveFailures >= 3) {
            Log::channel('alerts')->critical("Cron job experiencing consecutive failures", [
                'job_name' => $jobName,
                'consecutive_failures' => $consecutiveFailures,
                'recent_statuses' => $recentFailures,
            ]);

            // Store alert in cache to prevent spam
            $alertKey = "cron_alert_{$jobName}_consecutive_failures";
            if (!Cache::has($alertKey)) {
                Cache::put($alertKey, true, 3600); // 1 hour cooldown
                
                // Here you could send email/SMS alerts
                $this->sendFailureAlert($jobName, $consecutiveFailures);
            }
        }
    }

    /**
     * Send failure alert (placeholder for email/SMS integration)
     */
    protected function sendFailureAlert(string $jobName, int $consecutiveFailures): void
    {
        // This would integrate with your notification system
        Log::channel('alerts')->info("Failure alert sent for job: {$jobName}", [
            'consecutive_failures' => $consecutiveFailures,
        ]);
    }

    /**
     * Clean up old job execution records
     */
    public function cleanupOldRecords(int $daysToKeep = 30): int
    {
        $cutoffDate = Carbon::now()->subDays($daysToKeep);
        
        $deletedCount = DB::table('cron_job_executions')
            ->where('started_at', '<', $cutoffDate)
            ->delete();

        Log::channel($this->logChannel)->info("Cleaned up old cron job records", [
            'deleted_count' => $deletedCount,
            'cutoff_date' => $cutoffDate->toISOString(),
        ]);

        return $deletedCount;
    }

    /**
     * Get system health status based on cron jobs
     */
    public function getSystemHealth(): array
    {
        $now = Carbon::now();
        
        // Check if critical jobs have run recently
        $criticalJobs = [
            'cdr:automated-processing' => 10, // Should run every 5 minutes
            'freepbx:automated-sync' => 35,   // Should run every 30 minutes
            'billing:monitor-realtime' => 5,  // Should run every 2 minutes
            'system:health-check' => 10,      // Should run every 5 minutes
        ];

        $health = [
            'overall_status' => 'healthy',
            'job_statuses' => [],
            'alerts' => [],
        ];

        foreach ($criticalJobs as $jobName => $maxMinutesSinceRun) {
            $lastRun = DB::table('cron_job_executions')
                ->where('job_name', $jobName)
                ->where('status', 'completed')
                ->orderBy('completed_at', 'desc')
                ->first();

            if (!$lastRun) {
                $health['job_statuses'][$jobName] = 'never_run';
                $health['alerts'][] = "Critical job {$jobName} has never run";
                $health['overall_status'] = 'critical';
            } else {
                $minutesSinceRun = $now->diffInMinutes(Carbon::parse($lastRun->completed_at));
                
                if ($minutesSinceRun > $maxMinutesSinceRun) {
                    $health['job_statuses'][$jobName] = 'overdue';
                    $health['alerts'][] = "Critical job {$jobName} is overdue (last run: {$minutesSinceRun} minutes ago)";
                    $health['overall_status'] = $health['overall_status'] === 'healthy' ? 'warning' : 'critical';
                } else {
                    $health['job_statuses'][$jobName] = 'healthy';
                }
            }
        }

        return $health;
    }

    /**
     * Force kill stuck jobs
     */
    public function killStuckJobs(int $maxRuntimeMinutes = 60): array
    {
        $cutoffTime = Carbon::now()->subMinutes($maxRuntimeMinutes);
        
        $stuckJobs = DB::table('cron_job_executions')
            ->where('status', 'running')
            ->where('started_at', '<', $cutoffTime)
            ->get();

        $killedJobs = [];

        foreach ($stuckJobs as $job) {
            // Try to kill the process if PID is available
            if ($job->pid && function_exists('posix_kill')) {
                if (posix_kill($job->pid, SIGTERM)) {
                    $killedJobs[] = $job->execution_id;
                }
            }

            // Mark as failed in database
            DB::table('cron_job_executions')
                ->where('execution_id', $job->execution_id)
                ->update([
                    'status' => 'failed',
                    'completed_at' => Carbon::now(),
                    'result' => json_encode(['error' => 'Killed due to timeout']),
                    'updated_at' => Carbon::now(),
                ]);

            Log::channel($this->logChannel)->warning("Killed stuck cron job", [
                'execution_id' => $job->execution_id,
                'job_name' => $job->job_name,
                'runtime_minutes' => Carbon::now()->diffInMinutes(Carbon::parse($job->started_at)),
            ]);
        }

        return $killedJobs;
    }
}