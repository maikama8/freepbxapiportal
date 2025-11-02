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
        Schema::create('call_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('call_id')->unique();
            $table->string('caller_id');
            $table->string('destination');
            $table->timestamp('start_time');
            $table->timestamp('end_time')->nullable();
            $table->integer('duration')->nullable(); // in seconds
            $table->decimal('cost', 10, 4)->nullable();
            $table->string('status')->default('initiated');
            $table->json('freepbx_response')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'start_time']);
            $table->index('call_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_records');
    }
};
