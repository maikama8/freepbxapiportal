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
        Schema::create('country_rates', function (Blueprint $table) {
            $table->id();
            $table->string('country_code', 3)->unique();
            $table->string('country_name');
            $table->string('country_prefix', 10);
            $table->decimal('did_setup_cost', 8, 4)->default(0);
            $table->decimal('did_monthly_cost', 8, 4)->default(0);
            $table->decimal('call_rate_per_minute', 8, 4)->default(0);
            $table->decimal('sms_rate_per_message', 8, 4)->default(0);
            $table->enum('billing_increment', ['1', '6', '30', '60'])->default('60'); // seconds
            $table->integer('minimum_duration')->default(0); // seconds
            $table->boolean('is_active')->default(true);
            $table->json('area_codes')->nullable(); // Available area codes
            $table->json('features')->nullable(); // voice, sms, fax
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['country_code', 'is_active']);
            $table->index('country_prefix');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('country_rates');
    }
};