<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = config('database.connections.' . config('database.default') . '.driver');
        
        // Only implement partitioning for MySQL/MariaDB
        if ($driver !== 'mysql') {
            return;
        }

        // Check if call_records table exists and has data
        if (!Schema::hasTable('call_records')) {
            return;
        }

        // Create partitioned call_records table
        $this->createPartitionedCallRecordsTable();
        
        // Create partitioned balance_transactions table for high volume
        $this->createPartitionedBalanceTransactionsTable();
        
        // Create partitioned audit_logs table
        $this->createPartitionedAuditLogsTable();
    }

    /**
     * Create partitioned call_records table
     */
    protected function createPartitionedCallRecordsTable(): void
    {
        // First, check if table is already partitioned
        $partitionInfo = DB::select("
            SELECT COUNT(*) as partition_count 
            FROM information_schema.partitions 
            WHERE table_schema = ? AND table_name = 'call_records' AND partition_name IS NOT NULL
        ", [config('database.connections.' . config('database.default') . '.database')]);

        if ($partitionInfo[0]->partition_count > 0) {
            // Table is already partitioned
            return;
        }

        // Create backup table name
        $backupTable = 'call_records_backup_' . date('Ymd_His');
        
        try {
            // Create backup of existing data
            DB::statement("CREATE TABLE {$backupTable} AS SELECT * FROM call_records");
            
            // Drop existing table
            Schema::drop('call_records');
            
            // Create new partitioned table
            DB::statement("
                CREATE TABLE call_records (
                    id bigint unsigned NOT NULL AUTO_INCREMENT,
                    user_id bigint unsigned NOT NULL,
                    caller_id varchar(255) NOT NULL,
                    destination varchar(255) NOT NULL,
                    start_time datetime NOT NULL,
                    end_time datetime DEFAULT NULL,
                    duration int DEFAULT 0,
                    cost decimal(10,4) DEFAULT 0.0000,
                    status enum('initiated','connected','completed','failed','terminated') DEFAULT 'initiated',
                    call_id varchar(255) DEFAULT NULL,
                    freepbx_call_id varchar(255) DEFAULT NULL,
                    billing_increment int DEFAULT 1,
                    rate_per_minute decimal(8,4) DEFAULT 0.0000,
                    minimum_duration int DEFAULT 0,
                    processed_at timestamp NULL DEFAULT NULL,
                    created_at timestamp NULL DEFAULT NULL,
                    updated_at timestamp NULL DEFAULT NULL,
                    PRIMARY KEY (id, start_time),
                    KEY idx_call_records_user_id (user_id),
                    KEY idx_call_records_billing (user_id, start_time, status),
                    KEY idx_call_records_destination (destination, start_time),
                    KEY idx_call_records_duration (duration, cost),
                    KEY idx_call_records_realtime (status, start_time, user_id),
                    KEY idx_call_records_cdr_processing (processed_at, status),
                    CONSTRAINT call_records_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                PARTITION BY RANGE (YEAR(start_time) * 100 + MONTH(start_time)) (
                    PARTITION p202311 VALUES LESS THAN (202312),
                    PARTITION p202312 VALUES LESS THAN (202401),
                    PARTITION p202401 VALUES LESS THAN (202402),
                    PARTITION p202402 VALUES LESS THAN (202403),
                    PARTITION p202403 VALUES LESS THAN (202404),
                    PARTITION p202404 VALUES LESS THAN (202405),
                    PARTITION p202405 VALUES LESS THAN (202406),
                    PARTITION p202406 VALUES LESS THAN (202407),
                    PARTITION p202407 VALUES LESS THAN (202408),
                    PARTITION p202408 VALUES LESS THAN (202409),
                    PARTITION p202409 VALUES LESS THAN (202410),
                    PARTITION p202410 VALUES LESS THAN (202411),
                    PARTITION p202411 VALUES LESS THAN (202412),
                    PARTITION p202412 VALUES LESS THAN (202501),
                    PARTITION p202501 VALUES LESS THAN (202502),
                    PARTITION p202502 VALUES LESS THAN (202503),
                    PARTITION p202503 VALUES LESS THAN (202504),
                    PARTITION p202504 VALUES LESS THAN (202505),
                    PARTITION p202505 VALUES LESS THAN (202506),
                    PARTITION p202506 VALUES LESS THAN (202507),
                    PARTITION p202507 VALUES LESS THAN (202508),
                    PARTITION p202508 VALUES LESS THAN (202509),
                    PARTITION p202509 VALUES LESS THAN (202510),
                    PARTITION p202510 VALUES LESS THAN (202511),
                    PARTITION p202511 VALUES LESS THAN (202512),
                    PARTITION p202512 VALUES LESS THAN (202601),
                    PARTITION p_future VALUES LESS THAN MAXVALUE
                )
            ");
            
            // Restore data from backup
            DB::statement("INSERT INTO call_records SELECT * FROM {$backupTable}");
            
            // Drop backup table
            DB::statement("DROP TABLE {$backupTable}");
            
        } catch (\Exception $e) {
            // If partitioning fails, restore from backup
            if (DB::select("SHOW TABLES LIKE '{$backupTable}'")) {
                Schema::drop('call_records');
                DB::statement("CREATE TABLE call_records AS SELECT * FROM {$backupTable}");
                DB::statement("DROP TABLE {$backupTable}");
            }
            throw $e;
        }
    }

    /**
     * Create partitioned balance_transactions table
     */
    protected function createPartitionedBalanceTransactionsTable(): void
    {
        // Check if table exists and is not already partitioned
        if (!Schema::hasTable('balance_transactions')) {
            return;
        }

        $partitionInfo = DB::select("
            SELECT COUNT(*) as partition_count 
            FROM information_schema.partitions 
            WHERE table_schema = ? AND table_name = 'balance_transactions' AND partition_name IS NOT NULL
        ", [config('database.connections.' . config('database.default') . '.database')]);

        if ($partitionInfo[0]->partition_count > 0) {
            return;
        }

        $backupTable = 'balance_transactions_backup_' . date('Ymd_His');
        
        try {
            DB::statement("CREATE TABLE {$backupTable} AS SELECT * FROM balance_transactions");
            Schema::drop('balance_transactions');
            
            DB::statement("
                CREATE TABLE balance_transactions (
                    id bigint unsigned NOT NULL AUTO_INCREMENT,
                    user_id bigint unsigned NOT NULL,
                    amount decimal(10,4) NOT NULL,
                    type enum('credit','debit','adjustment','refund') NOT NULL,
                    description text,
                    reference_id varchar(255) DEFAULT NULL,
                    reference_type varchar(255) DEFAULT NULL,
                    metadata json DEFAULT NULL,
                    processed_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    balance_before decimal(10,4) DEFAULT 0.0000,
                    balance_after decimal(10,4) DEFAULT 0.0000,
                    created_at timestamp NULL DEFAULT NULL,
                    updated_at timestamp NULL DEFAULT NULL,
                    PRIMARY KEY (id, processed_at),
                    KEY idx_balance_transactions_user_history (user_id, processed_at, type),
                    KEY idx_balance_transactions_type (type, processed_at),
                    KEY idx_balance_transactions_reference (reference_type, reference_id),
                    CONSTRAINT balance_transactions_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                PARTITION BY RANGE (YEAR(processed_at) * 100 + MONTH(processed_at)) (
                    PARTITION p202311 VALUES LESS THAN (202312),
                    PARTITION p202312 VALUES LESS THAN (202401),
                    PARTITION p202401 VALUES LESS THAN (202402),
                    PARTITION p202402 VALUES LESS THAN (202403),
                    PARTITION p202403 VALUES LESS THAN (202404),
                    PARTITION p202404 VALUES LESS THAN (202405),
                    PARTITION p202405 VALUES LESS THAN (202406),
                    PARTITION p202406 VALUES LESS THAN (202407),
                    PARTITION p202407 VALUES LESS THAN (202408),
                    PARTITION p202408 VALUES LESS THAN (202409),
                    PARTITION p202409 VALUES LESS THAN (202410),
                    PARTITION p202410 VALUES LESS THAN (202411),
                    PARTITION p202411 VALUES LESS THAN (202412),
                    PARTITION p202412 VALUES LESS THAN (202501),
                    PARTITION p202501 VALUES LESS THAN (202502),
                    PARTITION p202502 VALUES LESS THAN (202503),
                    PARTITION p202503 VALUES LESS THAN (202504),
                    PARTITION p202504 VALUES LESS THAN (202505),
                    PARTITION p202505 VALUES LESS THAN (202506),
                    PARTITION p202506 VALUES LESS THAN (202507),
                    PARTITION p202507 VALUES LESS THAN (202508),
                    PARTITION p202508 VALUES LESS THAN (202509),
                    PARTITION p202509 VALUES LESS THAN (202510),
                    PARTITION p202510 VALUES LESS THAN (202511),
                    PARTITION p202511 VALUES LESS THAN (202512),
                    PARTITION p202512 VALUES LESS THAN (202601),
                    PARTITION p_future VALUES LESS THAN MAXVALUE
                )
            ");
            
            DB::statement("INSERT INTO balance_transactions SELECT * FROM {$backupTable}");
            DB::statement("DROP TABLE {$backupTable}");
            
        } catch (\Exception $e) {
            if (DB::select("SHOW TABLES LIKE '{$backupTable}'")) {
                Schema::drop('balance_transactions');
                DB::statement("CREATE TABLE balance_transactions AS SELECT * FROM {$backupTable}");
                DB::statement("DROP TABLE {$backupTable}");
            }
            throw $e;
        }
    }

    /**
     * Create partitioned audit_logs table
     */
    protected function createPartitionedAuditLogsTable(): void
    {
        if (!Schema::hasTable('audit_logs')) {
            return;
        }

        $partitionInfo = DB::select("
            SELECT COUNT(*) as partition_count 
            FROM information_schema.partitions 
            WHERE table_schema = ? AND table_name = 'audit_logs' AND partition_name IS NOT NULL
        ", [config('database.connections.' . config('database.default') . '.database')]);

        if ($partitionInfo[0]->partition_count > 0) {
            return;
        }

        $backupTable = 'audit_logs_backup_' . date('Ymd_His');
        
        try {
            DB::statement("CREATE TABLE {$backupTable} AS SELECT * FROM audit_logs");
            Schema::drop('audit_logs');
            
            DB::statement("
                CREATE TABLE audit_logs (
                    id bigint unsigned NOT NULL AUTO_INCREMENT,
                    user_id bigint unsigned DEFAULT NULL,
                    action varchar(255) NOT NULL,
                    model_type varchar(255) DEFAULT NULL,
                    model_id bigint unsigned DEFAULT NULL,
                    changes json DEFAULT NULL,
                    ip_address varchar(45) DEFAULT NULL,
                    user_agent text,
                    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at timestamp NULL DEFAULT NULL,
                    PRIMARY KEY (id, created_at),
                    KEY idx_audit_logs_user_id (user_id),
                    KEY idx_audit_logs_action (action, created_at),
                    KEY idx_audit_logs_model (model_type, model_id),
                    CONSTRAINT audit_logs_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                PARTITION BY RANGE (YEAR(created_at) * 100 + MONTH(created_at)) (
                    PARTITION p202311 VALUES LESS THAN (202312),
                    PARTITION p202312 VALUES LESS THAN (202401),
                    PARTITION p202401 VALUES LESS THAN (202402),
                    PARTITION p202402 VALUES LESS THAN (202403),
                    PARTITION p202403 VALUES LESS THAN (202404),
                    PARTITION p202404 VALUES LESS THAN (202405),
                    PARTITION p202405 VALUES LESS THAN (202406),
                    PARTITION p202406 VALUES LESS THAN (202407),
                    PARTITION p202407 VALUES LESS THAN (202408),
                    PARTITION p202408 VALUES LESS THAN (202409),
                    PARTITION p202409 VALUES LESS THAN (202410),
                    PARTITION p202410 VALUES LESS THAN (202411),
                    PARTITION p202411 VALUES LESS THAN (202412),
                    PARTITION p202412 VALUES LESS THAN (202501),
                    PARTITION p202501 VALUES LESS THAN (202502),
                    PARTITION p202502 VALUES LESS THAN (202503),
                    PARTITION p202503 VALUES LESS THAN (202504),
                    PARTITION p202504 VALUES LESS THAN (202505),
                    PARTITION p202505 VALUES LESS THAN (202506),
                    PARTITION p202506 VALUES LESS THAN (202507),
                    PARTITION p202507 VALUES LESS THAN (202508),
                    PARTITION p202508 VALUES LESS THAN (202509),
                    PARTITION p202509 VALUES LESS THAN (202510),
                    PARTITION p202510 VALUES LESS THAN (202511),
                    PARTITION p202511 VALUES LESS THAN (202512),
                    PARTITION p202512 VALUES LESS THAN (202601),
                    PARTITION p_future VALUES LESS THAN MAXVALUE
                )
            ");
            
            DB::statement("INSERT INTO audit_logs SELECT * FROM {$backupTable}");
            DB::statement("DROP TABLE {$backupTable}");
            
        } catch (\Exception $e) {
            if (DB::select("SHOW TABLES LIKE '{$backupTable}'")) {
                Schema::drop('audit_logs');
                DB::statement("CREATE TABLE audit_logs AS SELECT * FROM {$backupTable}");
                DB::statement("DROP TABLE {$backupTable}");
            }
            throw $e;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = config('database.connections.' . config('database.default') . '.driver');
        
        if ($driver !== 'mysql') {
            return;
        }

        // Remove partitioning from tables (convert back to regular tables)
        $this->removePartitioning('call_records');
        $this->removePartitioning('balance_transactions');
        $this->removePartitioning('audit_logs');
    }

    /**
     * Remove partitioning from a table
     */
    protected function removePartitioning(string $tableName): void
    {
        if (!Schema::hasTable($tableName)) {
            return;
        }

        try {
            // Check if table is partitioned
            $partitionInfo = DB::select("
                SELECT COUNT(*) as partition_count 
                FROM information_schema.partitions 
                WHERE table_schema = ? AND table_name = ? AND partition_name IS NOT NULL
            ", [config('database.connections.' . config('database.default') . '.database'), $tableName]);

            if ($partitionInfo[0]->partition_count > 0) {
                // Remove partitioning
                DB::statement("ALTER TABLE {$tableName} REMOVE PARTITIONING");
            }
        } catch (\Exception $e) {
            // If removal fails, log the error but don't throw
            \Log::warning("Failed to remove partitioning from {$tableName}: " . $e->getMessage());
        }
    }
};