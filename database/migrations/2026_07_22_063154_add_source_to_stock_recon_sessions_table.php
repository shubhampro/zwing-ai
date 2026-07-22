<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_recon_sessions', function (Blueprint $table) {
            $table->string('source')->default('csv')->after('v_id')
                ->comment('Data source: csv or connection');
            $table->foreignId('organization_id')->nullable()->after('source')
                ->constrained()->nullOnDelete()
                ->comment('Organization used for connection-based pull');
        });
    }

    public function down(): void
    {
        Schema::table('stock_recon_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn('source');
        });
    }
};
