<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_recon_sessions', function (Blueprint $table) {
            $table->comment('Header record for each invoice reconciliation session');

            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->comment('User who initiated the session');
            $table->string('name')->unique()->comment('Human-readable unique session name');
            $table->unsignedBigInteger('v_id')->comment('Vendor (organization) being reconciled');
            $table->string('zwing_file_name')->nullable()->comment('Original Zwing CSV filename');
            $table->string('erp_file_name')->nullable()->comment('Original ERP CSV filename');
            $table->unsignedInteger('zwing_row_count')->nullable()->comment('Number of rows in Zwing CSV');
            $table->unsignedInteger('erp_row_count')->nullable()->comment('Number of rows in ERP CSV');
            $table->unsignedInteger('zwing_processed_rows')->default(0)->comment('Rows inserted from Zwing CSV');
            $table->unsignedInteger('erp_processed_rows')->default(0)->comment('Rows inserted from ERP CSV');
            $table->unsignedInteger('zwing_skipped_rows')->default(0)->comment('Invalid rows skipped in Zwing CSV');
            $table->unsignedInteger('erp_skipped_rows')->default(0)->comment('Invalid rows skipped in ERP CSV');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending')->comment('Processing status');
            $table->timestamp('reconciled_at')->nullable()->comment('When processing completed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_recon_sessions');
    }
};
