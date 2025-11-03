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
        Schema::create('sip_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('sip_username')->unique();
            $table->string('sip_password');
            $table->string('sip_context')->default('from-internal');
            $table->string('display_name')->nullable();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->boolean('is_primary')->default(false);
            $table->string('codec_preferences')->nullable();
            $table->boolean('call_forward_enabled')->default(false);
            $table->string('call_forward_number')->nullable();
            $table->boolean('voicemail_enabled')->default(true);
            $table->string('voicemail_email')->nullable();
            $table->integer('freepbx_extension_id')->nullable();
            $table->json('freepbx_settings')->nullable();
            $table->timestamp('last_registered_at')->nullable();
            $table->string('last_registered_ip')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'status']);
            $table->index(['sip_username', 'status']);
            $table->index('is_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sip_accounts');
    }
};