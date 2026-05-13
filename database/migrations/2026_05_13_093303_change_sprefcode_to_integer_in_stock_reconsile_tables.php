<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['zwing_stock_reconsile', 'erp_stock_reconsile'] as $table) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("ALTER TABLE \"{$table}\" ALTER COLUMN sprefcode TYPE integer USING 0, ALTER COLUMN sprefcode SET DEFAULT 0, ALTER COLUMN sprefcode SET NOT NULL");
            } else {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->unsignedInteger('sprefcode')->default(0)->change();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['zwing_stock_reconsile', 'erp_stock_reconsile'] as $table) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("ALTER TABLE \"{$table}\" ALTER COLUMN sprefcode TYPE varchar(100) USING sprefcode::varchar, ALTER COLUMN sprefcode DROP DEFAULT, ALTER COLUMN sprefcode DROP NOT NULL");
            } else {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->string('sprefcode', 100)->nullable()->change();
                });
            }
        }
    }
};
