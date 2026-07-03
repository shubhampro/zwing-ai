<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('third_party_api_batches')) {
            return;
        }

        Schema::create('third_party_api_batches', function (Blueprint $table) {
            $table->comment('Generic CSV-driven batch runs against a third party API');

            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_third_party_api_id')->constrained()->cascadeOnDelete();
            $table->string('name')->unique();
            $table->string('file_name')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->json('defaults')->default('{}')->comment('Batch-level default param values keyed by param key');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('third_party_api_batches');
    }
};
