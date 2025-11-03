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
        Schema::table('sip_accounts', function (Blueprint $table) {
            // FreePBX sync tracking fields
            $table->string('freepbx_sync_status')->nullable()->after('freepbx_settings');
            $table->timestamp('freepbx_last_sync_at')->nullable()->after('freepbx_sync_status');
            $table->json('freepbx_extension_data')->nullable()->after('freepbx_last_sync_at');
            $table->integer('sync_retry_count')->default(0)->after('freepbx_extension_data');
            $table->text('sync_last_error')->nullable()->after('sync_retry_count');
            $table->timestamp('sync_last_attempt_at')->nullable()->after('sync_last_error');
            
            // Add indexes for sync queries
            $table->index('freepbx_sync_status');
            $table->index('freepbx_last_sync_at');
            $table->index('sync_last_attempt_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sip_accounts', function (Blueprint $table) {
            $table->dropIndex(['freepbx_sync_status']);
            $table->dropIndex(['freepbx_last_sync_at']);
            $table->dropIndex(['sync_last_attempt_at']);
            
            $table->dropColumn([
                'freepbx_sync_status',
                'freepbx_last_sync_at',
                'freepbx_extension_data',
                'sync_retry_count',
                'sync_last_error',
                'sync_last_attempt_at'
            ]);
        });
    }
};