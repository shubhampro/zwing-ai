<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sf_cases', function (Blueprint $table) {
            $table->comment('Salesforce Case header dump for Product Zwing');

            $table->id();
            $table->string('sf_id', 18)->unique()->comment('Salesforce Case Id');
            $table->string('case_number', 20)->unique();
            $table->string('subject');
            $table->text('description')->nullable();
            $table->string('status')->index();
            $table->string('priority')->nullable();
            $table->string('type')->nullable();
            $table->string('origin')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->boolean('is_spam')->default(false);
            $table->string('product')->index();
            $table->string('product_name')->nullable();
            $table->string('application')->nullable();
            $table->string('module')->nullable();
            $table->string('sub_module')->nullable();
            $table->string('group_name')->nullable()->index();
            $table->string('first_assigned_group')->nullable();
            $table->string('owner_name')->nullable();
            $table->string('agent_name')->nullable();
            $table->string('account_sf_id', 18)->nullable()->index();
            $table->string('requester_name')->nullable();
            $table->text('tags')->nullable();
            $table->string('size')->nullable();
            $table->string('jira_id')->nullable();
            $table->string('jira_status')->nullable();
            $table->timestamp('created_at_sf')->index();
            $table->timestamp('resolved_at_sf')->nullable()->index();
            $table->timestamp('closed_at_sf')->nullable();
            $table->timestamp('last_modified_at_sf')->nullable();
            $table->timestamp('synced_at')->index();
            $table->timestamps();

            $table->foreign('account_sf_id')
                ->references('sf_id')
                ->on('sf_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sf_cases');
    }
};
