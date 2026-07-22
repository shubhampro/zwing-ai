<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_recon_sessions', function (Blueprint $table) {
            $table->unsignedInteger('zwing_query_ms')->nullable()->after('zwing_skipped_rows')
                ->comment('MySQL connection pull duration in milliseconds');
            $table->unsignedInteger('erp_query_ms')->nullable()->after('erp_skipped_rows')
                ->comment('Postgres connection pull duration in milliseconds');
        });
    }

    public function down(): void
    {
        Schema::table('stock_recon_sessions', function (Blueprint $table) {
            $table->dropColumn(['zwing_query_ms', 'erp_query_ms']);
        });
    }
};
