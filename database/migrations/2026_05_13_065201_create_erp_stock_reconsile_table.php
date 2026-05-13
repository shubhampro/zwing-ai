<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_stock_reconsile', function (Blueprint $table) {
            $table->comment('ERP stock reconciliation rows');

            $table->id();
            $table->foreignId('session_id')->constrained('stock_recon_sessions')->cascadeOnDelete()->comment('Parent reconciliation session');
            $table->string('batch_no', 100)->comment('Batch identifier within the session');
            $table->unsignedBigInteger('v_id')->comment('Vendor (organization) identifier');
            $table->string('barcode', 255)->comment('Product barcode');
            $table->string('icode', 255)->comment('Item / internal product code');
            $table->string('stock_point_name', 255)->comment('Stock point or location name');
            $table->decimal('qty', total: 18, places: 4)->comment('Quantity');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_stock_reconsile');
    }
};
