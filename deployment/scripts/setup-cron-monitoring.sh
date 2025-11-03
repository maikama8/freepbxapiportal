#!/bin/bash

# FreePBX VoIP Platform - Cron Job Monitoring Setup Script
# This script sets up comprehensive monitoring and alerting for cron jobs

set -e

# Configuration
PROJECT_PATH="${1:-/var/www/html/freepbx-voip-platform}"
PHP_PATH="${2:-/usr/bin/php}"
ADMIN_EMAIL="${3:-admin@localhost}"
LOG_DIR="/var/log"
ALERT_SCRIPT="/usr/local/bin/cron-alert.sh"

echo "Setting up cron job monitoring for FreePBX VoIP Platform..."
echo "Project Path: $PROJECT_PATH"
echo "PHP Path: $PHP_PATH"
echo "Admin Email: $ADMIN_EMAIL"

# Verify project exists
if [ ! -d "$PROJECT_PATH" ]; then
    echo "ERROR: Project directory not found: $PROJECT_PATH"
    exit 1
fi

# Verify PHP exists
if [ ! -x "$PHP_PATH" ]; then
    echo "ERROR: PHP executable not found: $PHP_PATH"
    exit 1
fi

# Create log directories
echo "Creating log directories..."
mkdir -p "$LOG_DIR"
chmod 755 "$LOG_DIR"

# Create cron alert script
echo "Creating cron alert script..."
cat > "$ALERT_SCRIPT" << 'EOF'
#!/bin/bash

# Cron Job Alert Script
# Usage: cron-alert.sh <job_name> <status> <message>

JOB_NAME="$1"
STATUS="$2"
MESSAGE="$3"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
HOSTNAME=$(hostname)

# Log the alert
echo "[$TIMESTAMP] CRON ALERT: $JOB_NAME - $STATUS - $MESSAGE" >> /var/log/cron-alerts.log

# Send email alert (if mail is configured)
if command -v mail >/dev/null 2>&1; then
    {
        echo "Cron Job Alert - $HOSTNAME"
        echo "=========================="
        echo "Job Name: $JOB_NAME"
        echo "Status: $STATUS"
        echo "Message: $MESSAGE"
        echo "Timestamp: $TIMESTAMP"
        echo "Server: $HOSTNAME"
        echo ""
        echo "Please check the system immediately."
    } | mail -s "CRON ALERT: $JOB_NAME - $STATUS" "$ADMIN_EMAIL"
fi

# Log to syslog
logger -t "cron-monitor" "ALERT: $JOB_NAME - $STATUS - $MESSAGE"
EOF

chmod +x "$ALERT_SCRIPT"

# Create cron job health check script
echo "Creating cron job health check script..."
cat > "/usr/local/bin/cron-health-check.sh" << EOF
#!/bin/bash

# Cron Job Health Check Script
# Monitors critical cron jobs and sends alerts if they fail or are overdue

PROJECT_PATH="$PROJECT_PATH"
PHP_PATH="$PHP_PATH"
ADMIN_EMAIL="$ADMIN_EMAIL"

# Function to check if a job has run recently
check_job_health() {
    local job_name="\$1"
    local max_minutes="\$2"
    
    # Run the health check command
    cd "\$PROJECT_PATH"
    result=\$("\$PHP_PATH" artisan cron:monitor status --job="\$job_name" --format=json 2>/dev/null)
    
    if [ \$? -ne 0 ]; then
        "$ALERT_SCRIPT" "\$job_name" "ERROR" "Failed to check job status"
        return 1
    fi
    
    # Parse the result (simplified - in production you'd use jq)
    if echo "\$result" | grep -q "overdue"; then
        "$ALERT_SCRIPT" "\$job_name" "OVERDUE" "Job has not run within expected timeframe"
        return 1
    fi
    
    return 0
}

# Check critical jobs
check_job_health "cdr:automated-processing" 10
check_job_health "freepbx:automated-sync" 35
check_job_health "billing:monitor-realtime" 5
check_job_health "system:health-check" 10

# Check system resources
disk_usage=\$(df / | awk 'NR==2 {print \$5}' | sed 's/%//')
if [ "\$disk_usage" -gt 90 ]; then
    "$ALERT_SCRIPT" "system" "CRITICAL" "Disk usage is \${disk_usage}%"
fi

memory_usage=\$(free | awk 'NR==2{printf "%.0f", \$3/\$2*100}')
if [ "\$memory_usage" -gt 90 ]; then
    "$ALERT_SCRIPT" "system" "WARNING" "Memory usage is \${memory_usage}%"
fi

