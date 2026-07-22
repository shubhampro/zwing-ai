<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_recon_sessions', function (Blueprint $table) {
            $table->text('failure_reason')->nullable()->after('status')
                ->comment('Last queue/job failure message for UI');
        });
    }

    public function down(): void
    {
        Schema::table('stock_recon_sessions', function (Blueprint $table) {
            $table->dropColumn('failure_reason');
        });
    }
};
