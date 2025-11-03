#!/bin/bash

# FreePBX VoIP Platform - Database Maintenance Script
# This script performs comprehensive database maintenance for optimal performance

set -e

# Configuration
PROJECT_PATH="${1:-/var/www/html/freepbx-voip-platform}"
PHP_PATH="${2:-/usr/bin/php}"
DB_HOST="${3:-localhost}"
DB_NAME="${4:-freepbx_voip}"
DB_USER="${5:-root}"
DB_PASS="${6}"
BACKUP_DIR="${7:-$PROJECT_PATH/storage/app/backups}"
LOG_FILE="/var/log/database-maintenance.log"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE" >&2
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$LOG_FILE"
}

info() {
    echo -e "${BLUE}[INFO]${NC} $1" | tee -a "$LOG_FILE"
}

# Check prerequisites
check_prerequisites() {
    log "Checking prerequisites..."
    
    # Check if project directory exists
    if [ ! -d "$PROJECT_PATH" ]; then
        error "Project directory not found: $PROJECT_PATH"
        exit 1
    fi
    
    # Check if PHP exists
    if [ ! -x "$PHP_PATH" ]; then
        error "PHP executable not found: $PHP_PATH"
        exit 1
    fi
    
    # Check if MySQL client is available
    if ! command -v mysql >/dev/null 2>&1; then
        error "MySQL client not found. Please install mysql-client."
        exit 1
    fi
    
    # Test database connection
    if [ -n "$DB_PASS" ]; then
        mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "SELECT 1;" "$DB_NAME" >/dev/null 2>&1 || {
            error "Cannot connect to database. Please check credentials."
            exit 1
        }
    else
        mysql -h "$DB_HOST" -u "$DB_USER" -e "SELECT 1;" "$DB_NAME" >/dev/null 2>&1 || {
            error "Cannot connect to database. Please check credentials."
            exit 1
        }
    fi
    
    log "Prerequisites check completed successfully"
}

# Create backup before maintenance
create_backup() {
    log "Creating database backup before maintenance..."
    
    mkdir -p "$BACKUP_DIR"
    local backup_file="$BACKUP_DIR/db_backup_$(date +%Y%m%d_%H%M%S).sql"
    
    if [ -n "$DB_PASS" ]; then
        mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" \
            --single-transaction \
            --routines \
            --triggers \
            --events \
            --add-drop-table \
            "$DB_NAME" > "$backup_file"
    else
        mysqldump -h "$DB_HOST" -u "$DB_USER" \
            --single-transaction \
            --routines \
            --triggers \
            --events \
            --add-drop-table \
            "$DB_NAME" > "$backup_file"
    fi
    
    # Compress backup
    gzip "$backup_file"
    
    log "Database backup created: ${backup_file}.gz"
    echo "${backup_file}.gz"
}

# Run Laravel database maintenance
run_laravel_maintenance() {
    log "Running Laravel database maintenance commands..."
    
    cd "$PROJECT_PATH"
    
    # Run migrations if any are pending
    info "Checking for pending migrations..."
    "$PHP_PATH" artisan migrate --force
    
    # Run database performance monitoring
    info "Running database performance monitoring..."
    "$PHP_PATH" artisan db:performance-monitor --report --optimize
    
    # Run database maintenance command
    info "Running database maintenance..."
    "$PHP_PATH" artisan db:maintenance --cleanup --optimize --analyze
    
    log "Laravel database maintenance completed"
}

