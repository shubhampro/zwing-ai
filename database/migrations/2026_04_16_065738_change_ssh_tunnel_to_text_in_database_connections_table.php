<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('database_connections', function (Blueprint $table) {
            // Change from json to text so the column can store encrypted values.
            // The encrypted:array cast serialises to a ciphertext string, not valid JSON.
            $table->text('ssh_tunnel')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('database_connections', function (Blueprint $table) {
            $table->json('ssh_tunnel')->nullable()->change();
        });
    }
};
