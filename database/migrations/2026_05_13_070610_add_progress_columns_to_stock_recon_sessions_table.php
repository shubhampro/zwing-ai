<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_recon_sessions', function (Blueprint $table) {
            $table->unsignedInteger('zwing_processed_rows')->default(0)->after('zwing_row_count')->comment('Rows inserted so far for Zwing CSV');
            $table->unsignedInteger('erp_processed_rows')->default(0)->after('erp_row_count')->comment('Rows inserted so far for ERP CSV');
        });
    }

    public function down(): void
    {
        Schema::table('stock_recon_sessions', function (Blueprint $table) {
            $table->dropColumn(['zwing_processed_rows', 'erp_processed_rows']);
        });
    }
};
