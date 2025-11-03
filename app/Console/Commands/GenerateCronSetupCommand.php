<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateCronSetupCommand extends Command
{
    protected $signature = 'cron:generate-setup 
                           {--type=cpanel : Type of setup (cpanel, direct, systemd)}
                           {--output= : Output file path}
                           {--php-path=/usr/bin/php : Path to PHP executable}
                           {--project-path= : Full path to project directory}';

    protected $description = 'Generate cron job setup scripts for different hosting environments';

    public function handle(): int
    {
        $type = $this->option('type');
        $outputPath = $this->option('output');
        $phpPath = $this->option('php-path');
        $projectPath = $this->option('project-path') ?: base_path();

        $this->info("Generating cron job setup for: {$type}");

        switch ($type) {
            case 'cpanel':
                return $this->generateCpanelSetup($outputPath, $phpPath, $projectPath);
            case 'direct':
                return $this->generateDirectSetup($outputPath, $phpPath, $projectPath);
            case 'systemd':
                return $this->generateSystemdSetup($outputPath, $phpPath, $projectPath);
            default:
                $this->error("Unknown setup type: {$type}");
                return self::FAILURE;
        }
    }

    protected function generateCpanelSetup(string $outputPath = null, string $phpPath, string $projectPath): int
    {
        $outputPath = $outputPath ?: storage_path('app/cron-setup-cpanel.txt');

        $content = $this->getCpanelCronContent($phpPath, $projectPath);
        
        File::put($outputPath, $content);
        
        $this->info("cPanel cron setup generated: {$outputPath}");
        $this->line('');
        $this->line('Instructions for cPanel:');
        $this->line('1. Log into your cPanel');
        $this->line('2. Go to "Cron Jobs" section');
        $this->line('3. Add each line from the generated file as a separate cron job');
        $this->line('4. Make sure to set the correct email for notifications');
        $this->line('');
        $this->warn('Important: Verify the PHP path and project path are correct for your hosting environment!');

        return self::SUCCESS;
    }

    protected function generateDirectSetup(string $outputPath = null, string $phpPath, string $projectPath): int
    {
        $outputPath = $outputPath ?: storage_path('app/cron-setup-direct.sh');

        $content = $this->getDirectCronContent($phpPath, $projectPath);
        
        File::put($outputPath, $content);
        
        // Make the script executable
        chmod($outputPath, 0755);
        
        $this->info("Direct cron setup script generated: {$outputPath}");
        $this->line('');
        $this->line('Instructions for direct server access:');
        $this->line("1. Run: chmod +x {$outputPath}");
        $this->line("2. Run: {$outputPath}");
        $this->line('3. Verify with: crontab -l');

        return self::SUCCESS;
    }

    protected function generateSystemdSetup(string $outputPath = null, string $phpPath, string $projectPath): int
    {
        $outputPath = $outputPath ?: storage_path('app/cron-setup-systemd');

        $timerContent = $this->getSystemdTimerContent();
        $serviceContent = $this->getSystemdServiceContent($phpPath, $projectPath);
        
        File::put($outputPath . '.timer', $timerContent);
        File::put($outputPath . '.service', $serviceContent);
        
        $this->info("Systemd timer and service files generated:");
        $this->line("- {$outputPath}.timer");
        $this->line("- {$outputPath}.service");
        $this->line('');
        $this->line('Instructions for systemd:');
        $this->line("1. Copy files to /etc/systemd/system/");
        $this->line("2. Run: systemctl daemon-reload");
        $this->line("3. Run: systemctl enable laravel-scheduler.timer");
        $this->line("4. Run: systemctl start laravel-scheduler.timer");

        return self::SUCCESS;
    }

    protected function getCpanelCronContent(string $phpPath, string $projectPath): string
    {
        return <<<EOT
# FreePBX VoIP Platform - cPanel Cron Jobs Configuration
# Copy each line below as a separate cron job in cPanel
# Make sure to adjust the PHP path and project path for your hosting environment

# Main Laravel Scheduler (REQUIRED - runs every minute)
* * * * * {$phpPath} {$projectPath}/artisan schedule:run >> /dev/null 2>&1

# Critical Jobs (Backup - run directly if scheduler fails)
# CDR Processing (every 5 minutes)
*/5 * * * * {$phpPath} {$projectPath}/artisan cdr:automated-processing --batch-size=50 >> /dev/null 2>&1

# FreePBX Sync (every 30 minutes)
*/30 * * * * {$phpPath} {$projectPath}/artisan freepbx:automated-sync --sync-mode=incremental >> /dev/null 2>&1

# Real-time Billing Monitor (every 2 minutes)
*/2 * * * * {$phpPath} {$projectPath}/artisan billing:monitor-realtime --terminate >> /dev/null 2>&1

# System Health Check (every 5 minutes)
*/5 * * * * {$phpPath} {$projectPath}/artisan system:health-check --alert >> /dev/null 2>&1

# Monthly DID Billing (1st of each month at 2 AM)
0 2 1 * * {$phpPath} {$projectPath}/artisan billing:monthly-did-charges --suspend-insufficient >> /dev/null 2>&1

# Low Balance Warnings (daily at 9 AM)
0 9 * * * {$phpPath} {$projectPath}/artisan system:automated-maintenance --task=low-balance-warnings >> /dev/null 2>&1

# Database Maintenance (daily at 2 AM)
0 2 * * * {$phpPath} {$projectPath}/artisan db:maintenance --cleanup --optimize >> /dev/null 2>&1

# System Backup (daily at 3 AM)
0 3 * * * {$phpPath} {$projectPath}/artisan backup:system --compress --retention=30 >> /dev/null 2>&1

# Cron Job Cleanup (weekly on Sunday at 4 AM)
0 4 * * 0 {$phpPath} {$projectPath}/artisan cron:monitor cleanup --days=30 >> /dev/null 2>&1

# Kill Stuck Jobs (hourly)
0 * * * * {$phpPath} {$projectPath}/artisan cron:monitor kill-stuck --max-runtime=120 >> /dev/null 2>&1

# Notes:
# - Adjust {$phpPath} to match your hosting provider's PHP path
# - Adjust {$projectPath} to match your actual project directory
# - The main scheduler (first line) is the most important - it runs all other scheduled tasks
# - The backup jobs ensure critical functions continue even if the scheduler fails
# - All output is redirected to /dev/null to prevent email spam
# - Test each job manually before adding to cron: {$phpPath} {$projectPath}/artisan command:name
EOT;
    }

    protected function getDirectCronContent(string $phpPath, string $projectPath): string
    {
        return <<<EOT
#!/bin/bash

# FreePBX VoIP Platform - Direct Cron Setup Script
# This script sets up all required cron jobs for the VoIP platform

echo "Setting up cron jobs for FreePBX VoIP Platform..."

# Backup existing crontab
crontab -l > /tmp/crontab_backup_\$(date +%Y%m%d_%H%M%S) 2>/dev/null || echo "No existing crontab found"

# Create new crontab content
cat << 'CRON_CONTENT' > /tmp/voip_platform_cron
# FreePBX VoIP Platform Cron Jobs
# Generated on \$(date)

# Main Laravel Scheduler (REQUIRED - runs every minute)
* * * * * {$phpPath} {$projectPath}/artisan schedule:run >> /dev/null 2>&1

# Critical Jobs (Backup - run directly if scheduler fails)
*/5 * * * * {$phpPath} {$projectPath}/artisan cdr:automated-processing --batch-size=50 >> /dev/null 2>&1
*/30 * * * * {$phpPath} {$projectPath}/artisan freepbx:automated-sync --sync-mode=incremental >> /dev/null 2>&1
*/2 * * * * {$phpPath} {$projectPath}/artisan billing:monitor-realtime --terminate >> /dev/null 2>&1
*/5 * * * * {$phpPath} {$projectPath}/artisan system:health-check --alert >> /dev/null 2>&1
0 2 1 * * {$phpPath} {$projectPath}/artisan billing:monthly-did-charges --suspend-insufficient >> /dev/null 2>&1
0 9 * * * {$phpPath} {$projectPath}/artisan system:automated-maintenance --task=low-balance-warnings >> /dev/null 2>&1
0 2 * * * {$phpPath} {$projectPath}/artisan db:maintenance --cleanup --optimize >> /dev/null 2>&1
0 3 * * * {$phpPath} {$projectPath}/artisan backup:system --compress --retention=30 >> /dev/null 2>&1
0 4 * * 0 {$phpPath} {$projectPath}/artisan cron:monitor cleanup --days=30 >> /dev/null 2>&1
0 * * * * {$phpPath} {$projectPath}/artisan cron:monitor kill-stuck --max-runtime=120 >> /dev/null 2>&1
CRON_CONTENT

# Install the new crontab
if crontab /tmp/voip_platform_cron; then
    echo "✓ Cron jobs installed successfully!"
    echo ""
    echo "Current crontab:"
    crontab -l
    echo ""
    echo "✓ Setup complete!"
    echo ""
    echo "To verify cron jobs are working:"
    echo "1. Check logs: tail -f {$projectPath}/storage/logs/cron.log"
    echo "2. Monitor jobs: {$phpPath} {$projectPath}/artisan cron:monitor status"
    echo "3. Check system health: {$phpPath} {$projectPath}/artisan cron:monitor health"
else
    echo "✗ Failed to install cron jobs!"
    echo "Please check the PHP path and project path are correct."
    exit 1
fi

# Cleanup
rm -f /tmp/voip_platform_cron

echo ""
echo "Important notes:"
echo "- Make sure the web server user has permission to run these commands"
echo "- Monitor the first few executions to ensure everything works correctly"
echo "- Check {$projectPath}/storage/logs/ for any error messages"
EOT;
    }

    protected function getSystemdTimerContent(): string
    {
        return <<<EOT
[Unit]
Description=Laravel Scheduler Timer
Requires=laravel-scheduler.service

[Timer]
OnCalendar=*:*:00
Persistent=true

[Install]
WantedBy=timers.target
EOT;
    }

    protected function getSystemdServiceContent(string $phpPath, string $projectPath): string
    {
        return <<<EOT
[Unit]
Description=Laravel Scheduler
After=network.target

[Service]
Type=oneshot
User=www-data
Group=www-data
WorkingDirectory={$projectPath}
ExecStart={$phpPath} {$projectPath}/artisan schedule:run
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOT;
    }
}