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
            // VoIP Platform specific fields
            $table->string('phone')->nullable()->after('email');
            $table->enum('role', ['admin', 'customer', 'operator'])->default('customer')->after('phone');
            $table->enum('account_type', ['prepaid', 'postpaid'])->default('prepaid')->after('role');
            $table->decimal('balance', 10, 4)->default(0.0000)->after('account_type');
            $table->decimal('credit_limit', 10, 4)->default(0.0000)->after('balance');
            $table->enum('status', ['active', 'inactive', 'suspended', 'locked'])->default('active')->after('credit_limit');
            $table->string('timezone', 50)->default('UTC')->after('status');
            $table->string('currency', 3)->default('USD')->after('timezone');
            $table->string('sip_username')->nullable()->after('currency');
            $table->string('sip_password')->nullable()->after('sip_username');
            $table->integer('failed_login_attempts')->default(0)->after('sip_password');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
            $table->timestamp('last_login_at')->nullable()->after('locked_until');
            $table->string('last_login_ip')->nullable()->after('last_login_at');
            
            // Indexes for performance
            $table->index(['role', 'status']);
            $table->index(['account_type', 'status']);
            $table->index('sip_username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'status']);
            $table->dropIndex(['account_type', 'status']);
            $table->dropIndex(['sip_username']);
            
            $table->dropColumn([
                'phone', 'role', 'account_type', 'balance', 'credit_limit', 
                'status', 'timezone', 'currency', 'sip_username', 'sip_password',
                'failed_login_attempts', 'locked_until', 'last_login_at', 'last_login_ip'
            ]);
        });
    }
};
