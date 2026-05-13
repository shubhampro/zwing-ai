<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_recon_sessions', function (Blueprint $table) {
            $table->unsignedInteger('zwing_skipped_rows')->default(0)->after('zwing_processed_rows')->comment('Rows skipped due to missing/invalid data in Zwing CSV');
            $table->unsignedInteger('erp_skipped_rows')->default(0)->after('erp_processed_rows')->comment('Rows skipped due to missing/invalid data in ERP CSV');
        });
    }

    public function down(): void
    {
        Schema::table('stock_recon_sessions', function (Blueprint $table) {
            $table->dropColumn(['zwing_skipped_rows', 'erp_skipped_rows']);
        });
    }
};
