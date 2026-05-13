<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_recon_sessions', function (Blueprint $table) {
            $table->comment('Header record for each stock reconciliation session');

            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->comment('User who initiated the session');
            $table->unsignedBigInteger('v_id')->comment('Vendor (organization) being reconciled');
            $table->string('zwing_file_name')->nullable()->comment('Original Zwing CSV filename');
            $table->string('erp_file_name')->nullable()->comment('Original ERP CSV filename');
            $table->unsignedInteger('zwing_row_count')->nullable()->comment('Number of rows parsed from Zwing CSV');
            $table->unsignedInteger('erp_row_count')->nullable()->comment('Number of rows parsed from ERP CSV');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending')->comment('Processing status');
            $table->timestamp('reconciled_at')->nullable()->comment('When processing completed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_recon_sessions');
    }
};
