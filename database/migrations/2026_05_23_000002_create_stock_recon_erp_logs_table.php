<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_recon_erp_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_recon_session_id')->constrained('stock_recon_sessions')->cascadeOnDelete();
            $table->unsignedBigInteger('v_id');
            $table->string('site_code', 100)->default('');
            $table->string('icode', 255)->default('');
            $table->string('batch_no', 100)->default('');
            $table->string('sprefcode', 100)->default('');
            $table->string('doc_no', 100)->default('');
            $table->string('enttype', 50)->default('');
            $table->timestamps();

            $table->index('stock_recon_session_id');
            $table->index(['stock_recon_session_id', 'site_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_recon_erp_logs');
    }
};
