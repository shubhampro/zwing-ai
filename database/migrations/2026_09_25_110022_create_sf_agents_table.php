<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sf_agents', function (Blueprint $table) {
            $table->comment('Salesforce Users referenced as Case Agent__c');

            $table->id();
            $table->string('sf_id', 18)->unique()->comment('Salesforce User Id');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamp('synced_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sf_agents');
    }
};
