<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('zwing_expense_cash_reconsile', 'txn_date')) {
            Schema::table('zwing_expense_cash_reconsile', function (Blueprint $table) {
                $table->date('txn_date')->after('doc_no')->comment('Transaction date');
            });
        }

        if (! Schema::hasColumn('erp_expense_cash_reconsile', 'txn_date')) {
            Schema::table('erp_expense_cash_reconsile', function (Blueprint $table) {
                $table->date('txn_date')->after('doc_no')->comment('Transaction date');
            });
        }
    }

    public function down(): void
    {
        // Column ships on create migrations; leave down as no-op when already present there.
    }
};
