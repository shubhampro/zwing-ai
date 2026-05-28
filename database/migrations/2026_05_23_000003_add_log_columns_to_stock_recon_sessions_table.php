<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_recon_sessions', function (Blueprint $table): void {
            $table->string('zwing_log_file_name')->nullable()->after('erp_file_name');
            $table->string('erp_log_file_name')->nullable()->after('zwing_log_file_name');
            $table->unsignedInteger('zwing_log_row_count')->nullable()->after('erp_row_count');
            $table->unsignedInteger('erp_log_row_count')->nullable()->after('zwing_log_row_count');
            $table->unsignedInteger('zwing_log_processed_rows')->default(0)->after('erp_skipped_rows');
            $table->unsignedInteger('erp_log_processed_rows')->default(0)->after('zwing_log_processed_rows');
            $table->unsignedInteger('zwing_log_skipped_rows')->default(0)->after('erp_log_processed_rows');
            $table->unsignedInteger('erp_log_skipped_rows')->default(0)->after('zwing_log_skipped_rows');
        });
    }

    public function down(): void
    {
        Schema::table('stock_recon_sessions', function (Blueprint $table): void {
            $table->dropColumn([
                'zwing_log_file_name',
                'erp_log_file_name',
                'zwing_log_row_count',
                'erp_log_row_count',
                'zwing_log_processed_rows',
                'erp_log_processed_rows',
                'zwing_log_skipped_rows',
                'erp_log_skipped_rows',
            ]);
        });
    }
};
