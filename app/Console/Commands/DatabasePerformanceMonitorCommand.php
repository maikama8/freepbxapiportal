<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DatabasePerformanceMonitorCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'db:performance-monitor 
                            {--alert : Send alerts for performance issues}
                            {--optimize : Run optimization recommendations}
                            {--report : Generate performance report}
                            {--email= : Email address for alerts}';

    /**
     * The console command description.
     */
    protected $description = 'Monitor database performance and provide optimization recommendations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting database performance monitoring...');

        try {
            $performanceData = $this->gatherPerformanceMetrics();
            
            if ($this->option('report')) {
                $this->generatePerformanceReport($performanceData);
            }
            
            $issues = $this->analyzePerformanceIssues($performanceData);
            
            if (!empty($issues)) {
                $this->displayIssues($issues);
                
                if ($this->option('alert')) {
                    $this->sendAlerts($issues);
                }
                
                if ($this->option('optimize')) {
                    $this->runOptimizations($issues);
                }
            } else {
                $this->info('No performance issues detected.');
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Database performance monitoring failed: ' . $e->getMessage());
            Log::error('Database performance monitoring error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Gather performance metrics from the database
     */
    protected function gatherPerformanceMetrics(): array
    {
        $driver = config('database.default');
        $connection = config("database.connections.{$driver}.driver");
        
        if ($connection !== 'mysql') {
            $this->warn('Performance monitoring is optimized for MySQL/MariaDB');
            return [];
        }

        $metrics = [];

        // Table sizes and row counts
        $metrics['table_stats'] = $this->getTableStatistics();
        
        // Index usage statistics
        $metrics['index_stats'] = $this->getIndexStatistics();
        
        // Query performance
        $metrics['slow_queries'] = $this->getSlowQueryStats();
        
        // Connection and thread statistics
        $metrics['connection_stats'] = $this->getConnectionStats();
        
        // InnoDB statistics
        $metrics['innodb_stats'] = $this->getInnoDBStats();
        
        // Partition information
        $metrics['partition_stats'] = $this->getPartitionStats();
        
        return $metrics;
    }

    /**
     * Get table statistics
     */
    protected function getTableStatistics(): array
    {
        $database = config('database.connections.' . config('database.default') . '.database');
        
        return DB::select("
            SELECT 
                table_name,
                table_rows,
                ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
                ROUND((data_length / 1024 / 1024), 2) AS data_mb,
                ROUND((index_length / 1024 / 1024), 2) AS index_mb,
                engine,
                table_collation
            FROM information_schema.tables 
            WHERE table_schema = ?
            ORDER BY (data_length + index_length) DESC
        ", [$database]);
    }

    /**
     * Get index usage statistics
     */
    protected function getIndexStatistics(): array
    {
        $database = config('database.connections.' . config('database.default') . '.database');
        
        return DB::select("
            SELECT 
                s.table_name,
                s.index_name,
                s.column_name,
                s.cardinality,
                CASE 
                    WHEN s.index_name = 'PRIMARY' THEN 'Primary Key'
                    WHEN s.non_unique = 0 THEN 'Unique Index'
                    ELSE 'Regular Index'
                END as index_type
            FROM information_schema.statistics s
            WHERE s.table_schema = ?
            ORDER BY s.table_name, s.index_name, s.seq_in_index
        ", [$database]);
    }

    /**
     * Get slow query statistics
     */
    protected function getSlowQueryStats(): array
    {
        try {
            $slowLogEnabled = DB::select("SHOW VARIABLES LIKE 'slow_query_log'");
            $longQueryTime = DB::select("SHOW VARIABLES LIKE 'long_query_time'");
            
            return [
                'slow_log_enabled' => $slowLogEnabled[0]->Value ?? 'OFF',
                'long_query_time' => $longQueryTime[0]->Value ?? '10',
                'slow_queries' => DB::select("SHOW GLOBAL STATUS LIKE 'Slow_queries'")[0]->Value ?? 0
            ];
        } catch (\Exception $e) {
            return ['error' => 'Unable to retrieve slow query stats'];
        }
    }

    /**
     * Get connection statistics
     */
    protected function getConnectionStats(): array
    {
        try {
            $stats = [];
            $variables = [
                'Connections',
                'Max_used_connections',
                'Threads_connected',
                'Threads_running',
                'Aborted_connects',
                'Aborted_clients'
            ];
            
            foreach ($variables as $variable) {
                $result = DB::select("SHOW GLOBAL STATUS LIKE ?", [$variable]);
                $stats[$variable] = $result[0]->Value ?? 0;
            }
            
            $maxConnections = DB::select("SHOW VARIABLES LIKE 'max_connections'");
            $stats['max_connections'] = $maxConnections[0]->Value ?? 0;
            
            return $stats;
        } catch (\Exception $e) {
            return ['error' => 'Unable to retrieve connection stats'];
        }
    }

    /**
     * Get InnoDB statistics
     */
    protected function getInnoDBStats(): array
    {
        try {
            $stats = [];
            $variables = [
                'Innodb_buffer_pool_size',
                'Innodb_buffer_pool_pages_total',
                'Innodb_buffer_pool_pages_free',
                'Innodb_buffer_pool_pages_data',
                'Innodb_buffer_pool_read_requests',
                'Innodb_buffer_pool_reads'
            ];
            
            foreach ($variables as $variable) {
                $result = DB::select("SHOW GLOBAL STATUS LIKE ?", [$variable]);
                if (!empty($result)) {
                    $stats[$variable] = $result[0]->Value;
                }
            }
            
            // Calculate buffer pool hit ratio
            if (isset($stats['Innodb_buffer_pool_read_requests']) && isset($stats['Innodb_buffer_pool_reads'])) {
                $requests = (int)$stats['Innodb_buffer_pool_read_requests'];
                $reads = (int)$stats['Innodb_buffer_pool_reads'];
                
                if ($requests > 0) {
                    $stats['buffer_pool_hit_ratio'] = round((($requests - $reads) / $requests) * 100, 2);
                }
            }
            
            return $stats;
        } catch (\Exception $e) {
            return ['error' => 'Unable to retrieve InnoDB stats'];
        }
    }

    /**
     * Get partition statistics
     */
    protected function getPartitionStats(): array
    {
        $database = config('database.connections.' . config('database.default') . '.database');
        
        try {
            return DB::select("
                SELECT 
                    table_name,
                    partition_name,
                    partition_method,
                    partition_expression,
                    table_rows,
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                FROM information_schema.partitions 
                WHERE table_schema = ? 
                AND partition_name IS NOT NULL
                ORDER BY table_name, partition_ordinal_position
            ", [$database]);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Analyze performance issues
     */
    protected function analyzePerformanceIssues(array $metrics): array
    {
        $issues = [];

        // Check table sizes
        if (isset($metrics['table_stats'])) {
            foreach ($metrics['table_stats'] as $table) {
                if ($table->size_mb > 1000) { // Tables larger than 1GB
                    $issues[] = [
                        'type' => 'large_table',
                        'severity' => 'warning',
                        'table' => $table->table_name,
                        'message' => "Table {$table->table_name} is {$table->size_mb}MB. Consider partitioning or archiving.",
                        'recommendation' => 'Consider implementing table partitioning or data archiving'
                    ];
                }
                
                if ($table->table_rows > 1000000) { // Tables with more than 1M rows
                    $issues[] = [
                        'type' => 'high_row_count',
                        'severity' => 'info',
                        'table' => $table->table_name,
                        'message' => "Table {$table->table_name} has {$table->table_rows} rows.",
                        'recommendation' => 'Monitor query performance and consider indexing optimization'
                    ];
                }
            }
        }

        // Check InnoDB buffer pool hit ratio
        if (isset($metrics['innodb_stats']['buffer_pool_hit_ratio'])) {
            $hitRatio = $metrics['innodb_stats']['buffer_pool_hit_ratio'];
            if ($hitRatio < 95) {
                $issues[] = [
                    'type' => 'low_buffer_pool_hit_ratio',
                    'severity' => 'warning',
                    'message' => "InnoDB buffer pool hit ratio is {$hitRatio}% (should be > 95%)",
                    'recommendation' => 'Consider increasing innodb_buffer_pool_size'
                ];
            }
        }

        // Check connection usage
        if (isset($metrics['connection_stats'])) {
            $stats = $metrics['connection_stats'];
            if (isset($stats['max_connections']) && isset($stats['Max_used_connections'])) {
                $usage = ($stats['Max_used_connections'] / $stats['max_connections']) * 100;
                if ($usage > 80) {
                    $issues[] = [
                        'type' => 'high_connection_usage',
                        'severity' => 'warning',
                        'message' => "Connection usage is {$usage}% of maximum",
                        'recommendation' => 'Consider increasing max_connections or optimizing connection pooling'
                    ];
                }
            }
        }

        // Check for missing indexes on large tables
        $this->checkMissingIndexes($metrics, $issues);

        return $issues;
    }

    /**
     * Check for potentially missing indexes
     */
    protected function checkMissingIndexes(array $metrics, array &$issues): void
    {
        // Check call_records table for proper indexing
        if (isset($metrics['table_stats'])) {
            foreach ($metrics['table_stats'] as $table) {
                if ($table->table_name === 'call_records' && $table->table_rows > 10000) {
                    // Check if proper indexes exist
                    $indexes = collect($metrics['index_stats'])->where('table_name', 'call_records');
                    
                    $hasUserIndex = $indexes->contains('column_name', 'user_id');
                    $hasDateIndex = $indexes->contains('column_name', 'start_time');
                    
                    if (!$hasUserIndex) {
                        $issues[] = [
                            'type' => 'missing_index',
                            'severity' => 'warning',
                            'table' => 'call_records',
                            'message' => 'Missing index on user_id column in call_records table',
                            'recommendation' => 'Add index on user_id for better query performance'
                        ];
                    }
                    
                    if (!$hasDateIndex) {
                        $issues[] = [
                            'type' => 'missing_index',
                            'severity' => 'warning',
                            'table' => 'call_records',
                            'message' => 'Missing index on start_time column in call_records table',
                            'recommendation' => 'Add index on start_time for better date range queries'
                        ];
                    }
                }
            }
        }
    }

    /**
     * Display performance issues
     */
    protected function displayIssues(array $issues): void
    {
        $this->warn('Performance issues detected:');
        $this->newLine();

        foreach ($issues as $issue) {
            $severity = strtoupper($issue['severity']);
            $color = match($issue['severity']) {
                'critical' => 'error',
                'warning' => 'comment',
                'info' => 'info',
                default => 'line'
            };
            
            $this->$color("[{$severity}] {$issue['message']}");
            if (isset($issue['recommendation'])) {
                $this->line("  → Recommendation: {$issue['recommendation']}");
            }
            $this->newLine();
        }
    }

    /**
     * Send performance alerts
     */
    protected function sendAlerts(array $issues): void
    {
        $criticalIssues = array_filter($issues, fn($issue) => $issue['severity'] === 'critical');
        $warningIssues = array_filter($issues, fn($issue) => $issue['severity'] === 'warning');
        
        if (!empty($criticalIssues) || !empty($warningIssues)) {
            Log::channel('alerts')->warning('Database performance issues detected', [
                'critical_issues' => count($criticalIssues),
                'warning_issues' => count($warningIssues),
                'issues' => $issues
            ]);
            
            $this->info('Performance alerts logged to alerts channel');
            
            // Send email if configured
            $email = $this->option('email');
            if ($email) {
                $this->sendEmailAlert($email, $issues);
            }
        }
    }

    /**
     * Send email alert
     */
    protected function sendEmailAlert(string $email, array $issues): void
    {
        // This would integrate with your email system
        $this->info("Email alert would be sent to: {$email}");
        
        // Log the alert for now
        Log::info('Database performance email alert', [
            'recipient' => $email,
            'issues_count' => count($issues),
            'timestamp' => Carbon::now()
        ]);
    }

    /**
     * Run automatic optimizations
     */
    protected function runOptimizations(array $issues): void
    {
        $this->info('Running automatic optimizations...');
        
        foreach ($issues as $issue) {
            switch ($issue['type']) {
                case 'large_table':
                    $this->optimizeLargeTable($issue['table']);
                    break;
                    
                case 'missing_index':
                    $this->suggestIndexCreation($issue);
                    break;
            }
        }
    }

    /**
     * Optimize large table
     */
    protected function optimizeLargeTable(string $tableName): void
    {
        try {
            $this->info("Optimizing table: {$tableName}");
            DB::statement("OPTIMIZE TABLE {$tableName}");
            $this->info("Table {$tableName} optimized successfully");
        } catch (\Exception $e) {
            $this->error("Failed to optimize table {$tableName}: " . $e->getMessage());
        }
    }

    /**
     * Suggest index creation
     */
    protected function suggestIndexCreation(array $issue): void
    {
        $this->comment("Index suggestion for {$issue['table']}: {$issue['recommendation']}");
        
        // Log the suggestion for manual review
        Log::info('Database index suggestion', [
            'table' => $issue['table'],
            'recommendation' => $issue['recommendation'],
            'timestamp' => Carbon::now()
        ]);
    }

    /**
     * Generate performance report
     */
    protected function generatePerformanceReport(array $metrics): void
    {
        $reportPath = storage_path('app/reports/database-performance-' . date('Y-m-d-H-i-s') . '.json');
        
        $report = [
            'timestamp' => Carbon::now()->toISOString(),
            'server' => gethostname(),
            'database' => config('database.connections.' . config('database.default') . '.database'),
            'metrics' => $metrics,
            'summary' => $this->generateSummary($metrics)
        ];
        
        if (!is_dir(dirname($reportPath))) {
            mkdir(dirname($reportPath), 0755, true);
        }
        
        file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT));
        
        $this->info("Performance report generated: {$reportPath}");
    }

    /**
     * Generate performance summary
     */
    protected function generateSummary(array $metrics): array
    {
        $summary = [
            'total_tables' => 0,
            'total_size_mb' => 0,
            'largest_table' => null,
            'total_rows' => 0
        ];
        
        if (isset($metrics['table_stats'])) {
            $summary['total_tables'] = count($metrics['table_stats']);
            
            foreach ($metrics['table_stats'] as $table) {
                $summary['total_size_mb'] += $table->size_mb;
                $summary['total_rows'] += $table->table_rows;
                
                if (!$summary['largest_table'] || $table->size_mb > $summary['largest_table']['size_mb']) {
                    $summary['largest_table'] = [
                        'name' => $table->table_name,
                        'size_mb' => $table->size_mb,
                        'rows' => $table->table_rows
                    ];
                }
            }
        }
        
        return $summary;
    }
}