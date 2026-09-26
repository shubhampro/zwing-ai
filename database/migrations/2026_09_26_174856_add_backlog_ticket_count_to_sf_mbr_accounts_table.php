<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sf_mbr_accounts', function (Blueprint $table) {
            $table->unsignedInteger('backlog_ticket_count')->default(0)->after('sf_application_id');
        });
    }

    public function down(): void
    {
        Schema::table('sf_mbr_accounts', function (Blueprint $table) {
            $table->dropColumn('backlog_ticket_count');
        });
    }
};
