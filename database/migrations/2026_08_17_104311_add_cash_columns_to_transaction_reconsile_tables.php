<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = [
        'zwing_transaction_reconsile',
        'erp_transaction_reconsile',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('site_id', 255)->nullable()->after('txn_id');
                $table->date('txn_date')->nullable()->after('status');
                $table->decimal('amount', total: 18, places: 4)->nullable()->after('txn_date');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['site_id', 'txn_date', 'amount']);
            });
        }
    }
};
