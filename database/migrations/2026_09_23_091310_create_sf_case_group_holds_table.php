<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sf_case_group_holds', function (Blueprint $table) {
            $table->comment('Derived group hold stints from CaseHistory Group__c hops');

            $table->id();
            $table->foreignId('sf_case_id')->constrained('sf_cases')->cascadeOnDelete();
            $table->string('group_name')->index();
            $table->timestamp('started_at')->index();
            $table->timestamp('ended_at');
            $table->unsignedInteger('held_minutes');
            $table->boolean('is_open')->default(false)->index();
            $table->timestamp('computed_at');
            $table->timestamps();

            $table->index(['sf_case_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sf_case_group_holds');
    }
};
