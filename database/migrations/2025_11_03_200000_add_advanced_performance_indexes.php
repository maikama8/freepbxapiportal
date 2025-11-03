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
        // DID Numbers table optimization
        if (Schema::hasTable('did_numbers')) {
            Schema::table('did_numbers', function (Blueprint $table) {
                // Index for country-based queries
                if (!$this->indexExists('did_numbers', 'idx_did_numbers_country_status')) {
                    $table->index(['country_code', 'status'], 'idx_did_numbers_country_status');
                }
                
                // Index for user assignment queries
                if (!$this->indexExists('did_numbers', 'idx_did_numbers_user_status')) {
                    $table->index(['user_id', 'status'], 'idx_did_numbers_user_status');
                }
                
                // Index for billing queries
                if (!$this->indexExists('did_numbers', 'idx_did_numbers_billing')) {
                    $table->index(['status', 'assigned_at', 'expires_at'], 'idx_did_numbers_billing');
                }
                
                // Index for inventory management
                if (!$this->indexExists('did_numbers', 'idx_did_numbers_inventory')) {
                    $table->index(['country_code', 'area_code', 'status'], 'idx_did_numbers_inventory');
                }
                
                // Index for expiry monitoring
                if (!$this->indexExists('did_numbers', 'idx_did_numbers_expiry')) {
                    $table->index(['expires_at', 'status'], 'idx_did_numbers_expiry');
                }
            });
        }

        // Country Rates table optimization
        if (Schema::hasTable('country_rates')) {
            Schema::table('country_rates', function (Blueprint $table) {
                // Index for active country lookups
                if (!$this->indexExists('country_rates', 'idx_country_rates_active')) {
                    $table->index(['is_active', 'country_code'], 'idx_country_rates_active');
                }
                
                // Index for prefix-based routing
                if (!$this->indexExists('country_rates', 'idx_country_rates_prefix')) {
                    $table->index(['country_prefix', 'is_active'], 'idx_country_rates_prefix');
                }
            });
        }

        // Call Records table advanced optimization
        if (Schema::hasTable('call_records')) {
            Schema::table('call_records', function (Blueprint $table) {
                // Composite index for billing queries
                if (!$this->indexExists('call_records', 'idx_call_records_billing')) {
                    $table->index(['user_id', 'start_time', 'status'], 'idx_call_records_billing');
                }
                
                // Index for destination-based reporting
                if (!$this->indexExists('call_records', 'idx_call_records_destination')) {
                    $table->index(['destination', 'start_time'], 'idx_call_records_destination');
                }
                
                // Index for duration-based queries
                if (!$this->indexExists('call_records', 'idx_call_records_duration')) {
                    $table->index(['duration', 'cost'], 'idx_call_records_duration');
                }
                
                // Index for real-time billing monitoring
                if (!$this->indexExists('call_records', 'idx_call_records_realtime')) {
                    $table->index(['status', 'start_time', 'user_id'], 'idx_call_records_realtime');
                }
                
                // Index for CDR processing
                if (!$this->indexExists('call_records', 'idx_call_records_cdr_processing')) {
                    $table->index(['processed_at', 'status'], 'idx_call_records_cdr_processing');
                }
            });
        }

        // Balance Transactions table optimization
        if (Schema::hasTable('balance_transactions')) {
            Schema::table('balance_transactions', function (Blueprint $table) {
                // Index for user balance history
                if (!$this->indexExists('balance_transactions', 'idx_balance_transactions_user_history')) {
                    $table->index(['user_id', 'processed_at', 'type'], 'idx_balance_transactions_user_history');
                }
                
                // Index for transaction type queries
                if (!$this->indexExists('balance_transactions', 'idx_balance_transactions_type')) {
                    $table->index(['type', 'processed_at'], 'idx_balance_transactions_type');
                }
                
                // Index for reference lookups
                if (!$this->indexExists('balance_transactions', 'idx_balance_transactions_reference')) {
                    $table->index(['reference_type', 'reference_id'], 'idx_balance_transactions_reference');
                }
            });
        }

        // Payment Transactions advanced optimization
        if (Schema::hasTable('payment_transactions')) {
            Schema::table('payment_transactions', function (Blueprint $table) {
                // Index for gateway-specific queries
                if (!$this->indexExists('payment_transactions', 'idx_payment_transactions_gateway')) {
                    $table->index(['gateway', 'status', 'created_at'], 'idx_payment_transactions_gateway');
                }
                
                // Index for transaction ID lookups
                if (!$this->indexExists('payment_transactions', 'idx_payment_transactions_gateway_id')) {
                    $table->index(['gateway_transaction_id'], 'idx_payment_transactions_gateway_id');
                }
                
                // Index for amount-based queries
                if (!$this->indexExists('payment_transactions', 'idx_payment_transactions_amount')) {
                    $table->index(['amount', 'currency', 'status'], 'idx_payment_transactions_amount');
                }
            });
        }

        // Call Rates table optimization
        if (Schema::hasTable('call_rates')) {
            Schema::table('call_rates', function (Blueprint $table) {
                // Index for prefix matching (most important for call routing)
                if (!$this->indexExists('call_rates', 'idx_call_rates_prefix_effective')) {
                    $table->index(['destination_prefix', 'effective_date'], 'idx_call_rates_prefix_effective');
                }
                
                // Index for rate management
                if (!$this->indexExists('call_rates', 'idx_call_rates_management')) {
                    $table->index(['destination_name', 'effective_date'], 'idx_call_rates_management');
                }
            });
        }

        // Invoices table optimization
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                // Index for user invoice queries
                if (!$this->indexExists('invoices', 'idx_invoices_user_status')) {
                    $table->index(['user_id', 'status', 'due_date'], 'idx_invoices_user_status');
                }
                
                // Index for billing period queries
                if (!$this->indexExists('invoices', 'idx_invoices_billing_period')) {
                    $table->index(['billing_period_start', 'billing_period_end'], 'idx_invoices_billing_period');
                }
            });
        }

        // Invoice Items table optimization
        if (Schema::hasTable('invoice_items')) {
            Schema::table('invoice_items', function (Blueprint $table) {
                // Index for invoice item queries
                if (!$this->indexExists('invoice_items', 'idx_invoice_items_invoice')) {
                    $table->index(['invoice_id', 'item_type'], 'idx_invoice_items_invoice');
                }
            });
        }

        // SIP Accounts table optimization
        if (Schema::hasTable('sip_accounts')) {
            Schema::table('sip_accounts', function (Blueprint $table) {
                // Index for user SIP account queries
                if (!$this->indexExists('sip_accounts', 'idx_sip_accounts_user')) {
                    $table->index(['user_id', 'status'], 'idx_sip_accounts_user');
                }
                
                // Index for extension lookups
                if (!$this->indexExists('sip_accounts', 'idx_sip_accounts_extension')) {
                    $table->index(['extension'], 'idx_sip_accounts_extension');
                }
                
                // Index for sync operations
                if (!$this->indexExists('sip_accounts', 'idx_sip_accounts_sync')) {
                    $table->index(['freepbx_synced_at', 'sync_status'], 'idx_sip_accounts_sync');
                }
            });
        }

        // Cron Job Executions table optimization
        if (Schema::hasTable('cron_job_executions')) {
            Schema::table('cron_job_executions', function (Blueprint $table) {
                // Index for job monitoring
                if (!$this->indexExists('cron_job_executions', 'idx_cron_executions_monitoring')) {
                    $table->index(['job_name', 'status', 'started_at'], 'idx_cron_executions_monitoring');
                }
                
                // Index for cleanup operations
                if (!$this->indexExists('cron_job_executions', 'idx_cron_executions_cleanup')) {
                    $table->index(['started_at', 'status'], 'idx_cron_executions_cleanup');
                }
                
                // Index for performance analysis
                if (!$this->indexExists('cron_job_executions', 'idx_cron_executions_performance')) {
                    $table->index(['job_name', 'completed_at', 'duration_seconds'], 'idx_cron_executions_performance');
                }
            });
        }

        // DID Transfers table optimization (if exists)
        if (Schema::hasTable('did_transfers')) {
            Schema::table('did_transfers', function (Blueprint $table) {
                // Index for transfer tracking
                if (!$this->indexExists('did_transfers', 'idx_did_transfers_tracking')) {
                    $table->index(['did_number_id', 'status', 'created_at'], 'idx_did_transfers_tracking');
                }
                
                // Index for user transfer history
                if (!$this->indexExists('did_transfers', 'idx_did_transfers_user_history')) {
                    $table->index(['from_user_id', 'to_user_id', 'created_at'], 'idx_did_transfers_user_history');
                }
            });
        }

        // System Settings table optimization
        if (Schema::hasTable('system_settings')) {
            Schema::table('system_settings', function (Blueprint $table) {
                // Index for setting lookups
                if (!$this->indexExists('system_settings', 'idx_system_settings_key')) {
                    $table->index(['key'], 'idx_system_settings_key');
                }
                
                // Index for category-based queries
                if (!$this->indexExists('system_settings', 'idx_system_settings_category')) {
                    $table->index(['category', 'key'], 'idx_system_settings_category');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop DID Numbers indexes
        if (Schema::hasTable('did_numbers')) {
            Schema::table('did_numbers', function (Blueprint $table) {
                $indexes = [
                    'idx_did_numbers_country_status',
                    'idx_did_numbers_user_status',
                    'idx_did_numbers_billing',
                    'idx_did_numbers_inventory',
                    'idx_did_numbers_expiry'
                ];
                
                foreach ($indexes as $index) {
                    if ($this->indexExists('did_numbers', $index)) {
                        $table->dropIndex($index);
                    }
                }
            });
        }

        // Drop Country Rates indexes
        if (Schema::hasTable('country_rates')) {
            Schema::table('country_rates', function (Blueprint $table) {
                $indexes = [
                    'idx_country_rates_active',
                    'idx_country_rates_prefix'
                ];
                
                foreach ($indexes as $index) {
                    if ($this->indexExists('country_rates', $index)) {
                        $table->dropIndex($index);
                    }
                }
            });
        }

        // Drop Call Records indexes
        if (Schema::hasTable('call_records')) {
            Schema::table('call_records', function (Blueprint $table) {
                $indexes = [
                    'idx_call_records_billing',
                    'idx_call_records_destination',
                    'idx_call_records_duration',
                    'idx_call_records_realtime',
                    'idx_call_records_cdr_processing'
                ];
                
                foreach ($indexes as $index) {
                    if ($this->indexExists('call_records', $index)) {
                        $table->dropIndex($index);
                    }
                }
            });
        }

        // Drop Balance Transactions indexes
        if (Schema::hasTable('balance_transactions')) {
            Schema::table('balance_transactions', function (Blueprint $table) {
                $indexes = [
                    'idx_balance_transactions_user_history',
                    'idx_balance_transactions_type',
                    'idx_balance_transactions_reference'
                ];
                
                foreach ($indexes as $index) {
                    if ($this->indexExists('balance_transactions', $index)) {
                        $table->dropIndex($index);
                    }
                }
            });
        }

        // Drop Payment Transactions indexes
        if (Schema::hasTable('payment_transactions')) {
            Schema::table('payment_transactions', function (Blueprint $table) {
                $indexes = [
                    'idx_payment_transactions_gateway',
                    'idx_payment_transactions_gateway_id',
                    'idx_payment_transactions_amount'
                ];
                
                foreach ($indexes as $index) {
                    if ($this->indexExists('payment_transactions', $index)) {
                        $table->dropIndex($index);
                    }
                }
            });
        }

        // Drop Call Rates indexes
        if (Schema::hasTable('call_rates')) {
            Schema::table('call_rates', function (Blueprint $table) {
                $indexes = [
                    'idx_call_rates_prefix_effective',
                    'idx_call_rates_management'
                ];
                
                foreach ($indexes as $index) {
                    if ($this->indexExists('call_rates', $index)) {
                        $table->dropIndex($index);
                    }
                }
            });
        }

        // Drop remaining indexes for other tables
        $tablesToClean = [
            'invoices' => ['idx_invoices_user_status', 'idx_invoices_billing_period'],
            'invoice_items' => ['idx_invoice_items_invoice'],
            'sip_accounts' => ['idx_sip_accounts_user', 'idx_sip_accounts_extension', 'idx_sip_accounts_sync'],
            'cron_job_executions' => ['idx_cron_executions_monitoring', 'idx_cron_executions_cleanup', 'idx_cron_executions_performance'],
            'did_transfers' => ['idx_did_transfers_tracking', 'idx_did_transfers_user_history'],
            'system_settings' => ['idx_system_settings_key', 'idx_system_settings_category']
        ];

        foreach ($tablesToClean as $tableName => $indexes) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($indexes, $tableName) {
                    foreach ($indexes as $index) {
                        if ($this->indexExists($tableName, $index)) {
                            $table->dropIndex($index);
                        }
                    }
                });
            }
        }
    }
};