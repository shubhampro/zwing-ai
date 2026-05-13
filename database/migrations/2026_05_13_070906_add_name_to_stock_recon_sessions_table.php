<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_recon_sessions', function (Blueprint $table) {
            $table->string('name')->unique()->after('user_id')->comment('Human-readable unique session name');
        });
    }

    public function down(): void
    {
        Schema::table('stock_recon_sessions', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->dropColumn('name');
        });
    }
};
