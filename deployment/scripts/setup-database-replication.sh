#!/bin/bash

# Database Replication Setup Script for VoIP Platform
set -e

echo "Setting up MySQL/MariaDB Master-Slave Replication..."

# Configuration variables
MASTER_HOST="${MASTER_HOST:-127.0.0.1}"
SLAVE_HOST="${SLAVE_HOST:-127.0.0.2}"
REPLICATION_USER="${REPLICATION_USER:-replication_user}"
REPLICATION_PASSWORD="${REPLICATION_PASSWORD:-$(openssl rand -base64 32)}"
DATABASE_NAME="${DATABASE_NAME:-voip_platform_prod}"
MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD}"

# Function to execute MySQL commands
execute_mysql() {
    local host=$1
    local command=$2
    mysql -h "$host" -u root -p"$MYSQL_ROOT_PASSWORD" -e "$command"
}

# Function to setup master server
setup_master() {
    echo "Configuring master server at $MASTER_HOST..."
    
    # Create master configuration
    cat > /tmp/master.cnf << EOF
[mysqld]
# Replication Configuration
server-id = 1
log-bin = mysql-bin
binlog-format = ROW
binlog-do-db = $DATABASE_NAME

# Performance Optimizations
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT
query_cache_size = 128M
query_cache_type = 1

# Connection Settings
max_connections = 200
max_connect_errors = 1000000
wait_timeout = 28800
interactive_timeout = 28800

# Logging
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2
log_queries_not_using_indexes = 1

# Binary Log Settings
expire_logs_days = 7
max_binlog_size = 100M
sync_binlog = 1

# InnoDB Settings
innodb_file_per_table = 1
innodb_open_files = 400
innodb_io_capacity = 400
innodb_read_io_threads = 8
innodb_write_io_threads = 8
EOF

    # Copy configuration to master server
    if [ "$MASTER_HOST" != "127.0.0.1" ]; then
        scp /tmp/master.cnf root@$MASTER_HOST:/etc/mysql/conf.d/replication.cnf
        ssh root@$MASTER_HOST "systemctl restart mysql"
    else
        sudo cp /tmp/master.cnf /etc/mysql/conf.d/replication.cnf
        sudo systemctl restart mysql
    fi

    # Wait for MySQL to restart
    sleep 10

    # Create replication user
    execute_mysql "$MASTER_HOST" "
        CREATE USER IF NOT EXISTS '$REPLICATION_USER'@'%' IDENTIFIED BY '$REPLICATION_PASSWORD';
        GRANT REPLICATION SLAVE ON *.* TO '$REPLICATION_USER'@'%';
        FLUSH PRIVILEGES;
    "

    # Get master status
    MASTER_STATUS=$(execute_mysql "$MASTER_HOST" "SHOW MASTER STATUS\G")
    MASTER_LOG_FILE=$(echo "$MASTER_STATUS" | grep "File:" | awk '{print $2}')
    MASTER_LOG_POS=$(echo "$MASTER_STATUS" | grep "Position:" | awk '{print $2}')

    echo "Master configured successfully!"
    echo "Master Log File: $MASTER_LOG_FILE"
    echo "Master Log Position: $MASTER_LOG_POS"
    
    # Save master status for slave configuration
    echo "MASTER_LOG_FILE=$MASTER_LOG_FILE" > /tmp/master_status.env
    echo "MASTER_LOG_POS=$MASTER_LOG_POS" >> /tmp/master_status.env
}

