<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CronJobManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class CronHealthMonitorCommand extends Command
{
    protected $signature = 'cron:health-monitor 
                           {--alert : Send alerts for critical issues}
                           {--email= : Email address to send alerts to}
                           {--threshold-minutes=15 : Minutes before considering a job overdue}';

    protected $description = 'Monitor cron job health and send alerts for issues';

    protected CronJobManager $cronJobManager;

    public function __construct(CronJobManager $cronJobManager)
    {
        parent::__construct();
        $this->cronJobManager = $cronJobManager;
    }

    public function handle(): int
    {
        $this->info('Monitoring cron job health...');

        $health = $this->cronJobManager->getSystemHealth();
        $shouldAlert = $this->option('alert');
        $alertEmail = $this->option('email');
        $thresholdMinutes = (int) $this->option('threshold-minutes');

        // Display health status
        $this->displayHealthStatus($health);

        // Check for specific issues
        $issues = $this->checkForIssues($thresholdMinutes);

        if (!empty($issues)) {
            $this->displayIssues($issues);

            if ($shouldAlert) {
                $this->sendAlerts($issues, $alertEmail);
            }

            return self::FAILURE;
        }

        $this->info('✓ All cron jobs are healthy');
        return self::SUCCESS;
    }

    protected function displayHealthStatus(array $health): void
    {
        $statusColor = match ($health['overall_status']) {
            'healthy' => 'info',
            'warning' => 'comment',
            'critical' => 'error',
            default => 'line',
        };

        $this->$statusColor("Overall Status: " . strtoupper($health['overall_status']));
        $this->line('');

        // Display job statuses
        $this->info('Job Status Summary:');
        foreach ($health['job_statuses'] as $jobName => $status) {
            $statusIcon = match ($status) {
                'healthy' => '✓',
                'overdue' => '⚠',
                'never_run' => '✗',
                default => '?',
            };

            $statusColor = match ($status) {
                'healthy' => 'info',
                'overdue' => 'comment',
                'never_run' => 'error',
                default => 'line',
            };

            $this->$statusColor("  {$statusIcon} {$jobName}: " . strtoupper($status));
        }

        if (!empty($health['alerts'])) {
            $this->line('');
            $this->error('Active Alerts:');
            foreach ($health['alerts'] as $alert) {
                $this->error("  • {$alert}");
            }
        }
    }

    protected function checkForIssues(int $thresholdMinutes): array
    {
        $issues = [];
        $now = Carbon::now();

        // Check for stuck jobs
        $stuckJobs = $this->cronJobManager->getRunningJobs();
        foreach ($stuckJobs as $job) {
            $runtime = $now->diffInMinutes(Carbon::parse($job->started_at));
            if ($runtime > 60) { // Jobs running longer than 1 hour
                $issues[] = [
                    'type' => 'stuck_job',
                    'severity' => 'critical',
                    'message' => "Job '{$job->job_name}' has been running for {$runtime} minutes",
                    'job' => $job,
                ];
            }
        }

        // Check for failed jobs in the last hour
        $recentFailures = \DB::table('cron_job_executions')
            ->where('status', 'failed')
            ->where('started_at', '>', $now->subHour())
            ->get();

        $failuresByJob = $recentFailures->groupBy('job_name');
        foreach ($failuresByJob as $jobName => $failures) {
            if ($failures->count() >= 3) {
                $issues[] = [
                    'type' => 'repeated_failures',
                    'severity' => 'critical',
                    'message' => "Job '{$jobName}' has failed {$failures->count()} times in the last hour",
                    'failures' => $failures,
                ];
            }
        }

        // Check for missing critical jobs
        $criticalJobs = [
            'cdr:automated-processing' => 10,
            'billing:monitor-realtime' => 5,
            'system:health-check' => 10,
        ];

        foreach ($criticalJobs as $jobName => $maxMinutes) {
            $lastRun = \DB::table('cron_job_executions')
                ->where('job_name', $jobName)
                ->where('status', 'completed')
                ->orderBy('completed_at', 'desc')
                ->first();

            if (!$lastRun) {
                $issues[] = [
                    'type' => 'never_run',
                    'severity' => 'critical',
                    'message' => "Critical job '{$jobName}' has never run successfully",
                    'job_name' => $jobName,
                ];
            } else {
                $minutesSinceRun = $now->diffInMinutes(Carbon::parse($lastRun->completed_at));
                if ($minutesSinceRun > $maxMinutes) {
                    $issues[] = [
                        'type' => 'overdue',
                        'severity' => $minutesSinceRun > ($maxMinutes * 2) ? 'critical' : 'warning',
                        'message' => "Critical job '{$jobName}' is overdue (last run: {$minutesSinceRun} minutes ago)",
                        'job_name' => $jobName,
                        'minutes_overdue' => $minutesSinceRun - $maxMinutes,
                    ];
                }
            }
        }

        // Check disk space for logs
        $logPath = storage_path('logs');
        if (is_dir($logPath)) {
            $freeBytes = disk_free_space($logPath);
            $totalBytes = disk_total_space($logPath);
            $usedPercent = (($totalBytes - $freeBytes) / $totalBytes) * 100;

            if ($usedPercent > 90) {
                $issues[] = [
                    'type' => 'disk_space',
                    'severity' => $usedPercent > 95 ? 'critical' : 'warning',
                    'message' => "Disk space critically low: {$usedPercent}% used",
                    'used_percent' => $usedPercent,
                ];
            }
        }

        return $issues;
    }

    protected function displayIssues(array $issues): void
    {
        $this->line('');
        $this->error('Issues Found:');

        foreach ($issues as $issue) {
            $icon = $issue['severity'] === 'critical' ? '🔴' : '🟡';
            $this->error("  {$icon} [{$issue['severity']}] {$issue['message']}");
        }
    }

    protected function sendAlerts(array $issues, string $alertEmail = null): void
    {
        $criticalIssues = array_filter($issues, fn($issue) => $issue['severity'] === 'critical');
        
        if (empty($criticalIssues)) {
            return;
        }

        // Log critical issues
        Log::channel('alerts')->critical('Cron job health check found critical issues', [
            'issues_count' => count($criticalIssues),
            'issues' => $criticalIssues,
        ]);

        // Send email alert if configured
        if ($alertEmail) {
            $this->sendEmailAlert($criticalIssues, $alertEmail);
        }

        // Send Slack alert if configured
        if (config('logging.channels.alert_slack.url')) {
            $this->sendSlackAlert($criticalIssues);
        }
    }

    protected function sendEmailAlert(array $issues, string $email): void
    {
        try {
            $subject = 'VoIP Platform - Critical Cron Job Issues Detected';
            $message = $this->formatAlertMessage($issues);

            // Simple mail sending - in production you'd use a proper mail class
            mail($email, $subject, $message, [
                'From' => config('mail.from.address', 'noreply@voipplatform.com'),
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);

            $this->info("Alert email sent to: {$email}");
        } catch (\Exception $e) {
            $this->error("Failed to send email alert: " . $e->getMessage());
        }
    }

    protected function sendSlackAlert(array $issues): void
    {
        try {
            $message = $this->formatSlackMessage($issues);
            
            Log::channel('alert_slack')->critical($message);
            
            $this->info("Alert sent to Slack");
        } catch (\Exception $e) {
            $this->error("Failed to send Slack alert: " . $e->getMessage());
        }
    }

    protected function formatAlertMessage(array $issues): string
    {
        $message = "VoIP Platform Cron Job Health Alert\n";
        $message .= "=====================================\n\n";
        $message .= "Critical issues detected at " . Carbon::now()->format('Y-m-d H:i:s') . "\n\n";

        foreach ($issues as $issue) {
            $message .= "• [{$issue['severity']}] {$issue['message']}\n";
        }

        $message .= "\nPlease check the cron job management dashboard for more details.\n";
        $message .= "Dashboard: " . url('/admin/cron-jobs') . "\n";

        return $message;
    }

    protected function formatSlackMessage(array $issues): string
    {
        $message = "🚨 *VoIP Platform Cron Job Alert*\n\n";
        $message .= "*Critical issues detected:*\n";

        foreach ($issues as $issue) {
            $icon = $issue['severity'] === 'critical' ? '🔴' : '🟡';
            $message .= "{$icon} {$issue['message']}\n";
        }

        $message .= "\n<" . url('/admin/cron-jobs') . "|View Dashboard>";

        return $message;
    }
}