# Check for stuck processes
stuck_processes=\$(ps aux | awk '\$3 > 80.0 || \$4 > 80.0' | wc -l)
if [ "\$stuck_processes" -gt 5 ]; then
    "$ALERT_SCRIPT" "system" "WARNING" "\$stuck_processes processes using high resources"
fi
EOF

chmod +x "/usr/local/bin/cron-health-check.sh"

# Create log monitoring script
echo "Creating log monitoring script..."
cat > "/usr/local/bin/cron-log-monitor.sh" << EOF
#!/bin/bash

# Cron Log Monitoring Script
# Monitors cron job logs for errors and sends alerts

LOG_DIR="/var/log"
ADMIN_EMAIL="$ADMIN_EMAIL"
ALERT_SCRIPT="$ALERT_SCRIPT"

# Check for errors in cron logs
check_log_errors() {
    local log_file="\$1"
    local job_name="\$2"
    
    if [ ! -f "\$log_file" ]; then
        return 0
    fi
    
    # Check for errors in the last 10 minutes
    error_count=\$(tail -n 100 "\$log_file" | grep -i "error\|failed\|exception" | wc -l)
    
    if [ "\$error_count" -gt 0 ]; then
        "\$ALERT_SCRIPT" "\$job_name" "ERROR" "Found \$error_count errors in log file"
    fi
}

# Monitor critical log files
check_log_errors "\$LOG_DIR/cron-cdr.log" "cdr-processing"
check_log_errors "\$LOG_DIR/cron-billing.log" "billing-monitor"
check_log_errors "\$LOG_DIR/cron-freepbx.log" "freepbx-sync"
check_log_errors "\$LOG_DIR/cron-health.log" "health-check"

# Check Laravel application logs
if [ -f "$PROJECT_PATH/storage/logs/laravel.log" ]; then
    recent_errors=\$(tail -n 50 "$PROJECT_PATH/storage/logs/laravel.log" | grep -i "error\|critical\|emergency" | wc -l)
    if [ "\$recent_errors" -gt 5 ]; then
        "\$ALERT_SCRIPT" "application" "ERROR" "Found \$recent_errors recent errors in application log"
    fi
fi
EOF

chmod +x "/usr/local/bin/cron-log-monitor.sh"

# Create backup verification script
echo "Creating backup verification script..."
cat > "/usr/local/bin/verify-backups.sh" << EOF
#!/bin/bash

# Backup Verification Script
# Verifies that backups are being created successfully

PROJECT_PATH="$PROJECT_PATH"
BACKUP_DIR="\$PROJECT_PATH/storage/app/backups"
ALERT_SCRIPT="$ALERT_SCRIPT"

# Check if backup directory exists
if [ ! -d "\$BACKUP_DIR" ]; then
    "\$ALERT_SCRIPT" "backup" "ERROR" "Backup directory does not exist: \$BACKUP_DIR"
    exit 1
fi

# Check for recent backups (within last 25 hours)
recent_backup=\$(find "\$BACKUP_DIR" -name "*.tar.gz" -mtime -1 | head -1)

if [ -z "\$recent_backup" ]; then
    "\$ALERT_SCRIPT" "backup" "ERROR" "No recent backups found in \$BACKUP_DIR"
    exit 1
fi

# Check backup file size (should be > 1MB)
backup_size=\$(stat -c%s "\$recent_backup" 2>/dev/null || echo "0")
if [ "\$backup_size" -lt 1048576 ]; then
    "\$ALERT_SCRIPT" "backup" "WARNING" "Recent backup file is suspiciously small: \$backup_size bytes"
fi

echo "Backup verification completed successfully"
EOF

chmod +x "/usr/local/bin/verify-backups.sh"

# Set up log rotation
echo "Setting up log rotation..."
cp "$PROJECT_PATH/deployment/cron/logrotate.conf" "/etc/logrotate.d/freepbx-voip-platform"
sed -i "s|/path/to/project|$PROJECT_PATH|g" "/etc/logrotate.d/freepbx-voip-platform"

# Create monitoring cron jobs
echo "Setting up monitoring cron jobs..."
cat > "/tmp/monitoring-cron.txt" << EOF
# FreePBX VoIP Platform - Monitoring Cron Jobs

# Health check every 5 minutes
*/5 * * * * /usr/local/bin/cron-health-check.sh >> /var/log/cron-health-monitor.log 2>&1

# Log monitoring every 10 minutes
*/10 * * * * /usr/local/bin/cron-log-monitor.sh >> /var/log/cron-log-monitor.log 2>&1

# Backup verification daily at 4:30 AM
30 4 * * * /usr/local/bin/verify-backups.sh >> /var/log/cron-backup-verify.log 2>&1

