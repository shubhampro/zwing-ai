<?php

use App\Enums\SfMbrReportStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sf_mbr_reports', function (Blueprint $table) {
            $table->string('status')->default(SfMbrReportStatus::Pending->value)->after('ends_on');
            $table->string('current_section')->nullable()->after('status');
            $table->text('failed_reason')->nullable()->after('current_section');

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('sf_mbr_reports', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'current_section', 'failed_reason']);
        });
    }
};
