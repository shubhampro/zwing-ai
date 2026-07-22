<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_database_connections', function (Blueprint $table) {
            $table->comment('Per-organization database login credentials by connection type');

            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('type')->comment('Connection type: mysql, pgsql');
            $table->text('database_name')->comment('Encrypted database name');
            $table->string('username');
            $table->text('password')->comment('Encrypted database password');
            $table->string('host')->nullable()->comment('Optional host override; null uses shared config');
            $table->unsignedInteger('port')->nullable()->comment('Optional port override; null uses shared config');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_database_connections');
    }
};
