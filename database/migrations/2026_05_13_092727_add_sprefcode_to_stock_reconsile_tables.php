<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['zwing_stock_reconsile', 'erp_stock_reconsile'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('sprefcode', 100)->after('site_code')->comment('Stock point reference code');
            });
        }
    }

    public function down(): void
    {
        foreach (['zwing_stock_reconsile', 'erp_stock_reconsile'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('sprefcode');
            });
        }
    }
};
