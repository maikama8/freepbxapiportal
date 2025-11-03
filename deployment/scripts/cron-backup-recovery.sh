#!/bin/bash

# FreePBX VoIP Platform - Cron Job Backup and Recovery Script
# This script provides backup and recovery functionality for cron job configurations

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_PATH="${1:-/var/www/html/freepbx-voip-platform}"
BACKUP_DIR="${2:-$PROJECT_PATH/storage/app/cron-backups}"
ACTION="${3:-backup}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" >&2
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Create backup directory
create_backup_dir() {
    if [ ! -d "$BACKUP_DIR" ]; then
        log "Creating backup directory: $BACKUP_DIR"
        mkdir -p "$BACKUP_DIR"
        chmod 755 "$BACKUP_DIR"
    fi
}

# Backup current crontab
backup_crontab() {
    local timestamp=$(date '+%Y%m%d_%H%M%S')
    local backup_file="$BACKUP_DIR/crontab_backup_$timestamp.txt"
    
    log "Backing up current crontab to: $backup_file"
    
    if crontab -l > "$backup_file" 2>/dev/null; then
        log "Crontab backup completed successfully"
        echo "$backup_file"
    else
        warning "No existing crontab found or crontab is empty"
        echo "# No existing crontab - $(date)" > "$backup_file"
        echo "$backup_file"
    fi
}