# Optimize database tables
optimize_tables() {
    log "Optimizing database tables..."
    
    # Get list of tables
    local tables
    if [ -n "$DB_PASS" ]; then
        tables=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -N -e "SHOW TABLES;" "$DB_NAME")
    else
        tables=$(mysql -h "$DB_HOST" -u "$DB_USER" -N -e "SHOW TABLES;" "$DB_NAME")
    fi
    
    local optimized_count=0
    local failed_count=0
    
    for table in $tables; do
        info "Optimizing table: $table"
        
        if [ -n "$DB_PASS" ]; then
            if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "OPTIMIZE TABLE $table;" "$DB_NAME" >/dev/null 2>&1; then
                ((optimized_count++))
            else
                warning "Failed to optimize table: $table"
                ((failed_count++))
            fi
        else
            if mysql -h "$DB_HOST" -u "$DB_USER" -e "OPTIMIZE TABLE $table;" "$DB_NAME" >/dev/null 2>&1; then
                ((optimized_count++))
            else
                warning "Failed to optimize table: $table"
                ((failed_count++))
            fi
        fi
    done
    
    log "Table optimization completed. Optimized: $optimized_count, Failed: $failed_count"
}

# Analyze tables for better query optimization
analyze_tables() {
    log "Analyzing tables for query optimization..."
    
    local tables
    if [ -n "$DB_PASS" ]; then
        tables=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -N -e "SHOW TABLES;" "$DB_NAME")
    else
        tables=$(mysql -h "$DB_HOST" -u "$DB_USER" -N -e "SHOW TABLES;" "$DB_NAME")
    fi
    
    local analyzed_count=0
    
    for table in $tables; do
        info "Analyzing table: $table"
        
        if [ -n "$DB_PASS" ]; then
            mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "ANALYZE TABLE $table;" "$DB_NAME" >/dev/null 2>&1
        else
            mysql -h "$DB_HOST" -u "$DB_USER" -e "ANALYZE TABLE $table;" "$DB_NAME" >/dev/null 2>&1
        fi
        
        ((analyzed_count++))
    done
    
    log "Table analysis completed. Analyzed: $analyzed_count tables"
}

# Check and repair tables if needed
check_and_repair_tables() {
    log "Checking and repairing tables if needed..."
    
    local tables
    if [ -n "$DB_PASS" ]; then
        tables=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -N -e "SHOW TABLES;" "$DB_NAME")
    else
        tables=$(mysql -h "$DB_HOST" -u "$DB_USER" -N -e "SHOW TABLES;" "$DB_NAME")
    fi
    
    local checked_count=0
    local repaired_count=0
    
    for table in $tables; do
        info "Checking table: $table"
        
        local check_result
        if [ -n "$DB_PASS" ]; then
            check_result=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -N -e "CHECK TABLE $table;" "$DB_NAME" | awk '{print $4}')
        else
            check_result=$(mysql -h "$DB_HOST" -u "$DB_USER" -N -e "CHECK TABLE $table;" "$DB_NAME" | awk '{print $4}')
        fi
        
        if [ "$check_result" != "OK" ]; then
            warning "Table $table needs repair. Status: $check_result"
            
            info "Repairing table: $table"
            if [ -n "$DB_PASS" ]; then
                mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "REPAIR TABLE $table;" "$DB_NAME"
            else
                mysql -h "$DB_HOST" -u "$DB_USER" -e "REPAIR TABLE $table;" "$DB_NAME"
            fi
            
            ((repaired_count++))
        fi
        
        ((checked_count++))
    done
    
    log "Table check completed. Checked: $checked_count, Repaired: $repaired_count"
}

# Update table statistics
update_statistics() {
    log "Updating table statistics..."
    
    # Update InnoDB statistics
    if [ -n "$DB_PASS" ]; then
        mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "
            SET GLOBAL innodb_stats_on_metadata = ON;
            SET GLOBAL innodb_stats_auto_recalc = ON;
        " "$DB_NAME" 2>/dev/null || warning "Could not update InnoDB statistics settings"
    else
        mysql -h "$DB_HOST" -u "$DB_USER" -e "
            SET GLOBAL innodb_stats_on_metadata = ON;
            SET GLOBAL innodb_stats_auto_recalc = ON;
        " "$DB_NAME" 2>/dev/null || warning "Could not update InnoDB statistics settings"
    fi
    
    log "Table statistics updated"
}

