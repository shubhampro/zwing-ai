<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_recon_invoices', function (Blueprint $table) {
            $table->string('store_name')->nullable()->after('invoice_no')->comment('Store name from Zwing stores table');
        });
    }

    public function down(): void
    {
        Schema::table('report_recon_invoices', function (Blueprint $table) {
            $table->dropColumn('store_name');
        });
    }
};
