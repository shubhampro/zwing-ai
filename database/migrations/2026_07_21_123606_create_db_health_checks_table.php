<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('db_health_checks', function (Blueprint $table) {
            $table->id();
            $table->timestamp('ran_at');
            $table->string('overall_status', 32);
            $table->json('results');
            $table->timestamps();

            $table->index('ran_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_health_checks');
    }
};
