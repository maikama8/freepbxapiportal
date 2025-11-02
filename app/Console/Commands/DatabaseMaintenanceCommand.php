<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DatabaseMaintenanceCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'db:maintenance 
                            {--optimize : Optimize database tables}
                            {--cleanup : Clean up old records}
                            {--analyze : Analyze table statistics}
                            {--repair : Repair database tables}
                            {--retention-days=90 : Number of days to retain old records}';

    /**
     * The console command description.
     */
    protected $description = 'Perform database maintenance tasks including optimization and cleanup';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting database maintenance...');

        try {
            $tasks = [];
            
            if ($this->option('cleanup') || !$this->hasOptions()) {
                $tasks[] = 'cleanup';
            }
            
            if ($this->option('optimize') || !$this->hasOptions()) {
                $tasks[] = 'optimize';
            }
            
            if ($this->option('analyze')) {
                $tasks[] = 'analyze';
            }
            
            if ($this->option('repair')) {
                $tasks[] = 'repair';
            }

            foreach ($tasks as $task) {
                $this->info("Running {$task} task...");
                
                switch ($task) {
                    case 'cleanup':
                        $this->cleanupOldRecords();
                        break;
                    case 'optimize':
                        $this->optimizeTables();
                        break;
                    case 'analyze':
                        $this->analyzeTables();
                        break;
                    case 'repair':
                        $this->repairTables();
                        break;
                }
            }

            // Log maintenance completion
            Log::channel('monitoring')->info('Database maintenance completed', [
                'tasks' => $tasks,
                'timestamp' => now()->toISOString(),
            ]);

            $this->info('Database maintenance completed successfully.');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Database maintenance failed: {$e->getMessage()}");
            Log::error('Database maintenance failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return Command::FAILURE;
        }
    }

    /**
     * Check if any options are provided
     */
    protected function hasOptions(): bool
    {
        return $this->option('optimize') || 
               $this->option('cleanup') || 
               $this->option('analyze') || 
               $this->option('repair');
    }

    /**
     * Clean up old records from various tables
     */
    protected function cleanupOldRecords(): void
    {
        $retentionDays = (int) $this->option('retention-days');
        $cutoffDate = Carbon::now()->subDays($retentionDays);
        
        $this->info("Cleaning up records older than {$retentionDays} days...");

        // Clean up old audit logs (keep for 1 year)
        $auditCutoff = Carbon::now()->subDays(365);
        $deletedAuditLogs = DB::table('audit_logs')
            ->where('created_at', '<', $auditCutoff)
            ->delete();
        
        if ($deletedAuditLogs > 0) {
            $this->line("Deleted {$deletedAuditLogs} old audit log entries");
        }

        // Clean up old password histories (keep last 12 passwords per user)
        $this->cleanupPasswordHistories();

        // Clean up failed payment transactions older than retention period
        $deletedFailedPayments = DB::table('payment_transactions')
            ->where('status', 'failed')
            ->where('created_at', '<', $cutoffDate)
            ->delete();
        
        if ($deletedFailedPayments > 0) {
            $this->line("Deleted {$deletedFailedPayments} old failed payment records");
        }

        // Clean up old call records (keep based on retention policy)
        $callRetentionDays = config('voip.call_retention_days', 180);
        $callCutoff = Carbon::now()->subDays($callRetentionDays);
        $deletedCallRecords = DB::table('call_records')
            ->where('created_at', '<', $callCutoff)
            ->delete();
        
        if ($deletedCallRecords > 0) {
            $this->line("Deleted {$deletedCallRecords} old call records");
        }

        // Clean up old balance transactions (keep for 2 years)
        $balanceCutoff = Carbon::now()->subDays(730);
        $deletedBalanceTransactions = DB::table('balance_transactions')
            ->where('created_at', '<', $balanceCutoff)
            ->delete();
        
        if ($deletedBalanceTransactions > 0) {
            $this->line("Deleted {$deletedBalanceTransactions} old balance transaction records");
        }

        // Clean up Laravel sessions and cache
        $this->cleanupLaravelSessions();
        
        $this->info('Record cleanup completed.');
    }

    /**
     * Clean up password histories, keeping only the last 12 passwords per user
     */
    protected function cleanupPasswordHistories(): void
    {
        $users = DB::table('users')->pluck('id');
        $totalDeleted = 0;

        foreach ($users as $userId) {
            // Get password history IDs to keep (last 12)
            $keepIds = DB::table('password_histories')
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->limit(12)
                ->pluck('id');

            // Delete older password histories
            $deleted = DB::table('password_histories')
                ->where('user_id', $userId)
                ->whereNotIn('id', $keepIds)
                ->delete();

            $totalDeleted += $deleted;
        }

        if ($totalDeleted > 0) {
            $this->line("Deleted {$totalDeleted} old password history entries");
        }
    }

    /**
     * Clean up Laravel sessions and cache entries
     */
    protected function cleanupLaravelSessions(): void
    {
        // Clean up expired sessions
        $expiredSessions = DB::table('sessions')
            ->where('last_activity', '<', now()->subHours(24)->timestamp)
            ->delete();
        
        if ($expiredSessions > 0) {
            $this->line("Deleted {$expiredSessions} expired session records");
        }

        // Clean up old cache entries
        $expiredCache = DB::table('cache')
            ->where('expiration', '<', now()->timestamp)
            ->delete();
        
        if ($expiredCache > 0) {
            $this->line("Deleted {$expiredCache} expired cache entries");
        }
    }

    /**
     * Optimize database tables
     */
    protected function optimizeTables(): void
    {
        $this->info('Optimizing database tables...');
        $driver = config('database.connections.' . config('database.default') . '.driver');

        if ($driver === 'sqlite') {
            try {
                DB::statement("VACUUM");
                $this->info("SQLite database optimized with VACUUM.");
            } catch (\Exception $e) {
                $this->warn("Failed to optimize SQLite database: {$e->getMessage()}");
            }
            return;
        }

        $tables = $this->getDatabaseTables();
        $optimizedCount = 0;

        foreach ($tables as $table) {
            try {
                DB::statement("OPTIMIZE TABLE `{$table}`");
                $optimizedCount++;
                $this->line("Optimized table: {$table}");
            } catch (\Exception $e) {
                $this->warn("Failed to optimize table {$table}: {$e->getMessage()}");
            }
        }

        $this->info("Optimized {$optimizedCount} tables.");
    }

    /**
     * Analyze database tables to update statistics
     */
    protected function analyzeTables(): void
    {
        $this->info('Analyzing database tables...');
        $driver = config('database.connections.' . config('database.default') . '.driver');

        if ($driver === 'sqlite') {
            try {
                DB::statement("ANALYZE");
                $this->info("SQLite database analyzed.");
            } catch (\Exception $e) {
                $this->warn("Failed to analyze SQLite database: {$e->getMessage()}");
            }
            return;
        }

        $tables = $this->getDatabaseTables();
        $analyzedCount = 0;

        foreach ($tables as $table) {
            try {
                DB::statement("ANALYZE TABLE `{$table}`");
                $analyzedCount++;
                $this->line("Analyzed table: {$table}");
            } catch (\Exception $e) {
                $this->warn("Failed to analyze table {$table}: {$e->getMessage()}");
            }
        }

        $this->info("Analyzed {$analyzedCount} tables.");
    }

    /**
     * Repair database tables
     */
    protected function repairTables(): void
    {
        $this->info('Repairing database tables...');

        $tables = $this->getDatabaseTables();
        $repairedCount = 0;

        foreach ($tables as $table) {
            try {
                $result = DB::select("REPAIR TABLE `{$table}`");
                
                if (isset($result[0]) && $result[0]->Msg_text === 'OK') {
                    $repairedCount++;
                    $this->line("Repaired table: {$table}");
                } else {
                    $this->warn("Table {$table} repair result: " . ($result[0]->Msg_text ?? 'Unknown'));
                }
            } catch (\Exception $e) {
                $this->warn("Failed to repair table {$table}: {$e->getMessage()}");
            }
        }

        $this->info("Repaired {$repairedCount} tables.");
    }

    /**
     * Get list of database tables
     */
    protected function getDatabaseTables(): array
    {
        $driver = config('database.connections.' . config('database.default') . '.driver');
        
        if ($driver === 'sqlite') {
            $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            return array_map(function ($table) {
                return $table->name;
            }, $tables);
        } else {
            $databaseName = config('database.connections.' . config('database.default') . '.database');
            $tables = DB::select("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?", [$databaseName]);
            return array_map(function ($table) {
                return $table->TABLE_NAME;
            }, $tables);
        }
    }

    /**
     * Get database size information
     */
    protected function getDatabaseSizeInfo(): array
    {
        $databaseName = config('database.connections.' . config('database.default') . '.database');
        
        $result = DB::select("
            SELECT 
                table_name AS 'table',
                ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'size_mb',
                table_rows AS 'rows'
            FROM information_schema.TABLES 
            WHERE table_schema = ?
            ORDER BY (data_length + index_length) DESC
        ", [$databaseName]);

        return $result;
    }

    /**
     * Display database statistics
     */
    protected function displayDatabaseStats(): void
    {
        $this->info('Database Statistics:');
        
        $sizeInfo = $this->getDatabaseSizeInfo();
        $totalSize = 0;
        $totalRows = 0;

        $this->table(
            ['Table', 'Size (MB)', 'Rows'],
            array_map(function ($table) use (&$totalSize, &$totalRows) {
                $totalSize += $table->size_mb;
                $totalRows += $table->rows;
                return [
                    $table->table,
                    number_format($table->size_mb, 2),
                    number_format($table->rows)
                ];
            }, $sizeInfo)
        );

        $this->info("Total database size: " . number_format($totalSize, 2) . " MB");
        $this->info("Total rows: " . number_format($totalRows));
    }
}