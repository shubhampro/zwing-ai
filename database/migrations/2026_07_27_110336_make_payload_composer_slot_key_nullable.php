<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payload_composer_slots', function (Blueprint $table) {
            $table->string('key')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payload_composer_slots', function (Blueprint $table) {
            $table->string('key')->nullable(false)->change();
        });
    }
};
