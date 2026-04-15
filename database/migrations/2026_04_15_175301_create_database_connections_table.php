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
        Schema::create('database_connections', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique()->comment('Laravel connection name, e.g. warehouse_read');
            $table->string('connection_group')->comment('Pairs read + write rows, e.g. warehouse');
            $table->string('driver');
            $table->string('access_mode');
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('writes_enabled')->default(false)->comment('For access_mode=write: allow writeForGroup()');
            $table->boolean('enforce_read_only_sql_guard')->default(true)->comment('For access_mode=read on SQL drivers');
            $table->string('url')->nullable();
            $table->string('host')->nullable();
            $table->unsignedSmallInteger('port')->nullable();
            $table->string('database')->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->string('unix_socket')->nullable();
            $table->string('charset')->nullable();
            $table->string('collation')->nullable();
            $table->string('search_path')->nullable();
            $table->string('sslmode')->nullable();
            $table->string('ssl_ca_path')->nullable();
            $table->string('mongodb_dsn')->nullable();
            $table->string('mongodb_authentication_database')->nullable();
            $table->string('mongodb_read_preference')->nullable();
            $table->json('ssh_tunnel')->nullable()->comment('Optional SSH tunnel metadata (not used by Laravel)');
            $table->json('extra_options')->nullable()->comment('Driver-specific overrides');
            $table->timestamps();

            $table->index(['connection_group', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('database_connections');
    }
};
