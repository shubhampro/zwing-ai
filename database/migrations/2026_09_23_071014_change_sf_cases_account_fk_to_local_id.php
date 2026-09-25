<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sf_cases', function (Blueprint $table) {
            $table->dropForeign(['account_sf_id']);
        });

        Schema::table('sf_cases', function (Blueprint $table) {
            $table->foreignId('sf_account_id')
                ->nullable()
                ->after('agent_name')
                ->constrained('sf_accounts')
                ->nullOnDelete();
        });

        foreach (DB::table('sf_accounts')->select('id', 'sf_id')->orderBy('id')->get() as $account) {
            DB::table('sf_cases')
                ->where('account_sf_id', $account->sf_id)
                ->update(['sf_account_id' => $account->id]);
        }

        Schema::table('sf_cases', function (Blueprint $table) {
            $table->dropIndex(['account_sf_id']);
            $table->dropColumn('account_sf_id');
        });
    }

    public function down(): void
    {
        Schema::table('sf_cases', function (Blueprint $table) {
            $table->string('account_sf_id', 18)->nullable()->index();
        });

        foreach (DB::table('sf_accounts')->select('id', 'sf_id')->orderBy('id')->get() as $account) {
            DB::table('sf_cases')
                ->where('sf_account_id', $account->id)
                ->update(['account_sf_id' => $account->sf_id]);
        }

        Schema::table('sf_cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sf_account_id');
            $table->foreign('account_sf_id')
                ->references('sf_id')
                ->on('sf_accounts')
                ->nullOnDelete();
        });
    }
};
