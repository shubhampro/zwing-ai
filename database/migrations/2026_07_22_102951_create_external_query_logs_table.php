<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_query_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stock_recon_session_id')
                ->nullable()
                ->constrained('stock_recon_sessions')
                ->nullOnDelete();
            $table->string('job_type', 50);
            $table->string('status', 30)->default('pending');
            $table->json('context')->nullable();
            $table->json('result')->nullable();
            $table->unsignedInteger('zwing_query_ms')->nullable();
            $table->unsignedInteger('erp_query_ms')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['stock_recon_session_id', 'job_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_query_logs');
    }
};
