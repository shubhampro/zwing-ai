<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_recon_zwing_logs', function (Blueprint $table): void {
            $table->decimal('qty', 18, 4)->default(0)->after('enttype');
        });

        Schema::table('stock_recon_erp_logs', function (Blueprint $table): void {
            $table->decimal('qty', 18, 4)->default(0)->after('enttype');
        });
    }

    public function down(): void
    {
        Schema::table('stock_recon_zwing_logs', function (Blueprint $table): void {
            $table->dropColumn('qty');
        });

        Schema::table('stock_recon_erp_logs', function (Blueprint $table): void {
            $table->dropColumn('qty');
        });
    }
};
