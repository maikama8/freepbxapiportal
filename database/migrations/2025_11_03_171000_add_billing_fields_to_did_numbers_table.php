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
        Schema::table('did_numbers', function (Blueprint $table) {
            // Billing and suspension fields
            $table->json('billing_history')->nullable()->after('metadata');
            $table->timestamp('last_billed_at')->nullable()->after('billing_history');
            $table->string('suspension_reason')->nullable()->after('last_billed_at');
            $table->timestamp('suspended_at')->nullable()->after('suspension_reason');
            $table->json('suspension_details')->nullable()->after('suspended_at');
            
            // Add indexes for billing queries
            $table->index('last_billed_at');
            $table->index('suspended_at');
            $table->index(['status', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('did_numbers', function (Blueprint $table) {
            $table->dropIndex(['last_billed_at']);
            $table->dropIndex(['suspended_at']);
            $table->dropIndex(['status', 'user_id']);
            
            $table->dropColumn([
                'billing_history',
                'last_billed_at',
                'suspension_reason',
                'suspended_at',
                'suspension_details'
            ]);
        });
    }
};