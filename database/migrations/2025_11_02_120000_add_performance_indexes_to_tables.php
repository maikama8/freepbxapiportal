<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Check if an index exists on a table
     */
    protected function indexExists(string $table, string $indexName): bool
    {
        try {
            $driver = config('database.connections.' . config('database.default') . '.driver');
            
            if ($driver === 'sqlite') {
                $indexes = DB::select("PRAGMA index_list({$table})");
                foreach ($indexes as $index) {
                    if ($index->name === $indexName) {
                        return true;
                    }
                }
                return false;
            }
            
            // For MySQL/MariaDB
            $database = config('database.connections.' . config('database.default') . '.database');
            $result = DB::select("
                SELECT COUNT(*) as count 
                FROM information_schema.statistics 
                WHERE table_schema = ? AND table_name = ? AND index_name = ?
            ", [$database, $table, $indexName]);
            
            return $result[0]->count > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add indexes to users table for performance optimization
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'email') && !$this->indexExists('users', 'idx_users_email')) {
                    $table->index(['email'], 'idx_users_email');
                }
                if (Schema::hasColumn('users', 'created_at') && !$this->indexExists('users', 'idx_users_created_at')) {
                    $table->index(['created_at'], 'idx_users_created_at');
                }
            });
        }

        // Add indexes to call_records table for performance optimization
        if (Schema::hasTable('call_records')) {
            Schema::table('call_records', function (Blueprint $table) {
                if (Schema::hasColumn('call_records', 'user_id') && !$this->indexExists('call_records', 'idx_call_records_user_id')) {
                    $table->index(['user_id'], 'idx_call_records_user_id');
                }
                if (Schema::hasColumn('call_records', 'created_at') && !$this->indexExists('call_records', 'idx_call_records_created_at')) {
                    $table->index(['created_at'], 'idx_call_records_created_at');
                }
            });
        }

        // Add indexes to payment_transactions table for performance optimization
        if (Schema::hasTable('payment_transactions')) {
            Schema::table('payment_transactions', function (Blueprint $table) {
                if (Schema::hasColumn('payment_transactions', 'user_id') && !$this->indexExists('payment_transactions', 'idx_payment_transactions_user_id')) {
                    $table->index(['user_id'], 'idx_payment_transactions_user_id');
                }
                if (Schema::hasColumn('payment_transactions', 'status') && !$this->indexExists('payment_transactions', 'idx_payment_transactions_status')) {
                    $table->index(['status'], 'idx_payment_transactions_status');
                }
                if (Schema::hasColumn('payment_transactions', 'created_at') && !$this->indexExists('payment_transactions', 'idx_payment_transactions_created_at')) {
                    $table->index(['created_at'], 'idx_payment_transactions_created_at');
                }
            });
        }

        // Add indexes to audit_logs table for performance optimization
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                if (Schema::hasColumn('audit_logs', 'user_id') && !$this->indexExists('audit_logs', 'idx_audit_logs_user_id')) {
                    $table->index(['user_id'], 'idx_audit_logs_user_id');
                }
                if (Schema::hasColumn('audit_logs', 'created_at') && !$this->indexExists('audit_logs', 'idx_audit_logs_created_at')) {
                    $table->index(['created_at'], 'idx_audit_logs_created_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes from users table
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if ($this->indexExists('users', 'idx_users_email')) {
                    $table->dropIndex('idx_users_email');
                }
                if ($this->indexExists('users', 'idx_users_created_at')) {
                    $table->dropIndex('idx_users_created_at');
                }
            });
        }

        // Drop indexes from call_records table
        if (Schema::hasTable('call_records')) {
            Schema::table('call_records', function (Blueprint $table) {
                if ($this->indexExists('call_records', 'idx_call_records_user_id')) {
                    $table->dropIndex('idx_call_records_user_id');
                }
                if ($this->indexExists('call_records', 'idx_call_records_created_at')) {
                    $table->dropIndex('idx_call_records_created_at');
                }
            });
        }

        // Drop indexes from payment_transactions table
        if (Schema::hasTable('payment_transactions')) {
            Schema::table('payment_transactions', function (Blueprint $table) {
                if ($this->indexExists('payment_transactions', 'idx_payment_transactions_user_id')) {
                    $table->dropIndex('idx_payment_transactions_user_id');
                }
                if ($this->indexExists('payment_transactions', 'idx_payment_transactions_status')) {
                    $table->dropIndex('idx_payment_transactions_status');
                }
                if ($this->indexExists('payment_transactions', 'idx_payment_transactions_created_at')) {
                    $table->dropIndex('idx_payment_transactions_created_at');
                }
            });
        }

        // Drop indexes from audit_logs table
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                if ($this->indexExists('audit_logs', 'idx_audit_logs_user_id')) {
                    $table->dropIndex('idx_audit_logs_user_id');
                }
                if ($this->indexExists('audit_logs', 'idx_audit_logs_created_at')) {
                    $table->dropIndex('idx_audit_logs_created_at');
                }
            });
        }
    }
};