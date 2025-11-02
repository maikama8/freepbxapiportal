<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class DatabasePerformanceService
{
    /**
     * Get database performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        return [
            'connection_stats' => $this->getConnectionStats(),
            'query_performance' => $this->getQueryPerformance(),
            'table_stats' => $this->getTableStats(),
            'index_usage' => $this->getIndexUsage(),
            'slow_queries' => $this->getSlowQueries(),
            'innodb_stats' => $this->getInnoDBStats(),
            'replication_status' => $this->getReplicationStatus(),
        ];
    }

    /**
     * Get database connection statistics
     */
    protected function getConnectionStats(): array
    {
        try {
            $stats = DB::select("SHOW STATUS WHERE Variable_name IN (
                'Threads_connected', 'Threads_running', 'Max_used_connections',
                'Connections', 'Aborted_connects', 'Aborted_clients'
            )");

            $result = [];
            foreach ($stats as $stat) {
                $result[strtolower($stat->Variable_name)] = $stat->Value;
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to get connection stats', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get query performance statistics
     */
    protected function getQueryPerformance(): array
    {
        try {
            $stats = DB::select("SHOW STATUS WHERE Variable_name IN (
                'Queries', 'Questions', 'Com_select', 'Com_insert', 
                'Com_update', 'Com_delete', 'Slow_queries'
            )");

            $result = [];
            foreach ($stats as $stat) {
                $result[strtolower($stat->Variable_name)] = $stat->Value;
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to get query performance stats', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get table statistics
     */
    protected function getTableStats(): array
    {
        try {
            $driver = config('database.connections.' . config('database.default') . '.driver');
            
            if ($driver === 'sqlite') {
                $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                
                return array_map(function ($table) {
                    return [
                        'name' => $table->name,
                        'rows' => 0, // SQLite doesn't provide easy row count
                        'size_mb' => 0, // Would need file system check
                        'data_mb' => 0,
                        'index_mb' => 0,
                        'engine' => 'sqlite',
                        'collation' => 'utf8',
                    ];
                }, $tables);
            }
            
            $databaseName = config('database.connections.' . config('database.default') . '.database');
            
            $stats = DB::select("
                SELECT 
                    table_name,
                    table_rows,
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
                    ROUND((data_length / 1024 / 1024), 2) AS data_mb,
                    ROUND((index_length / 1024 / 1024), 2) AS index_mb,
                    engine,
                    table_collation
                FROM information_schema.TABLES 
                WHERE table_schema = ?
                ORDER BY (data_length + index_length) DESC
                LIMIT 20
            ", [$databaseName]);

            return array_map(function ($table) {
                return [
                    'name' => $table->table_name,
                    'rows' => (int) $table->table_rows,
                    'size_mb' => (float) $table->size_mb,
                    'data_mb' => (float) $table->data_mb,
                    'index_mb' => (float) $table->index_mb,
                    'engine' => $table->engine,
                    'collation' => $table->table_collation,
                ];
            }, $stats);
        } catch (\Exception $e) {
            Log::error('Failed to get table stats', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get index usage statistics
     */
    protected function getIndexUsage(): array
    {
        try {
            $databaseName = config('database.connections.' . config('database.default') . '.database');
            
            $stats = DB::select("
                SELECT 
                    t.table_name,
                    s.index_name,
                    s.column_name,
                    s.cardinality,
                    s.nullable,
                    s.index_type
                FROM information_schema.STATISTICS s
                JOIN information_schema.TABLES t ON s.table_name = t.table_name
                WHERE s.table_schema = ? AND t.table_schema = ?
                AND s.index_name != 'PRIMARY'
                ORDER BY s.table_name, s.index_name, s.seq_in_index
            ", [$databaseName, $databaseName]);

            $indexUsage = [];
            foreach ($stats as $stat) {
                $key = $stat->table_name . '.' . $stat->index_name;
                if (!isset($indexUsage[$key])) {
                    $indexUsage[$key] = [
                        'table' => $stat->table_name,
                        'index' => $stat->index_name,
                        'columns' => [],
                        'type' => $stat->index_type,
                    ];
                }
                $indexUsage[$key]['columns'][] = [
                    'column' => $stat->column_name,
                    'cardinality' => (int) $stat->cardinality,
                    'nullable' => $stat->nullable === 'YES',
                ];
            }

            return array_values($indexUsage);
        } catch (\Exception $e) {
            Log::error('Failed to get index usage stats', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get slow query information
     */
    protected function getSlowQueries(): array
    {
        try {
            // Check if slow query log is enabled
            $slowLogEnabled = DB::select("SHOW VARIABLES LIKE 'slow_query_log'");
            if (empty($slowLogEnabled) || $slowLogEnabled[0]->Value !== 'ON') {
                return ['enabled' => false, 'queries' => []];
            }

            $slowQueryStats = DB::select("SHOW STATUS LIKE 'Slow_queries'");
            $slowQueryCount = $slowQueryStats[0]->Value ?? 0;

            return [
                'enabled' => true,
                'total_slow_queries' => (int) $slowQueryCount,
                'queries' => [], // Would need to parse slow query log file for details
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get slow query stats', ['error' => $e->getMessage()]);
            return ['enabled' => false, 'queries' => []];
        }
    }

    /**
     * Get InnoDB statistics
     */
    protected function getInnoDBStats(): array
    {
        try {
            $stats = DB::select("SHOW STATUS WHERE Variable_name LIKE 'Innodb_%'");
            
            $result = [];
            foreach ($stats as $stat) {
                $key = strtolower(str_replace('innodb_', '', $stat->Variable_name));
                $result[$key] = $stat->Value;
            }

            // Calculate some derived metrics
            if (isset($result['buffer_pool_reads']) && isset($result['buffer_pool_read_requests'])) {
                $hitRatio = 100 - (($result['buffer_pool_reads'] / $result['buffer_pool_read_requests']) * 100);
                $result['buffer_pool_hit_ratio'] = round($hitRatio, 2);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to get InnoDB stats', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get replication status
     */
    protected function getReplicationStatus(): array
    {
        try {
            // Check if this is a slave
            $slaveStatus = DB::select("SHOW SLAVE STATUS");
            
            if (empty($slaveStatus)) {
                // Check if this is a master
                $masterStatus = DB::select("SHOW MASTER STATUS");
                
                if (!empty($masterStatus)) {
                    return [
                        'role' => 'master',
                        'status' => 'active',
                        'log_file' => $masterStatus[0]->File,
                        'log_position' => $masterStatus[0]->Position,
                    ];
                }
                
                return ['role' => 'standalone', 'status' => 'none'];
            }

            $status = $slaveStatus[0];
            
            return [
                'role' => 'slave',
                'status' => ($status->Slave_IO_Running === 'Yes' && $status->Slave_SQL_Running === 'Yes') ? 'running' : 'stopped',
                'master_host' => $status->Master_Host,
                'master_port' => $status->Master_Port,
                'io_running' => $status->Slave_IO_Running === 'Yes',
                'sql_running' => $status->Slave_SQL_Running === 'Yes',
                'seconds_behind_master' => $status->Seconds_Behind_Master,
                'last_io_error' => $status->Last_IO_Error,
                'last_sql_error' => $status->Last_SQL_Error,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get replication status', ['error' => $e->getMessage()]);
            return ['role' => 'unknown', 'status' => 'error'];
        }
    }

    /**
     * Analyze query performance and suggest optimizations
     */
    public function analyzeQueryPerformance(): array
    {
        $suggestions = [];

        try {
            // Check for tables without primary keys
            $tablesWithoutPK = $this->getTablesWithoutPrimaryKey();
            if (!empty($tablesWithoutPK)) {
                $suggestions[] = [
                    'type' => 'missing_primary_key',
                    'severity' => 'high',
                    'message' => 'Tables without primary keys detected',
                    'tables' => $tablesWithoutPK,
                    'recommendation' => 'Add primary keys to improve replication and performance',
                ];
            }

            // Check for unused indexes
            $unusedIndexes = $this->getUnusedIndexes();
            if (!empty($unusedIndexes)) {
                $suggestions[] = [
                    'type' => 'unused_indexes',
                    'severity' => 'medium',
                    'message' => 'Unused indexes detected',
                    'indexes' => $unusedIndexes,
                    'recommendation' => 'Consider removing unused indexes to improve write performance',
                ];
            }

            // Check buffer pool hit ratio
            $innodbStats = $this->getInnoDBStats();
            if (isset($innodbStats['buffer_pool_hit_ratio']) && $innodbStats['buffer_pool_hit_ratio'] < 95) {
                $suggestions[] = [
                    'type' => 'low_buffer_pool_hit_ratio',
                    'severity' => 'medium',
                    'message' => 'Low InnoDB buffer pool hit ratio',
                    'current_ratio' => $innodbStats['buffer_pool_hit_ratio'],
                    'recommendation' => 'Consider increasing innodb_buffer_pool_size',
                ];
            }

            return $suggestions;
        } catch (\Exception $e) {
            Log::error('Failed to analyze query performance', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get tables without primary keys
     */
    protected function getTablesWithoutPrimaryKey(): array
    {
        try {
            $databaseName = config('database.connections.' . config('database.default') . '.database');
            
            $tables = DB::select("
                SELECT t.table_name
                FROM information_schema.TABLES t
                LEFT JOIN information_schema.KEY_COLUMN_USAGE k 
                    ON t.table_name = k.table_name 
                    AND t.table_schema = k.table_schema 
                    AND k.constraint_name = 'PRIMARY'
                WHERE t.table_schema = ? 
                    AND t.table_type = 'BASE TABLE'
                    AND k.table_name IS NULL
            ", [$databaseName]);

            return array_map(function ($table) {
                return $table->table_name;
            }, $tables);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get unused indexes (simplified check)
     */
    protected function getUnusedIndexes(): array
    {
        try {
            // This is a simplified check - in production, you'd want to use
            // performance_schema or pt-index-usage tools for accurate results
            $databaseName = config('database.connections.' . config('database.default') . '.database');
            
            $indexes = DB::select("
                SELECT 
                    table_name,
                    index_name,
                    cardinality
                FROM information_schema.STATISTICS
                WHERE table_schema = ?
                    AND index_name != 'PRIMARY'
                    AND cardinality = 0
                GROUP BY table_name, index_name
            ", [$databaseName]);

            return array_map(function ($index) {
                return [
                    'table' => $index->table_name,
                    'index' => $index->index_name,
                ];
            }, $indexes);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Log performance metrics
     */
    public function logPerformanceMetrics(): void
    {
        $metrics = $this->getPerformanceMetrics();
        
        Log::channel('performance')->info('Database Performance Metrics', $metrics);
        
        // Check for performance issues
        $suggestions = $this->analyzeQueryPerformance();
        if (!empty($suggestions)) {
            foreach ($suggestions as $suggestion) {
                if ($suggestion['severity'] === 'high') {
                    Log::channel('alerts')->warning('Database Performance Issue', $suggestion);
                }
            }
        }
    }
}