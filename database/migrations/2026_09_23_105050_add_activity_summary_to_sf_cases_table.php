<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sf_cases', function (Blueprint $table) {
            $table->text('activity_summary')->nullable();
            $table->timestamp('activity_summarized_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('sf_cases', function (Blueprint $table) {
            $table->dropIndex(['activity_summarized_at']);
            $table->dropColumn(['activity_summary', 'activity_summarized_at']);
        });
    }
};
