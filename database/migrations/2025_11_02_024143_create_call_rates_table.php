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
        Schema::create('call_rates', function (Blueprint $table) {
            $table->id();
            $table->string('destination_prefix', 20)->index();
            $table->string('destination_name', 100);
            $table->decimal('rate_per_minute', 8, 6);
            $table->integer('minimum_duration')->default(60); // seconds
            $table->integer('billing_increment')->default(60); // seconds
            $table->datetime('effective_date')->default(now());
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Index for fast lookups
            $table->index(['destination_prefix', 'effective_date', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_rates');
    }
};