# Clean up old data
cleanup_old_data() {
    log "Cleaning up old data..."
    
    cd "$PROJECT_PATH"
    
    # Clean up old audit logs (older than 90 days)
    info "Cleaning up old audit logs..."
    "$PHP_PATH" artisan db:cleanup --table=audit_logs --days=90
    
    # Clean up old cron job executions (older than 30 days)
    info "Cleaning up old cron job executions..."
    "$PHP_PATH" artisan cron:monitor cleanup --days=30
    
    # Clean up old call records (archive records older than 1 year)
    info "Archiving old call records..."
    "$PHP_PATH" artisan db:archive-old-records --table=call_records --days=365
    
    # Clean up old balance transactions (archive records older than 2 years)
    info "Archiving old balance transactions..."
    "$PHP_PATH" artisan db:archive-old-records --table=balance_transactions --days=730
    
    log "Old data cleanup completed"
}

# Manage partitions
manage_partitions() {
    log "Managing table partitions..."
    
    # Add new partitions for the next 3 months
    local current_year=$(date +%Y)
    local current_month=$(date +%m)
    
    for i in {1..3}; do
        local target_month=$((current_month + i))
        local target_year=$current_year
        
        if [ $target_month -gt 12 ]; then
            target_month=$((target_month - 12))
            target_year=$((target_year + 1))
        fi
        
        local partition_value=$(printf "%04d%02d" $target_year $target_month)
        local next_partition_value=$(printf "%04d%02d" $target_year $((target_month + 1)))
        
        if [ $target_month -eq 12 ]; then
            next_partition_value=$(printf "%04d%02d" $((target_year + 1)) 1)
        fi
        
        info "Adding partition for $target_year-$(printf "%02d" $target_month)"
        
        # Add partition for call_records
        if [ -n "$DB_PASS" ]; then
            mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "
                ALTER TABLE call_records 
                ADD PARTITION (PARTITION p$partition_value VALUES LESS THAN ($next_partition_value));
            " "$DB_NAME" 2>/dev/null || info "Partition p$partition_value may already exist for call_records"
            
            # Add partition for balance_transactions
            mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "
                ALTER TABLE balance_transactions 
                ADD PARTITION (PARTITION p$partition_value VALUES LESS THAN ($next_partition_value));
            " "$DB_NAME" 2>/dev/null || info "Partition p$partition_value may already exist for balance_transactions"
        else
            mysql -h "$DB_HOST" -u "$DB_USER" -e "
                ALTER TABLE call_records 
                ADD PARTITION (PARTITION p$partition_value VALUES LESS THAN ($next_partition_value));
            " "$DB_NAME" 2>/dev/null || info "Partition p$partition_value may already exist for call_records"
            
            mysql -h "$DB_HOST" -u "$DB_USER" -e "
                ALTER TABLE balance_transactions 
                ADD PARTITION (PARTITION p$partition_value VALUES LESS THAN ($next_partition_value));
            " "$DB_NAME" 2>/dev/null || info "Partition p$partition_value may already exist for balance_transactions"
        fi
    done
    
    log "Partition management completed"
}