# Function to setup slave server
setup_slave() {
    echo "Configuring slave server at $SLAVE_HOST..."
    
    # Load master status
    source /tmp/master_status.env
    
    # Create slave configuration
    cat > /tmp/slave.cnf << EOF
[mysqld]
# Replication Configuration
server-id = 2
relay-log = relay-bin
log-slave-updates = 1
read-only = 1

# Performance Optimizations
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT
query_cache_size = 128M
query_cache_type = 1

# Connection Settings
max_connections = 200
max_connect_errors = 1000000
wait_timeout = 28800
interactive_timeout = 28800

# Slave-specific Settings
slave_skip_errors = 1062,1053,1146
slave_net_timeout = 60
slave_compressed_protocol = 1

# InnoDB Settings
innodb_file_per_table = 1
innodb_open_files = 400
innodb_io_capacity = 400
innodb_read_io_threads = 8
innodb_write_io_threads = 8
EOF

    # Copy configuration to slave server
    if [ "$SLAVE_HOST" != "127.0.0.2" ]; then
        scp /tmp/slave.cnf root@$SLAVE_HOST:/etc/mysql/conf.d/replication.cnf
        ssh root@$SLAVE_HOST "systemctl restart mysql"
    else
        sudo cp /tmp/slave.cnf /etc/mysql/conf.d/replication.cnf
        sudo systemctl restart mysql
    fi

    # Wait for MySQL to restart
    sleep 10

    # Configure slave replication
    execute_mysql "$SLAVE_HOST" "
        STOP SLAVE;
        CHANGE MASTER TO
            MASTER_HOST='$MASTER_HOST',
            MASTER_USER='$REPLICATION_USER',
            MASTER_PASSWORD='$REPLICATION_PASSWORD',
            MASTER_LOG_FILE='$MASTER_LOG_FILE',
            MASTER_LOG_POS=$MASTER_LOG_POS;
        START SLAVE;
    "

    # Check slave status
    echo "Checking slave status..."
    SLAVE_STATUS=$(execute_mysql "$SLAVE_HOST" "SHOW SLAVE STATUS\G")
    
    IO_RUNNING=$(echo "$SLAVE_STATUS" | grep "Slave_IO_Running:" | awk '{print $2}')
    SQL_RUNNING=$(echo "$SLAVE_STATUS" | grep "Slave_SQL_Running:" | awk '{print $2}')
    
    if [ "$IO_RUNNING" = "Yes" ] && [ "$SQL_RUNNING" = "Yes" ]; then
        echo "Slave configured successfully!"
        echo "Replication is running properly."
    else
        echo "Warning: Replication may not be working properly."
        echo "IO Thread Running: $IO_RUNNING"
        echo "SQL Thread Running: $SQL_RUNNING"
        
        # Show any errors
        LAST_IO_ERROR=$(echo "$SLAVE_STATUS" | grep "Last_IO_Error:" | cut -d: -f2-)
        LAST_SQL_ERROR=$(echo "$SLAVE_STATUS" | grep "Last_SQL_Error:" | cut -d: -f2-)
        
        if [ -n "$LAST_IO_ERROR" ]; then
            echo "Last IO Error: $LAST_IO_ERROR"
        fi
        
        if [ -n "$LAST_SQL_ERROR" ]; then
            echo "Last SQL Error: $LAST_SQL_ERROR"
        fi
    fi
}

# Function to create monitoring script
create_monitoring_script() {
    cat > /usr/local/bin/check-replication.sh << 'EOF'
#!/bin/bash

# MySQL Replication Monitoring Script

MASTER_HOST="${MASTER_HOST:-127.0.0.1}"
SLAVE_HOST="${SLAVE_HOST:-127.0.0.2}"
MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD}"

check_master() {
    echo "=== Master Status ==="
    mysql -h "$MASTER_HOST" -u root -p"$MYSQL_ROOT_PASSWORD" -e "SHOW MASTER STATUS\G"
    echo ""
}

