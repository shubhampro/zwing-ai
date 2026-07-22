<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_recon_sessions', function (Blueprint $table) {
            $table->foreignId('pgsql_connection_id')
                ->nullable()
                ->after('organization_id')
                ->constrained('organization_database_connections')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_recon_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pgsql_connection_id');
        });
    }
};