# Backup monitoring scripts
backup_monitoring_scripts() {
    local timestamp=$(date '+%Y%m%d_%H%M%S')
    local backup_file="$BACKUP_DIR/monitoring_scripts_$timestamp.tar.gz"
    
    log "Backing up monitoring scripts to: $backup_file"
    
    local scripts_to_backup=(
        "/usr/local/bin/cron-alert.sh"
        "/usr/local/bin/cron-health-check.sh"
        "/usr/local/bin/cron-log-monitor.sh"
        "/usr/local/bin/verify-backups.sh"
        "/etc/logrotate.d/freepbx-voip-platform"
    )
    
    local existing_scripts=()
    for script in "${scripts_to_backup[@]}"; do
        if [ -f "$script" ]; then
            existing_scripts+=("$script")
        fi
    done
    
    if [ ${#existing_scripts[@]} -gt 0 ]; then
        tar -czf "$backup_file" "${existing_scripts[@]}" 2>/dev/null || {
            warning "Some monitoring scripts could not be backed up"
        }
        log "Monitoring scripts backup completed"
    else
        warning "No monitoring scripts found to backup"
    fi
    
    echo "$backup_file"
}

# Backup systemd services (if they exist)
backup_systemd_services() {
    local timestamp=$(date '+%Y%m%d_%H%M%S')
    local backup_file="$BACKUP_DIR/systemd_services_$timestamp.tar.gz"
    
    if command -v systemctl >/dev/null 2>&1; then
        log "Backing up systemd services to: $backup_file"
        
        local services_to_backup=(
            "/etc/systemd/system/freepbx-monitor.service"
            "/etc/systemd/system/freepbx-monitor.timer"
            "/etc/systemd/system/laravel-scheduler.service"
            "/etc/systemd/system/laravel-scheduler.timer"
        )
        
        local existing_services=()
        for service in "${services_to_backup[@]}"; do
            if [ -f "$service" ]; then
                existing_services+=("$service")
            fi
        done
        
        if [ ${#existing_services[@]} -gt 0 ]; then
            tar -czf "$backup_file" "${existing_services[@]}" 2>/dev/null
            log "Systemd services backup completed"
        else
            warning "No systemd services found to backup"
        fi
    else
        warning "Systemd not available on this system"
    fi
    
    echo "$backup_file"
}

# Create comprehensive backup
create_full_backup() {
    local timestamp=$(date '+%Y%m%d_%H%M%S')
    local backup_name="cron_full_backup_$timestamp"
    local backup_path="$BACKUP_DIR/$backup_name"
    
    log "Creating comprehensive cron job backup: $backup_name"
    
    create_backup_dir
    mkdir -p "$backup_path"
    
    # Backup crontab
    local crontab_backup=$(backup_crontab)
    cp "$crontab_backup" "$backup_path/"
    
    # Backup monitoring scripts
    local scripts_backup=$(backup_monitoring_scripts)
    if [ -f "$scripts_backup" ]; then
        cp "$scripts_backup" "$backup_path/"
    fi
    
    # Backup systemd services
    local systemd_backup=$(backup_systemd_services)
    if [ -f "$systemd_backup" ]; then
        cp "$systemd_backup" "$backup_path/"
    fi
    
    # Create backup manifest
    cat > "$backup_path/manifest.txt" << EOF
FreePBX VoIP Platform - Cron Job Backup Manifest
================================================
Backup Created: $(date)
Backup Name: $backup_name
Server: $(hostname)
User: $(whoami)

Contents:
- Crontab configuration
- Monitoring scripts
- Systemd services (if applicable)
- Log rotation configuration

Restoration Command:
$0 $PROJECT_PATH $BACKUP_DIR restore $backup_name

Notes:
- Verify paths in crontab before restoration
- Test monitoring scripts after restoration
- Check systemd service status after restoration
EOF
    
    # Create compressed archive
    cd "$BACKUP_DIR"
    tar -czf "${backup_name}.tar.gz" "$backup_name/"
    rm -rf "$backup_path"
    
    log "Full backup created: $BACKUP_DIR/${backup_name}.tar.gz"
    echo "$BACKUP_DIR/${backup_name}.tar.gz"
}

# List available backups
list_backups() {
    log "Available cron job backups in $BACKUP_DIR:"
    echo ""
    
    if [ ! -d "$BACKUP_DIR" ]; then
        warning "Backup directory does not exist: $BACKUP_DIR"
        return 1
    fi
    
    local backups=($(ls -1 "$BACKUP_DIR"/cron_full_backup_*.tar.gz 2>/dev/null || true))
    
    if [ ${#backups[@]} -eq 0 ]; then
        warning "No backups found"
        return 1
    fi
    
    printf "%-30s %-20s %-10s\n" "Backup Name" "Date Created" "Size"
    printf "%-30s %-20s %-10s\n" "----------" "------------" "----"
    
    for backup in "${backups[@]}"; do
        local basename=$(basename "$backup" .tar.gz)
        local date_created=$(stat -c %y "$backup" 2>/dev/null | cut -d' ' -f1 || echo "Unknown")
        local size=$(du -h "$backup" 2>/dev/null | cut -f1 || echo "Unknown")
        printf "%-30s %-20s %-10s\n" "$basename" "$date_created" "$size"
    done
    
    echo ""
}

# Restore from backup
restore_backup() {
    local backup_name="$1"
    local backup_file="$BACKUP_DIR/${backup_name}.tar.gz"
    
    if [ -z "$backup_name" ]; then
        error "Backup name is required for restoration"
        list_backups
        return 1
    fi
    
    if [ ! -f "$backup_file" ]; then
        error "Backup file not found: $backup_file"
        list_backups
        return 1
    fi
    
    log "Restoring cron job configuration from: $backup_name"
    
    # Create temporary restoration directory
    local temp_dir=$(mktemp -d)
    cd "$temp_dir"
    
    # Extract backup
    tar -xzf "$backup_file"
    cd "$backup_name"
    
    # Show manifest
    if [ -f "manifest.txt" ]; then
        log "Backup manifest:"
        cat "manifest.txt"
        echo ""
    fi
    
    # Confirm restoration
    read -p "Do you want to proceed with restoration? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log "Restoration cancelled"
        rm -rf "$temp_dir"
        return 0
    fi
    
    # Backup current configuration before restoration
    log "Creating safety backup of current configuration..."
    local safety_backup=$(create_full_backup)
    log "Safety backup created: $safety_backup"
    
    # Restore crontab
    local crontab_file=$(ls crontab_backup_*.txt 2>/dev/null | head -1)
    if [ -f "$crontab_file" ]; then
        log "Restoring crontab from: $crontab_file"
        crontab "$crontab_file"
        log "Crontab restored successfully"
    else
        warning "No crontab backup found in archive"
    fi
    
    # Restore monitoring scripts
    local scripts_file=$(ls monitoring_scripts_*.tar.gz 2>/dev/null | head -1)
    if [ -f "$scripts_file" ]; then
        log "Restoring monitoring scripts from: $scripts_file"
        cd /
        tar -xzf "$temp_dir/$backup_name/$scripts_file"
        log "Monitoring scripts restored successfully"
        cd "$temp_dir/$backup_name"
    else
        warning "No monitoring scripts backup found in archive"
    fi
    
    # Restore systemd services
    local systemd_file=$(ls systemd_services_*.tar.gz 2>/dev/null | head -1)
    if [ -f "$systemd_file" ] && command -v systemctl >/dev/null 2>&1; then
        log "Restoring systemd services from: $systemd_file"
        cd /
        tar -xzf "$temp_dir/$backup_name/$systemd_file"
        systemctl daemon-reload
        log "Systemd services restored successfully"
        cd "$temp_dir/$backup_name"
    else
        if [ -f "$systemd_file" ]; then
            warning "Systemd not available, skipping service restoration"
        else
            warning "No systemd services backup found in archive"
        fi
    fi
    
    # Clean up
    rm -rf "$temp_dir"
    
    log "Restoration completed successfully"
    log "Please verify the restored configuration:"
    log "  - Check crontab: crontab -l"
    log "  - Test monitoring scripts manually"
    log "  - Verify systemd services: systemctl status freepbx-monitor.timer"
}

# Clean up old backups
cleanup_old_backups() {
    local retention_days="${1:-30}"
    
    log "Cleaning up backups older than $retention_days days..."
    
    if [ ! -d "$BACKUP_DIR" ]; then
        warning "Backup directory does not exist: $BACKUP_DIR"
        return 0
    fi
    
    local deleted_count=0
    while IFS= read -r -d '' backup; do
        rm -f "$backup"
        ((deleted_count++))
        log "Deleted old backup: $(basename "$backup")"
    done < <(find "$BACKUP_DIR" -name "cron_full_backup_*.tar.gz" -mtime +$retention_days -print0 2>/dev/null)
    
    log "Cleanup completed. Deleted $deleted_count old backups."
}

# Verify backup integrity
verify_backup() {
    local backup_name="$1"
    local backup_file="$BACKUP_DIR/${backup_name}.tar.gz"
    
    if [ -z "$backup_name" ]; then
        error "Backup name is required for verification"
        return 1
    fi
    
    if [ ! -f "$backup_file" ]; then
        error "Backup file not found: $backup_file"
        return 1
    fi
    
    log "Verifying backup integrity: $backup_name"
    
    # Test archive integrity
    if tar -tzf "$backup_file" >/dev/null 2>&1; then
        log "Archive integrity: OK"
    else
        error "Archive is corrupted or invalid"
        return 1
    fi
    
    # Check contents
    local contents=$(tar -tzf "$backup_file" | wc -l)
    log "Archive contains $contents files/directories"
    
    # Extract and verify manifest
    local temp_dir=$(mktemp -d)
    cd "$temp_dir"
    tar -xzf "$backup_file"
    
    if [ -f "$backup_name/manifest.txt" ]; then
        log "Manifest found and readable"
        echo ""
        cat "$backup_name/manifest.txt"
    else
        warning "No manifest found in backup"
    fi
    
    rm -rf "$temp_dir"
    log "Backup verification completed"
}

# Main function
main() {
    case "$ACTION" in
        "backup")
            create_full_backup
            ;;
        "list")
            list_backups
            ;;
        "restore")
            restore_backup "$4"
            ;;
        "cleanup")
            cleanup_old_backups "$4"
            ;;
        "verify")
            verify_backup "$4"
            ;;
        *)
            echo "FreePBX VoIP Platform - Cron Job Backup and Recovery"
            echo "Usage: $0 <project_path> <backup_dir> <action> [options]"
            echo ""
            echo "Actions:"
            echo "  backup                    Create a full backup of cron configuration"
            echo "  list                      List available backups"
            echo "  restore <backup_name>     Restore from a specific backup"
            echo "  cleanup [days]            Clean up backups older than specified days (default: 30)"
            echo "  verify <backup_name>      Verify backup integrity"
            echo ""
            echo "Examples:"
            echo "  $0 /var/www/html/voip backup"
            echo "  $0 /var/www/html/voip list"
            echo "  $0 /var/www/html/voip restore cron_full_backup_20231201_120000"
            echo "  $0 /var/www/html/voip cleanup 14"
            echo "  $0 /var/www/html/voip verify cron_full_backup_20231201_120000"
            exit 1
            ;;
    esac
}

# Run main function
main "$@"