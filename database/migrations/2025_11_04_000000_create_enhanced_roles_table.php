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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('display_name', 100);
            $table->text('description')->nullable();
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['is_active', 'is_system']);
            $table->index('name');
        });

        // Insert default system roles
        DB::table('roles')->insert([
            [
                'name' => 'admin',
                'display_name' => 'Administrator',
                'description' => 'Full system access with all permissions',
                'permissions' => json_encode([
                    'users.view', 'users.create', 'users.edit', 'users.delete', 'users.balance',
                    'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
                    'rates.view', 'rates.create', 'rates.edit', 'rates.delete',
                    'billing.view', 'billing.manage',
                    'dids.view', 'dids.create', 'dids.edit', 'dids.delete', 'dids.assign',
                    'calls.view', 'calls.manage', 'calls.terminate', 'cdr.view',
                    'system.view', 'system.settings', 'system.monitoring',
                    'audit.view', 'cron.manage',
                    'reports.view', 'reports.export', 'analytics.view'
                ]),
                'is_active' => true,
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'customer',
                'display_name' => 'Customer',
                'description' => 'Standard customer access for VoIP services',
                'permissions' => json_encode([
                    'calls.view', 'calls.manage',
                    'dids.view',
                    'billing.view',
                    'reports.view'
                ]),
                'is_active' => true,
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'operator',
                'display_name' => 'Operator',
                'description' => 'Limited administrative access for customer support',
                'permissions' => json_encode([
                    'users.view', 'users.edit',
                    'calls.view', 'calls.manage', 'cdr.view',
                    'dids.view', 'dids.assign',
                    'billing.view',
                    'reports.view', 'reports.export'
                ]),
                'is_active' => true,
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};