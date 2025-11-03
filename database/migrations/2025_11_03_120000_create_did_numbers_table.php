<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('did_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('did_number')->unique();
            $table->string('country_code', 3);
            $table->string('area_code', 10)->nullable();
            $table->string('provider')->nullable();
            $table->decimal('monthly_cost', 8, 4)->default(0);
            $table->decimal('setup_cost', 8, 4)->default(0);
            $table->enum('status', ['available', 'assigned', 'suspended', 'expired'])->default('available');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('assigned_extension')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('features')->nullable(); // SMS, voice, fax capabilities
            $table->json('metadata')->nullable(); // Additional provider data
            $table->timestamps();

            // Indexes for performance
            $table->index(['country_code', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('status');
            $table->index('area_code');
            
            // Foreign key constraint to country_rates table
            $table->foreign('country_code')->references('country_code')->on('country_rates')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('did_numbers');
    }
};