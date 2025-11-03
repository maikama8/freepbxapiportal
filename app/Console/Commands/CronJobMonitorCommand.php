<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CronJobManager;
use Carbon\Carbon;

class CronJobMonitorCommand extends Command
{
    protected $signature = 'cron:monitor 
                           {action : Action to perform (status|history|health|cleanup|kill-stuck)}
                           {--job= : Specific job name to monitor}
                           {--days=7 : Number of days for statistics}
                           {--limit=100 : Limit for history results}
                           {--max-runtime=60 : Maximum runtime in minutes for stuck job detection}';

    protected $description = 'Monitor and manage cron job executions';

    protected CronJobManager $cronJobManager;

    public function __construct(CronJobManager $cronJobManager)
    {
        parent::__construct();
        $this->cronJobManager = $cronJobManager;
    }

    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'status' => $this->showStatus(),
            'history' => $this->showHistory(),
            'health' => $this->showHealth(),
            'cleanup' => $this->cleanupOldRecords(),
            'kill-stuck' => $this->killStuckJobs(),
            default => $this->showUsage(),
        };
    }

    protected function showStatus(): int
    {
        $this->info('Current Cron Job Status');
        $this->line('========================');

        // Show running jobs
        $runningJobs = $this->cronJobManager->getRunningJobs();
        
        if (empty($runningJobs)) {
            $this->info('No jobs currently running.');
        } else {
            $this->table(
                ['Execution ID', 'Job Name', 'Started At', 'Runtime', 'PID', 'Memory (MB)'],
                array_map(function ($job) {
                    $startTime = Carbon::parse($job->started_at);
                    $runtime = $startTime->diffForHumans(null, true);
                    $memoryMB = $job->memory_start ? round($job->memory_start / 1024 / 1024, 2) : 'N/A';
                    
                    return [
                        substr($job->execution_id, 0, 12) . '...',
                        $job->job_name,
                        $startTime->format('Y-m-d H:i:s'),
                        $runtime,
                        $job->pid ?? 'N/A',
                        $memoryMB,
                    ];
                }, $runningJobs)
            );
        }

        // Show recent statistics
        $this->line('');
        $this->info('Recent Job Statistics (Last 24 hours)');
        $this->line('=====================================');

        $stats = $this->cronJobManager->getJobStatistics(
            $this->option('job'),
            1 // Last 1 day
        );

        if (empty($stats)) {
            $this->info('No job statistics available.');
        } else {
            $this->table(
                ['Job Name', 'Total', 'Success', 'Failed', 'Success Rate', 'Avg Duration'],
                array_map(function ($stat) {
                    $successRate = $stat->total_executions > 0 
                        ? round(($stat->successful_executions / $stat->total_executions) * 100, 1) 
                        : 0;
                    
                    return [
                        $stat->job_name,
                        $stat->total_executions,
                        $stat->successful_executions,
                        $stat->failed_executions,
                        $successRate . '%',
                        $stat->avg_duration ? round($stat->avg_duration, 2) . 's' : 'N/A',
                    ];
                }, $stats)
            );
        }

        return self::SUCCESS;
    }

    protected function showHistory(): int
    {
        $jobName = $this->option('job');
        $limit = (int) $this->option('limit');

        $this->info($jobName ? "Job History for: {$jobName}" : 'All Jobs History');
        $this->line(str_repeat('=', 50));

        $history = $this->cronJobManager->getJobHistory($jobName, $limit);

        if (empty($history)) {
            $this->info('No job history found.');
            return self::SUCCESS;
        }

        $this->table(
            ['Job Name', 'Status', 'Started At', 'Duration', 'Memory Peak'],
            array_map(function ($job) {
                $duration = $job->duration_seconds ? $job->duration_seconds . 's' : 'N/A';
                $memoryPeak = $job->memory_peak ? round($job->memory_peak / 1024 / 1024, 2) . 'MB' : 'N/A';
                
                return [
                    $job->job_name,
                    $this->colorizeStatus($job->status),
                    Carbon::parse($job->started_at)->format('Y-m-d H:i:s'),
                    $duration,
                    $memoryPeak,
                ];
            }, $history)
        );

        return self::SUCCESS;
    }

    protected function showHealth(): int
    {
        $this->info('System Health Check');
        $this->line('==================');

        $health = $this->cronJobManager->getSystemHealth();

        // Overall status
        $statusColor = match ($health['overall_status']) {
            'healthy' => 'info',
            'warning' => 'comment',
            'critical' => 'error',
            default => 'line',
        };

        $this->$statusColor("Overall Status: " . strtoupper($health['overall_status']));
        $this->line('');

        // Job statuses
        $this->info('Critical Job Status:');
        foreach ($health['job_statuses'] as $jobName => $status) {
            $statusColor = match ($status) {
                'healthy' => 'info',
                'overdue' => 'comment',
                'never_run' => 'error',
                default => 'line',
            };
            
            $this->$statusColor("  {$jobName}: " . strtoupper($status));
        }

        // Alerts
        if (!empty($health['alerts'])) {
            $this->line('');
            $this->error('Alerts:');
            foreach ($health['alerts'] as $alert) {
                $this->error("  • {$alert}");
            }
        }

        return self::SUCCESS;
    }

    protected function cleanupOldRecords(): int
    {
        $days = (int) $this->option('days');
        
        $this->info("Cleaning up cron job records older than {$days} days...");
        
        $deletedCount = $this->cronJobManager->cleanupOldRecords($days);
        
        $this->info("Cleaned up {$deletedCount} old records.");
        
        return self::SUCCESS;
    }

    protected function killStuckJobs(): int
    {
        $maxRuntime = (int) $this->option('max-runtime');
        
        $this->info("Checking for jobs running longer than {$maxRuntime} minutes...");
        
        $killedJobs = $this->cronJobManager->killStuckJobs($maxRuntime);
        
        if (empty($killedJobs)) {
            $this->info('No stuck jobs found.');
        } else {
            $this->info('Killed ' . count($killedJobs) . ' stuck jobs:');
            foreach ($killedJobs as $executionId) {
                $this->line("  • {$executionId}");
            }
        }
        
        return self::SUCCESS;
    }

    protected function showUsage(): int
    {
        $this->error('Invalid action. Available actions:');
        $this->line('  status     - Show current job status and statistics');
        $this->line('  history    - Show job execution history');
        $this->line('  health     - Show system health based on cron jobs');
        $this->line('  cleanup    - Clean up old job execution records');
        $this->line('  kill-stuck - Kill jobs that have been running too long');
        
        return self::FAILURE;
    }

    protected function colorizeStatus(string $status): string
    {
        return match ($status) {
            'completed' => "<info>{$status}</info>",
            'failed' => "<error>{$status}</error>",
            'running' => "<comment>{$status}</comment>",
            default => $status,
        };
    }
}