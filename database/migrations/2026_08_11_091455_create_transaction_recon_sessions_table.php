<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_recon_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 20);
            $table->unsignedBigInteger('v_id');
            $table->string('source')->default('connection');
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pgsql_connection_id')
                ->nullable()
                ->constrained('organization_database_connections')
                ->nullOnDelete();
            $table->string('zwing_file_name')->nullable();
            $table->string('erp_file_name')->nullable();
            $table->unsignedInteger('zwing_row_count')->nullable();
            $table->unsignedInteger('erp_row_count')->nullable();
            $table->unsignedInteger('zwing_processed_rows')->default(0);
            $table->unsignedInteger('erp_processed_rows')->default(0);
            $table->unsignedInteger('zwing_skipped_rows')->default(0);
            $table->unsignedInteger('erp_skipped_rows')->default(0);
            $table->unsignedInteger('zwing_query_ms')->nullable();
            $table->unsignedInteger('erp_query_ms')->nullable();
            $table->string('status')->default('pending');
            $table->text('failure_reason')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_recon_sessions');
    }
};
