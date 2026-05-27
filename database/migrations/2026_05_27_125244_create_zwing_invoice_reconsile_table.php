<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zwing_invoice_reconsile', function (Blueprint $table) {
            $table->comment('Zwing (POS) invoice reconciliation rows');

            $table->id();
            $table->foreignId('session_id')->constrained('invoice_recon_sessions')->cascadeOnDelete();
            $table->unsignedBigInteger('v_id')->comment('Vendor (organization) identifier');
            $table->string('invoice_id', 255)->comment('Invoice identifier');
            $table->decimal('total_amount', total: 18, places: 4)->comment('Invoice total amount');
            $table->string('status', 100)->comment('Invoice status');
            $table->timestamps();

            $table->index(['session_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zwing_invoice_reconsile');
    }
};
