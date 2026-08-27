<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_recon_sessions', function (Blueprint $table) {
            $table->string('source')->default('csv')->after('v_id');
            $table->foreignId('organization_id')->nullable()->after('source')->constrained()->nullOnDelete();
            $table->foreignId('pgsql_connection_id')
                ->nullable()
                ->after('organization_id')
                ->constrained('organization_database_connections')
                ->nullOnDelete();
            $table->date('date_from')->nullable()->after('pgsql_connection_id');
            $table->date('date_to')->nullable()->after('date_from');
            $table->unsignedInteger('zwing_query_ms')->nullable()->after('erp_skipped_rows');
            $table->unsignedInteger('erp_query_ms')->nullable()->after('zwing_query_ms');
            $table->text('failure_reason')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_recon_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropConstrainedForeignId('pgsql_connection_id');
            $table->dropColumn([
                'source',
                'date_from',
                'date_to',
                'zwing_query_ms',
                'erp_query_ms',
                'failure_reason',
            ]);
        });
    }
};
