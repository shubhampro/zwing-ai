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
                $table->index(['session_id', 'site_code', 'icode', 'batch_no', 'sprefcode'], $table->getTable().'_join_idx');
            });
        }
    }

    public function down(): void
    {
        foreach (['zwing_stock_reconsile', 'erp_stock_reconsile'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropIndex($table->getTable().'_join_idx');
            });
        }
    }
};
