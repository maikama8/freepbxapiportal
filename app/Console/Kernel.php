<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // System health checks every 5 minutes
        $schedule->command('system:health-check --alert')
                 ->everyFiveMinutes()
                 ->withoutOverlapping()
                 ->runInBackground();

        // Database maintenance daily at 2 AM
        $schedule->command('db:maintenance --cleanup --optimize')
                 ->dailyAt('02:00')
                 ->withoutOverlapping()
                 ->runInBackground();

        // System backup daily at 3 AM
        $schedule->command('backup:system --compress --retention=30')
                 ->dailyAt('03:00')
                 ->withoutOverlapping()
                 ->runInBackground();

        // Generate monthly invoices on the 1st of each month at 1 AM
        $schedule->command('invoices:generate-monthly')
                 ->monthlyOn(1, '01:00')
                 ->withoutOverlapping()
                 ->runInBackground();

        // Send low balance warnings daily at 9 AM
        $schedule->command('balance:send-warnings')
                 ->dailyAt('09:00')
                 ->withoutOverlapping()
                 ->runInBackground();

        // Security audit weekly on Sundays at 4 AM
        $schedule->command('security:audit')
                 ->weeklyOn(0, '04:00')
                 ->withoutOverlapping()
                 ->runInBackground();

        // Clean up old logs weekly on Sundays at 5 AM
        $schedule->command('log:clear --days=30')
                 ->weeklyOn(0, '05:00')
                 ->withoutOverlapping()
                 ->runInBackground();

        // Queue worker health check every minute
        $schedule->command('queue:work --stop-when-empty')
                 ->everyMinute()
                 ->withoutOverlapping()
                 ->runInBackground();

        // Database analyze tables weekly on Saturdays at 3 AM
        $schedule->command('db:maintenance --analyze')
                 ->weeklyOn(6, '03:00')
                 ->withoutOverlapping()
                 ->runInBackground();

        // Clear expired sessions daily at midnight
        $schedule->command('session:gc')
                 ->daily()
                 ->withoutOverlapping()
                 ->runInBackground();

        // Update call rates from external sources (if configured)
        $schedule->command('rates:update')
                 ->dailyAt('06:00')
                 ->withoutOverlapping()
                 ->runInBackground()
                 ->skip(function () {
                     return !config('voip.auto_update_rates', false);
                 });

        // Monitor payment gateway status every 15 minutes
        $schedule->command('payments:check-gateways')
                 ->everyFifteenMinutes()
                 ->withoutOverlapping()
                 ->runInBackground();

        // Sync CDR records from FreePBX every 10 minutes
        $schedule->command('cdr:sync')
                 ->everyTenMinutes()
                 ->withoutOverlapping()
                 ->runInBackground();

        // Generate system reports monthly on the 1st at 6 AM
        $schedule->command('reports:generate-monthly')
                 ->monthlyOn(1, '06:00')
                 ->withoutOverlapping()
                 ->runInBackground();

        // Check for failed jobs and retry them every hour
        $schedule->command('queue:retry all')
                 ->hourly()
                 ->withoutOverlapping()
                 ->runInBackground();

        // Prune old failed jobs weekly
        $schedule->command('queue:prune-failed --hours=168')
                 ->weekly()
                 ->withoutOverlapping()
                 ->runInBackground();

        // Monitor disk space every hour
        $schedule->call(function () {
            $diskUsage = disk_free_space('/') / disk_total_space('/') * 100;
            if ($diskUsage > 90) {
                \Log::channel('alerts')->critical('Disk space critically low', [
                    'usage_percentage' => $diskUsage,
                    'server' => gethostname(),
                ]);
            }
        })->hourly();

        // Warm up application cache daily at 5 AM
        $schedule->command('config:cache')
                 ->dailyAt('05:00')
                 ->withoutOverlapping()
                 ->runInBackground();

        $schedule->command('route:cache')
                 ->dailyAt('05:05')
                 ->withoutOverlapping()
                 ->runInBackground();

        $schedule->command('view:cache')
                 ->dailyAt('05:10')
                 ->withoutOverlapping()
                 ->runInBackground();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}