<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sf_mbr_accounts', function (Blueprint $table) {
            $table->comment('MBR account rows for one report and application');

            $table->id();
            $table->foreignId('sf_mbr_report_id')->constrained('sf_mbr_reports')->cascadeOnDelete();
            $table->foreignId('sf_account_id')->constrained('sf_accounts')->cascadeOnDelete();
            $table->foreignId('sf_application_id')->constrained('sf_applications')->cascadeOnDelete();
            $table->unsignedInteger('created_ticket_count')->default(0);
            $table->unsignedInteger('resolved_or_closed_count')->default(0);
            $table->unsignedInteger('open_ticket_count')->default(0);
            $table->timestamps();

            $table->unique(['sf_mbr_report_id', 'sf_account_id', 'sf_application_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sf_mbr_accounts');
    }
};
