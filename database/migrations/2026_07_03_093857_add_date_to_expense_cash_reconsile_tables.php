<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zwing_expense_cash_reconsile', function (Blueprint $table) {
            $table->date('txn_date')->after('doc_no')->comment('Transaction date');
        });

        Schema::table('erp_expense_cash_reconsile', function (Blueprint $table) {
            $table->date('txn_date')->after('doc_no')->comment('Transaction date');
        });
    }

    public function down(): void
    {
        Schema::table('zwing_expense_cash_reconsile', function (Blueprint $table) {
            $table->dropColumn('txn_date');
        });

        Schema::table('erp_expense_cash_reconsile', function (Blueprint $table) {
            $table->dropColumn('txn_date');
        });
    }
};
