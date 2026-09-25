<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sf_case_activities', function (Blueprint $table) {
            $table->comment('Salesforce CaseComment, CaseFeed, and EmailMessage dump for local MBR');

            $table->id();
            $table->string('sf_id', 18)->unique()->comment('Salesforce activity record Id');
            $table->foreignId('sf_case_id')->constrained('sf_cases')->cascadeOnDelete();
            $table->string('source')->index();
            $table->string('type')->nullable()->index();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('author_name')->nullable();
            $table->boolean('is_incoming')->default(false);
            $table->timestamp('occurred_at')->index();
            $table->timestamp('synced_at')->index();
            $table->timestamps();

            $table->index(['sf_case_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sf_case_activities');
    }
};
