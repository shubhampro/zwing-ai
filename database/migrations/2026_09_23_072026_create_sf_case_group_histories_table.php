<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sf_case_group_histories', function (Blueprint $table) {
            $table->comment('Salesforce CaseHistory Group/Owner changes for hold-time analysis');

            $table->id();
            $table->string('sf_id', 18)->unique()->comment('Salesforce CaseHistory Id');
            $table->foreignId('sf_case_id')->constrained('sf_cases')->cascadeOnDelete();
            $table->string('field')->index();
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            $table->string('changed_by')->nullable();
            $table->timestamp('changed_at')->index();
            $table->timestamp('synced_at')->index();
            $table->timestamps();

            $table->index(['sf_case_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sf_case_group_histories');
    }
};
