<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_recon_sessions', function (Blueprint $table) {
            $table->comment('Header record for each Zwing invoice vs MOP report consolidation session');

            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->comment('User who initiated the session');
            $table->string('name')->unique()->comment('Human-readable unique session name');
            $table->unsignedBigInteger('v_id')->comment('Vendor (organization) identifier');
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date_from')->comment('Inclusive pull start date');
            $table->date('date_to')->comment('Inclusive pull end date');
            $table->unsignedInteger('invoice_row_count')->nullable()->comment('Invoice rows pulled');
            $table->unsignedInteger('mop_row_count')->nullable()->comment('MOP rows pulled');
            $table->unsignedInteger('invoice_processed_rows')->default(0)->comment('Invoice rows inserted');
            $table->unsignedInteger('mop_processed_rows')->default(0)->comment('MOP rows inserted');
            $table->unsignedInteger('invoice_skipped_rows')->default(0)->comment('Invalid invoice rows skipped');
            $table->unsignedInteger('mop_skipped_rows')->default(0)->comment('Invalid MOP rows skipped');
            $table->unsignedInteger('invoice_query_ms')->nullable();
            $table->unsignedInteger('mop_query_ms')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('failure_reason')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('report_recon_invoices', function (Blueprint $table) {
            $table->comment('Zwing invoice report rows for consolidation');

            $table->id();
            $table->foreignId('session_id')->constrained('report_recon_sessions')->cascadeOnDelete();
            $table->unsignedBigInteger('v_id')->comment('Vendor (organization) identifier');
            $table->string('invoice_no', 255);
            $table->date('date')->nullable();
            $table->decimal('total', total: 18, places: 4);
            $table->timestamps();

            $table->index(['session_id', 'invoice_no']);
        });

        Schema::create('report_recon_mops', function (Blueprint $table) {
            $table->comment('Zwing MOP report rows aggregated per invoice');

            $table->id();
            $table->foreignId('session_id')->constrained('report_recon_sessions')->cascadeOnDelete();
            $table->unsignedBigInteger('v_id')->comment('Vendor (organization) identifier');
            $table->string('invoice_no', 255);
            $table->date('date')->nullable();
            $table->decimal('total', total: 18, places: 4);
            $table->timestamps();

            $table->index(['session_id', 'invoice_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_recon_mops');
        Schema::dropIfExists('report_recon_invoices');
        Schema::dropIfExists('report_recon_sessions');
    }
};
