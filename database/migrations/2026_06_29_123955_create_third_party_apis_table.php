<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('third_party_apis') || Schema::hasTable('organization_apis')) {
            return;
        }

        Schema::create('third_party_apis', function (Blueprint $table) {
            $table->comment('Reusable third-party HTTP API templates shared across organizations');

            $table->id();
            $table->string('name')->comment('Display label shown in the UI');
            $table->string('path')->comment('Endpoint path appended to each organization base URL');
            $table->string('method')->default('POST')->comment('HTTP method: GET, POST, PUT, PATCH, DELETE');
            $table->json('params')->default('[]')->comment('Request body/query param definitions');
            $table->string('auth_header_name')->default('Authorization')->comment('Header name for API token');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('organization_third_party_apis', function (Blueprint $table) {
            $table->comment('Per-organization base URL and token for a reusable third party API template');

            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('third_party_api_id')->constrained()->cascadeOnDelete();
            $table->string('base_url')->comment('Organization-specific API host/base URL without trailing slash');
            $table->text('auth_token')->comment('Encrypted API token or key');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'third_party_api_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_third_party_apis');
        Schema::dropIfExists('third_party_apis');
    }
};
