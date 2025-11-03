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
        Schema::create('cron_job_executions', function (Blueprint $table) {
            $table->id();
            $table->string('execution_id')->unique();
            $table->string('job_name');
            $table->enum('status', ['running', 'completed', 'failed'])->default('running');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->integer('pid')->nullable();
            $table->bigInteger('memory_start')->nullable();
            $table->bigInteger('memory_end')->nullable();
            $table->bigInteger('memory_peak')->nullable();
            $table->json('metadata')->nullable();
            $table->json('result')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['job_name', 'started_at']);
            $table->index(['status', 'started_at']);
            $table->index('started_at');
            $table->index('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cron_job_executions');
    }
};