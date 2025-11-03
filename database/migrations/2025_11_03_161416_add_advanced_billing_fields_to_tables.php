<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add advanced billing fields to call_rates table
        Schema::table('call_rates', function (Blueprint $table) {
            $table->string('billing_increment_config')->default('6/6')->after('billing_increment');
            $table->json('billing_rules')->nullable()->after('billing_increment_config');
        });

        // Add advanced billing fields to call_records table
        Schema::table('call_records', function (Blueprint $table) {
            $table->enum('billing_status', ['pending', 'calculated', 'paid', 'unpaid', 'error', 'terminated'])
                  ->default('pending')->after('cost');
            $table->json('billing_details')->nullable()->after('billing_status');
            $table->integer('actual_duration')->nullable()->after('billing_details');
            $table->integer('billable_duration')->nullable()->after('actual_duration');
        });

        // Add billing increment configuration to country_rates table
        Schema::table('country_rates', function (Blueprint $table) {
            $table->string('billing_increment_config')->default('6/6')->after('billing_increment');
            $table->json('billing_rules')->nullable()->after('billing_increment_config');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('call_rates', function (Blueprint $table) {
            $table->dropColumn(['billing_increment_config', 'billing_rules']);
        });

        Schema::table('call_records', function (Blueprint $table) {
            $table->dropColumn(['billing_status', 'billing_details', 'actual_duration', 'billable_duration']);
        });

        Schema::table('country_rates', function (Blueprint $table) {
            $table->dropColumn(['billing_increment_config', 'billing_rules']);
        });
    }
};