check_slave() {
    echo "=== Slave Status ==="
    SLAVE_STATUS=$(mysql -h "$SLAVE_HOST" -u root -p"$MYSQL_ROOT_PASSWORD" -e "SHOW SLAVE STATUS\G")
    
    echo "$SLAVE_STATUS"
    echo ""
    
    # Check for issues
    IO_RUNNING=$(echo "$SLAVE_STATUS" | grep "Slave_IO_Running:" | awk '{print $2}')
    SQL_RUNNING=$(echo "$SLAVE_STATUS" | grep "Slave_SQL_Running:" | awk '{print $2}')
    SECONDS_BEHIND=$(echo "$SLAVE_STATUS" | grep "Seconds_Behind_Master:" | awk '{print $2}')
    
    echo "=== Replication Health ==="
    echo "IO Thread: $IO_RUNNING"
    echo "SQL Thread: $SQL_RUNNING"
    echo "Seconds Behind Master: $SECONDS_BEHIND"
    
    if [ "$IO_RUNNING" != "Yes" ] || [ "$SQL_RUNNING" != "Yes" ]; then
        echo "WARNING: Replication is not running properly!"
        exit 1
    fi
    
    if [ "$SECONDS_BEHIND" != "NULL" ] && [ "$SECONDS_BEHIND" -gt 60 ]; then
        echo "WARNING: Slave is more than 60 seconds behind master!"
        exit 1
    fi
    
    echo "Replication is healthy."
}

case "$1" in
    master)
        check_master
        ;;
    slave)
        check_slave
        ;;
    *)
        check_master
        check_slave
        ;;
esac
EOF

    chmod +x /usr/local/bin/check-replication.sh
    echo "Monitoring script created at /usr/local/bin/check-replication.sh"
}

# Function to setup automated failover
setup_failover() {
    cat > /usr/local/bin/failover-to-slave.sh << 'EOF'
#!/bin/bash

# MySQL Failover Script

MASTER_HOST="${MASTER_HOST:-127.0.0.1}"
SLAVE_HOST="${SLAVE_HOST:-127.0.0.2}"
MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD}"

echo "Initiating failover from master to slave..."

# Stop slave replication
mysql -h "$SLAVE_HOST" -u root -p"$MYSQL_ROOT_PASSWORD" -e "STOP SLAVE;"

# Make slave writable
mysql -h "$SLAVE_HOST" -u root -p"$MYSQL_ROOT_PASSWORD" -e "SET GLOBAL read_only = OFF;"

# Reset slave configuration
mysql -h "$SLAVE_HOST" -u root -p"$MYSQL_ROOT_PASSWORD" -e "RESET SLAVE ALL;"

echo "Failover completed. Slave is now the new master."
echo "Remember to update application configuration to point to the new master."
EOF

    chmod +x /usr/local/bin/failover-to-slave.sh
    echo "Failover script created at /usr/local/bin/failover-to-slave.sh"
}

# Main execution
main() {
    if [ -z "$MYSQL_ROOT_PASSWORD" ]; then
        echo "Error: MYSQL_ROOT_PASSWORD environment variable is required"
        exit 1
    fi

    echo "MySQL/MariaDB Replication Setup"
    echo "Master Host: $MASTER_HOST"
    echo "Slave Host: $SLAVE_HOST"
    echo "Database: $DATABASE_NAME"
    echo "Replication User: $REPLICATION_USER"
    echo ""

    # Setup master
    setup_master

    # Setup slave
    setup_slave

    # Create monitoring and failover scripts
    create_monitoring_script
    setup_failover

    # Setup cron job for monitoring
    echo "*/5 * * * * /usr/local/bin/check-replication.sh slave > /dev/null 2>&1 || logger 'MySQL replication check failed'" | crontab -

    echo ""
    echo "Replication setup completed successfully!"
    echo ""
    echo "Replication User: $REPLICATION_USER"
    echo "Replication Password: $REPLICATION_PASSWORD"
    echo ""
    echo "To monitor replication: /usr/local/bin/check-replication.sh"
    echo "To failover to slave: /usr/local/bin/failover-to-slave.sh"
    echo ""
    echo "Remember to:"
    echo "1. Update your Laravel .env file to use the slave for read operations"
    echo "2. Configure your load balancer to handle database failover"
    echo "3. Test the replication and failover procedures"
}

# Run main function
main "$@"