<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('third_party_api_batch_items')) {
            return;
        }

        Schema::create('third_party_api_batch_items', function (Blueprint $table) {
            $table->comment('Single API call attempt within a third party API batch');

            $table->id();
            $table->foreignId('third_party_api_batch_id')->constrained()->cascadeOnDelete();
            $table->json('payload')->comment('Request body param values keyed by API param key');
            $table->enum('status', ['pending', 'success', 'failed', 'skipped'])->default('pending');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('response_body')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['third_party_api_batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('third_party_api_batch_items');
    }
};
