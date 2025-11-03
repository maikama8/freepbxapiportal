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
        Schema::table('users', function (Blueprint $table) {
            // Add extension context
            $table->string('sip_context')->default('from-internal')->after('sip_password');
            
            // Add extension status
            $table->enum('extension_status', ['active', 'inactive', 'suspended'])->default('active')->after('sip_context');
            
            // Add FreePBX extension ID for tracking
            $table->string('freepbx_extension_id')->nullable()->after('extension_status');
            
            // Add codec preferences
            $table->json('codec_preferences')->nullable()->after('freepbx_extension_id');
            
            // Add call forwarding settings
            $table->string('call_forward_number')->nullable()->after('codec_preferences');
            $table->boolean('call_forward_enabled')->default(false)->after('call_forward_number');
            
            // Add voicemail settings
            $table->boolean('voicemail_enabled')->default(true)->after('call_forward_enabled');
            $table->string('voicemail_email')->nullable()->after('voicemail_enabled');
            
            // Add indexes for performance (sip_username index already exists)
            $table->index('extension_status');
            $table->index('freepbx_extension_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['extension_status']);
            $table->dropIndex(['freepbx_extension_id']);
            
            $table->dropColumn([
                'sip_context',
                'extension_status',
                'freepbx_extension_id',
                'codec_preferences',
                'call_forward_number',
                'call_forward_enabled',
                'voicemail_enabled',
                'voicemail_email'
            ]);
        });
    }
};