# Generate maintenance report
generate_report() {
    log "Generating maintenance report..."
    
    local report_file="$BACKUP_DIR/maintenance_report_$(date +%Y%m%d_%H%M%S).txt"
    
    {
        echo "FreePBX VoIP Platform - Database Maintenance Report"
        echo "=================================================="
        echo "Date: $(date)"
        echo "Server: $(hostname)"
        echo "Database: $DB_NAME"
        echo ""
        
        echo "Database Size Information:"
        echo "-------------------------"
        if [ -n "$DB_PASS" ]; then
            mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "
                SELECT 
                    table_name as 'Table',
                    table_rows as 'Rows',
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
                FROM information_schema.tables 
                WHERE table_schema = '$DB_NAME'
                ORDER BY (data_length + index_length) DESC;
            " "$DB_NAME"
        else
            mysql -h "$DB_HOST" -u "$DB_USER" -e "
                SELECT 
                    table_name as 'Table',
                    table_rows as 'Rows',
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
                FROM information_schema.tables 
                WHERE table_schema = '$DB_NAME'
                ORDER BY (data_length + index_length) DESC;
            " "$DB_NAME"
        fi
        
        echo ""
        echo "Partition Information:"
        echo "---------------------"
        if [ -n "$DB_PASS" ]; then
            mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "
                SELECT 
                    table_name as 'Table',
                    partition_name as 'Partition',
                    table_rows as 'Rows',
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
                FROM information_schema.partitions 
                WHERE table_schema = '$DB_NAME' 
                AND partition_name IS NOT NULL
                ORDER BY table_name, partition_ordinal_position;
            " "$DB_NAME"
        else
            mysql -h "$DB_HOST" -u "$DB_USER" -e "
                SELECT 
                    table_name as 'Table',
                    partition_name as 'Partition',
                    table_rows as 'Rows',
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
                FROM information_schema.partitions 
                WHERE table_schema = '$DB_NAME' 
                AND partition_name IS NOT NULL
                ORDER BY table_name, partition_ordinal_position;
            " "$DB_NAME"
        fi
        
        echo ""
        echo "Maintenance Log:"
        echo "---------------"
        tail -50 "$LOG_FILE"
        
    } > "$report_file"
    
    log "Maintenance report generated: $report_file"
}

# Clean up old backups
cleanup_old_backups() {
    log "Cleaning up old backups..."
    
    # Keep backups for 30 days
    find "$BACKUP_DIR" -name "db_backup_*.sql.gz" -mtime +30 -delete 2>/dev/null || true
    find "$BACKUP_DIR" -name "maintenance_report_*.txt" -mtime +30 -delete 2>/dev/null || true
    
    local remaining_backups=$(find "$BACKUP_DIR" -name "db_backup_*.sql.gz" | wc -l)
    log "Backup cleanup completed. Remaining backups: $remaining_backups"
}

# Main maintenance function
main() {
    log "Starting database maintenance for FreePBX VoIP Platform"
    
    # Check prerequisites
    check_prerequisites
    
    # Create backup
    local backup_file=$(create_backup)
    
    # Run maintenance tasks
    run_laravel_maintenance
    check_and_repair_tables
    optimize_tables
    analyze_tables
    update_statistics
    cleanup_old_data
    manage_partitions
    
    # Generate report
    generate_report
    
    # Clean up old backups
    cleanup_old_backups
    
    log "Database maintenance completed successfully"
    log "Backup created: $backup_file"
    
    # Run final performance check
    cd "$PROJECT_PATH"
    "$PHP_PATH" artisan db:performance-monitor --alert
}

# Handle script arguments
case "${8:-maintenance}" in
    "maintenance")
        main
        ;;
    "backup-only")
        check_prerequisites
        create_backup
        ;;
    "optimize-only")
        check_prerequisites
        optimize_tables
        analyze_tables
        ;;
    "cleanup-only")
        check_prerequisites
        cleanup_old_data
        cleanup_old_backups
        ;;
    "report-only")
        check_prerequisites
        generate_report
        ;;
    *)
        echo "FreePBX VoIP Platform - Database Maintenance Script"
        echo "Usage: $0 <project_path> <php_path> <db_host> <db_name> <db_user> [db_pass] [backup_dir] [action]"
        echo ""
        echo "Actions:"
        echo "  maintenance  - Run full maintenance (default)"
        echo "  backup-only  - Create backup only"
        echo "  optimize-only - Run optimization only"
        echo "  cleanup-only - Run cleanup only"
        echo "  report-only  - Generate report only"
        echo ""
        echo "Example:"
        echo "  $0 /var/www/html/voip /usr/bin/php localhost freepbx_voip root password /backups maintenance"
        exit 1
        ;;
esac