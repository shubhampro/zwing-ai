<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payload_composer_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payload_composer_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->foreignId('saved_sql_query_id')->constrained('saved_sql_queries')->cascadeOnDelete();
            $table->string('shape')->default('array');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['payload_composer_id', 'key']);
            $table->index(['payload_composer_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payload_composer_slots');
    }
};
