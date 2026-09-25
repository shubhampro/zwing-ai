<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sf_cases', function (Blueprint $table) {
            $table->unsignedInteger('resolution_minutes')->nullable()->index();
            $table->unsignedInteger('zwing_resolution_minutes')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('sf_cases', function (Blueprint $table) {
            $table->dropIndex(['resolution_minutes']);
            $table->dropIndex(['zwing_resolution_minutes']);
            $table->dropColumn(['resolution_minutes', 'zwing_resolution_minutes']);
        });
    }
};