# Disk space monitoring every hour
0 * * * * df -h | awk '\$5 > 85 {print "WARNING: Disk " \$1 " is " \$5 " full"}' | while read line; do echo "\$line" | logger -t disk-monitor; done

# Process monitoring every 15 minutes
*/15 * * * * ps aux | awk '\$3 > 90.0 || \$4 > 90.0 {print "HIGH USAGE: PID " \$2 " - " \$11 " (CPU: " \$3 "%, MEM: " \$4 "%)"}' | head -3 | logger -t process-monitor

# Network connectivity check every 30 minutes
*/30 * * * * ping -c 1 8.8.8.8 > /dev/null 2>&1 || echo "Network connectivity issue detected" | logger -t network-monitor

# SSL certificate expiry check daily at 6 AM
0 6 * * * openssl x509 -in /etc/ssl/certs/your-cert.pem -noout -dates 2>/dev/null | grep "notAfter" | awk -F= '{print \$2}' | xargs -I {} date -d "{}" +%s | awk '{if (\$1 - systime() < 2592000) print "SSL certificate expires within 30 days"}' | logger -t ssl-monitor
EOF

# Install monitoring cron jobs
echo "Installing monitoring cron jobs..."
crontab -l > /tmp/current-cron.txt 2>/dev/null || echo "# New crontab" > /tmp/current-cron.txt
cat /tmp/current-cron.txt /tmp/monitoring-cron.txt | crontab -

# Create systemd service for critical monitoring (if systemd is available)
if command -v systemctl >/dev/null 2>&1; then
    echo "Creating systemd monitoring service..."
    
    cat > "/etc/systemd/system/freepbx-monitor.service" << EOF
[Unit]
Description=FreePBX VoIP Platform Critical Monitor
After=network.target

[Service]
Type=oneshot
ExecStart=/usr/local/bin/cron-health-check.sh
User=root
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF

    cat > "/etc/systemd/system/freepbx-monitor.timer" << EOF
[Unit]
Description=Run FreePBX Monitor every 5 minutes
Requires=freepbx-monitor.service

[Timer]
OnCalendar=*:0/5
Persistent=true

[Install]
WantedBy=timers.target
EOF

    systemctl daemon-reload
    systemctl enable freepbx-monitor.timer
    systemctl start freepbx-monitor.timer
    
    echo "Systemd monitoring service installed and started"
fi

# Create monitoring dashboard data
echo "Setting up monitoring dashboard..."
mkdir -p "$PROJECT_PATH/storage/app/monitoring"
cat > "$PROJECT_PATH/storage/app/monitoring/config.json" << EOF
{
    "monitoring": {
        "enabled": true,
        "alert_email": "$ADMIN_EMAIL",
        "critical_jobs": [
            "cdr:automated-processing",
            "freepbx:automated-sync", 
            "billing:monitor-realtime",
            "system:health-check"
        ],
        "thresholds": {
            "disk_usage": 90,
            "memory_usage": 90,
            "cpu_usage": 90,
            "job_overdue_minutes": {
                "cdr:automated-processing": 10,
                "freepbx:automated-sync": 35,
                "billing:monitor-realtime": 5,
                "system:health-check": 10
            }
        },
        "log_retention_days": 30,
        "backup_retention_days": 30
    }
}
EOF

# Set proper permissions
chown -R www-data:www-data "$PROJECT_PATH/storage/app/monitoring"
chmod -R 755 "$PROJECT_PATH/storage/app/monitoring"

# Create initial health check
echo "Running initial health check..."
cd "$PROJECT_PATH"
"$PHP_PATH" artisan cron:health-monitor --setup

echo ""
echo "Cron job monitoring setup completed successfully!"
echo ""
echo "Monitoring components installed:"
echo "- Alert script: $ALERT_SCRIPT"
echo "- Health check: /usr/local/bin/cron-health-check.sh"
echo "- Log monitor: /usr/local/bin/cron-log-monitor.sh"
echo "- Backup verification: /usr/local/bin/verify-backups.sh"
echo "- Log rotation: /etc/logrotate.d/freepbx-voip-platform"
echo ""
echo "Monitoring cron jobs have been added to the system crontab."
echo "Logs will be written to /var/log/cron-*-monitor.log"
echo ""
echo "To view monitoring status:"
echo "  $PHP_PATH $PROJECT_PATH/artisan cron:monitor status"
echo ""
echo "To access the web dashboard:"
echo "  https://your-domain.com/admin/cron-jobs"
echo ""
echo "IMPORTANT: Update the SSL certificate path in the monitoring cron job"
echo "and configure your mail server for email alerts."

# Clean up temporary files
rm -f /tmp/monitoring-cron.txt /tmp/current-cron.txt