<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zwing_expense_cash_reconsile', function (Blueprint $table) {
            $table->comment('Zwing (POS) expense & cash transaction reconciliation rows');

            $table->id();
            $table->foreignId('session_id')->constrained('expense_cash_recon_sessions')->cascadeOnDelete();
            $table->unsignedBigInteger('v_id')->comment('Vendor (organization) identifier');
            $table->string('site_id', 255)->comment('Store / site identifier');
            $table->string('doc_no', 255)->comment('Document number');
            $table->date('txn_date')->comment('Transaction date');
            $table->decimal('amount', total: 18, places: 4)->comment('Transaction amount');
            $table->string('status', 100)->comment('Transaction status');
            $table->timestamps();

            $table->index(['session_id', 'site_id', 'doc_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zwing_expense_cash_reconsile');
    }
};
