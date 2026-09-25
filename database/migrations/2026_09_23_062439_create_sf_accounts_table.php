<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sf_accounts', function (Blueprint $table) {
            $table->comment('Salesforce Account dump for local MBR');

            $table->id();
            $table->string('sf_id', 18)->unique()->comment('Salesforce Account Id');
            $table->string('name');
            $table->timestamp('last_modified_at_sf')->nullable()->index();
            $table->timestamp('synced_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sf_accounts');
    }
};
