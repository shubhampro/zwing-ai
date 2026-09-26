<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sf_cases', function (Blueprint $table) {
            $table->foreignId('sf_product_id')->nullable()->after('product')->constrained('sf_products')->nullOnDelete();
            $table->foreignId('sf_application_id')->nullable()->after('application')->constrained('sf_applications')->nullOnDelete();
            $table->foreignId('sf_module_id')->nullable()->after('module')->constrained('sf_modules')->nullOnDelete();
            $table->foreignId('sf_sub_module_id')->nullable()->after('sub_module')->constrained('sf_sub_modules')->nullOnDelete();
            $table->foreignId('sf_type_id')->nullable()->after('type')->constrained('sf_types')->nullOnDelete();
            $table->foreignId('sf_group_id')->nullable()->after('group_name')->constrained('sf_groups')->nullOnDelete();
            $table->foreignId('sf_first_assigned_group_id')->nullable()->after('first_assigned_group')->constrained('sf_groups')->nullOnDelete();
            $table->foreignId('sf_agent_id')->nullable()->after('agent_name')->constrained('sf_agents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sf_cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sf_product_id');
            $table->dropConstrainedForeignId('sf_application_id');
            $table->dropConstrainedForeignId('sf_module_id');
            $table->dropConstrainedForeignId('sf_sub_module_id');
            $table->dropConstrainedForeignId('sf_type_id');
            $table->dropConstrainedForeignId('sf_group_id');
            $table->dropConstrainedForeignId('sf_first_assigned_group_id');
            $table->dropConstrainedForeignId('sf_agent_id');
        });
    }
};
