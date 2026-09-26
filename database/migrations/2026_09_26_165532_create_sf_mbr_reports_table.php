<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sf_mbr_reports', function (Blueprint $table) {
            $table->comment('Saved MBR report filters');

            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();

            $table->index(['starts_on', 'ends_on']);
        });

        Schema::create('sf_mbr_report_application', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sf_mbr_report_id')->constrained('sf_mbr_reports')->cascadeOnDelete();
            $table->foreignId('sf_application_id')->constrained('sf_applications')->cascadeOnDelete();
            $table->unique(['sf_mbr_report_id', 'sf_application_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sf_mbr_report_application');
        Schema::dropIfExists('sf_mbr_reports');
    }
};
