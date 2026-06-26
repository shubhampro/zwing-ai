<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zwing_invoice_reconsile', function (Blueprint $table) {
            $table->string('ref_id', 500)
                ->default('')
                ->after('invoice_id')
                ->comment('Hyphen-separated Mop Ref ids used for cross-system matching (e.g. 22-21)');
        });

        Schema::table('erp_invoice_reconsile', function (Blueprint $table) {
            $table->string('ref_id', 500)
                ->default('')
                ->after('invoice_id')
                ->comment('Hyphen-separated Mop Ref ids used for cross-system matching (e.g. 22-21)');
        });
    }

    public function down(): void
    {
        Schema::table('zwing_invoice_reconsile', function (Blueprint $table) {
            $table->dropColumn('ref_id');
        });

        Schema::table('erp_invoice_reconsile', function (Blueprint $table) {
            $table->dropColumn('ref_id');
        });
    }
};
