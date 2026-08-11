<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zwing_transaction_reconsile', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')
                ->constrained('transaction_recon_sessions')
                ->cascadeOnDelete();
            $table->string('txn_id', 100);
            $table->string('code')->nullable();
            $table->string('type')->nullable();
            $table->string('status', 50)->nullable();
            $table->timestamps();

            $table->index(['session_id', 'txn_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zwing_transaction_reconsile');
    }
};
