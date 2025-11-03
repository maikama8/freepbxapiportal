# FreePBX VoIP Platform - Cron Job Setup Guide

This guide provides comprehensive instructions for setting up cron jobs for the FreePBX VoIP Platform across different hosting environments.

## Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [Quick Setup](#quick-setup)
4. [cPanel Setup](#cpanel-setup)
5. [Direct Server Setup](#direct-server-setup)
6. [Systemd Setup](#systemd-setup)
7. [Verification](#verification)
8. [Troubleshooting](#troubleshooting)
9. [Monitoring](#monitoring)

## Overview

The FreePBX VoIP Platform requires several cron jobs to function properly:

- **Laravel Scheduler** (every minute) - Main scheduler that runs all other tasks
- **CDR Processing** (every 5 minutes) - Process call detail records and billing
- **FreePBX Sync** (every 30 minutes) - Synchronize extensions with FreePBX
- **Real-time Billing** (every 2 minutes) - Monitor and terminate calls based on balance
- **System Health** (every 5 minutes) - Monitor system health and send alerts
- **Monthly DID Billing** (1st of month) - Process monthly DID charges
- **Maintenance Tasks** (daily/weekly) - Database cleanup, backups, etc.

## Prerequisites

Before setting up cron jobs, ensure:

1. **PHP Path**: Know the correct path to PHP on your server
2. **Project Path**: Know the full path to your Laravel project
3. **Permissions**: Ensure the web server user can execute artisan commands
4. **Dependencies**: All Composer dependencies are installed
5. **Environment**: `.env` file is properly configured

### Finding PHP Path

```bash
# Common locations:
which php          # Usually /usr/bin/php
which php8.1       # Version-specific
which php8.2       # Version-specific

# On shared hosting, check:
/usr/local/bin/php
/opt/php81/bin/php
/opt/alt/php81/usr/bin/php
```

### Finding Project Path

```bash
# If you're in the project directory:
pwd

# Common locations:
/home/username/public_html/voip-platform
/var/www/html/voip-platform
/home/username/domains/example.com/public_html
```

## Quick Setup

### Generate Setup Scripts

The platform includes a command to generate setup scripts for your environment:

```bash
# For cPanel hosting
php artisan cron:generate-setup --type=cpanel --php-path=/usr/bin/php --project-path=/home/user/public_html

# For direct server access
php artisan cron:generate-setup --type=direct --php-path=/usr/bin/php --project-path=/var/www/html/voip-platform

# For systemd-based systems
php artisan cron:generate-setup --type=systemd --php-path=/usr/bin/php --project-path=/var/www/html/voip-platform
```

## cPanel Setup

### Step 1: Access Cron Jobs

1. Log into your cPanel
2. Navigate to "Advanced" section
3. Click on "Cron Jobs"

### Step 2: Generate cPanel Configuration

```bash
php artisan cron:generate-setup --type=cpanel --output=storage/app/cpanel-cron.txt
```

### Step 3: Add Cron Jobs

Copy each line from the generated file and add as separate cron jobs in cPanel:

#### Main Scheduler (REQUIRED)
- **Minute**: `*`
- **Hour**: `*`
- **Day**: `*`
- **Month**: `*`
- **Weekday**: `*`
- **Command**: `/usr/bin/php /home/username/public_html/artisan schedule:run`

#### Critical Backup Jobs

Add these as backup in case the main scheduler fails:

```bash
# CDR Processing (every 5 minutes)
*/5 * * * * /usr/bin/php /home/username/public_html/artisan cdr:automated-processing --batch-size=50

# FreePBX Sync (every 30 minutes)
*/30 * * * * /usr/bin/php /home/username/public_html/artisan freepbx:automated-sync --sync-mode=incremental

# Real-time Billing (every 2 minutes)
*/2 * * * * /usr/bin/php /home/username/public_html/artisan billing:monitor-realtime --terminate

# System Health (every 5 minutes)
*/5 * * * * /usr/bin/php /home/username/public_html/artisan system:health-check --alert
```

### Step 4: Configure Email Notifications

In cPanel cron jobs section, set your email address to receive notifications about cron job failures.

## Direct Server Setup

### Step 1: Generate Setup Script

```bash
php artisan cron:generate-setup --type=direct --output=/tmp/setup-cron.sh
chmod +x /tmp/setup-cron.sh
```

### Step 2: Run Setup Script

```bash
sudo /tmp/setup-cron.sh
```

### Step 3: Manual Setup (Alternative)

If you prefer manual setup:

```bash
# Edit crontab
crontab -e

# Add the following lines:
* * * * * /usr/bin/php /var/www/html/voip-platform/artisan schedule:run >> /dev/null 2>&1
*/5 * * * * /usr/bin/php /var/www/html/voip-platform/artisan cdr:automated-processing --batch-size=50 >> /dev/null 2>&1
*/30 * * * * /usr/bin/php /var/www/html/voip-platform/artisan freepbx:automated-sync --sync-mode=incremental >> /dev/null 2>&1
*/2 * * * * /usr/bin/php /var/www/html/voip-platform/artisan billing:monitor-realtime --terminate >> /dev/null 2>&1
*/5 * * * * /usr/bin/php /var/www/html/voip-platform/artisan system:health-check --alert >> /dev/null 2>&1
0 2 1 * * /usr/bin/php /var/www/html/voip-platform/artisan billing:monthly-did-charges --suspend-insufficient >> /dev/null 2>&1
0 9 * * * /usr/bin/php /var/www/html/voip-platform/artisan system:automated-maintenance --task=low-balance-warnings >> /dev/null 2>&1
```

## Systemd Setup

### Step 1: Generate Systemd Files

```bash
php artisan cron:generate-setup --type=systemd --output=/tmp/laravel-scheduler
```

### Step 2: Install Systemd Files

```bash
sudo cp /tmp/laravel-scheduler.service /etc/systemd/system/
sudo cp /tmp/laravel-scheduler.timer /etc/systemd/system/
```

### Step 3: Enable and Start

```bash
sudo systemctl daemon-reload
sudo systemctl enable laravel-scheduler.timer
sudo systemctl start laravel-scheduler.timer
```

### Step 4: Verify Status

```bash
sudo systemctl status laravel-scheduler.timer
sudo systemctl list-timers | grep laravel
```

## Verification

### Test Individual Commands

Before setting up cron jobs, test each command manually:

```bash
# Test main scheduler
php artisan schedule:run

# Test individual commands
php artisan cdr:automated-processing --batch-size=10
php artisan freepbx:automated-sync --sync-mode=incremental
php artisan billing:monitor-realtime
php artisan system:health-check
```

### Monitor Cron Job Execution

```bash
# Check cron job status
php artisan cron:monitor status

# View job history
php artisan cron:monitor history --limit=20

# Check system health
php artisan cron:monitor health
```

### Check Logs

```bash
# View cron job logs
tail -f storage/logs/cron.log

# View Laravel logs
tail -f storage/logs/laravel.log

# View system logs (Linux)
tail -f /var/log/cron
```

## Troubleshooting

### Common Issues

#### 1. Permission Denied

```bash
# Fix permissions
chmod +x artisan
chown -R www-data:www-data storage/
chmod -R 775 storage/
```

#### 2. PHP Path Issues

```bash
# Test PHP path
/usr/bin/php -v
/usr/local/bin/php -v

# Use full path in cron jobs
which php
```

#### 3. Environment Issues

```bash
# Ensure .env file exists and is readable
ls -la .env
cat .env | head -5

# Test database connection
php artisan tinker
>>> DB::connection()->getPdo();
```

#### 4. Memory Issues

```bash
# Increase memory limit in cron jobs
/usr/bin/php -d memory_limit=512M /path/to/artisan command:name
```

#### 5. Timezone Issues

```bash
# Set timezone in .env
APP_TIMEZONE=America/New_York

# Or in cron job
TZ=America/New_York /usr/bin/php /path/to/artisan schedule:run
```

### Debug Mode

Enable debug mode for troubleshooting:

```bash
# Run with verbose output
php artisan schedule:run -v

# Run specific command with debug
php artisan cdr:automated-processing --batch-size=1 -vvv
```

### Health Check

Use the built-in health monitoring:

```bash
# Check overall health
php artisan cron:health-monitor --alert

# Monitor with email alerts
php artisan cron:health-monitor --alert --email=admin@example.com
```

## Monitoring

### Dashboard Access

Access the cron job management dashboard at:
```
https://your-domain.com/admin/cron-jobs
```

### Command Line Monitoring

```bash
# Real-time status
php artisan cron:monitor status

# Performance analysis
php artisan cron:monitor statistics --days=7

# Clean up old records
php artisan cron:monitor cleanup --days=30

# Kill stuck jobs
php artisan cron:monitor kill-stuck --max-runtime=60
```

### Log Monitoring

Set up log rotation for cron logs:

```bash
# Add to /etc/logrotate.d/laravel-cron
/path/to/project/storage/logs/cron.log {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
    create 644 www-data www-data
}
```

### Alerting

Configure alerts for critical issues:

1. **Email Alerts**: Set up in cPanel or configure SMTP
2. **Slack Alerts**: Configure webhook URL in `.env`
3. **Log Monitoring**: Use tools like Logwatch or custom scripts

### Performance Optimization

Generate performance reports:

```bash
# Generate optimization report
php artisan cron:performance-report --days=30 --output=storage/app/performance-report.json
```

## Best Practices

1. **Always test commands manually first**
2. **Use the main scheduler when possible**
3. **Monitor logs regularly**
4. **Set up proper alerting**
5. **Keep backups of crontab**
6. **Use appropriate batch sizes**
7. **Monitor resource usage**
8. **Regular performance reviews**

## Support

If you encounter issues:

1. Check the troubleshooting section above
2. Review logs in `storage/logs/`
3. Use the monitoring dashboard
4. Test commands manually
5. Check server resources and permissions

For additional support, consult the main application documentation or contact your system administrator.