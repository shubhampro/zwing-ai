<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organization_apis')) {
            return;
        }

        Schema::rename('organization_apis', 'third_party_apis');

        Schema::table('third_party_apis', function (Blueprint $table) {
            $table->string('method')->default('DELETE');
            $table->json('params')->nullable();
            $table->string('auth_header_name')->default('Ginesys_Api_Key');
            $table->string('url')->nullable();
        });

        $defaultParams = json_encode([
            ['key' => 'intgInvoiceid', 'csv_column' => 'intgInvoiceid', 'source_field' => 'intg_invoice_id', 'required' => true],
            ['key' => 'transactionSiteCode', 'csv_column' => 'transactionSiteCode', 'source_field' => 'transaction_site_code', 'required' => true],
            ['key' => 'voidDate', 'csv_column' => 'voidDate', 'source_field' => 'void_date', 'required' => false],
            ['key' => 'voidRemarks', 'csv_column' => 'voidRemarks', 'source_field' => 'void_remarks', 'required' => false],
        ]);

        $voidPath = '/api/rt/RetailInvoice/Adhoc';

        foreach (DB::table('third_party_apis')->get() as $row) {
            DB::table('third_party_apis')->where('id', $row->id)->update([
                'url' => rtrim($row->base_url, '/').$voidPath,
                'method' => 'DELETE',
                'params' => $defaultParams,
                'auth_header_name' => 'Ginesys_Api_Key',
            ]);
        }

        DB::statement('ALTER TABLE third_party_apis DROP CONSTRAINT IF EXISTS organization_apis_organization_id_type_unique');
        DB::statement('ALTER TABLE third_party_apis DROP CONSTRAINT IF EXISTS third_party_apis_organization_id_type_unique');

        Schema::table('third_party_apis', function (Blueprint $table) {
            $table->dropColumn(['type', 'base_url']);
            $table->renameColumn('api_token', 'auth_token');
        });

        if (Schema::hasColumn('invoice_void_sessions', 'organization_api_id')) {
            Schema::table('invoice_void_sessions', function (Blueprint $table) {
                $table->dropForeign(['organization_api_id']);
                $table->renameColumn('organization_api_id', 'third_party_api_id');
            });

            Schema::table('invoice_void_sessions', function (Blueprint $table) {
                $table->foreign('third_party_api_id')->references('id')->on('third_party_apis')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('third_party_apis') || Schema::hasTable('organization_apis')) {
            return;
        }

        Schema::table('invoice_void_sessions', function (Blueprint $table) {
            $table->dropForeign(['third_party_api_id']);
            $table->renameColumn('third_party_api_id', 'organization_api_id');
        });

        Schema::table('invoice_void_sessions', function (Blueprint $table) {
            $table->foreign('organization_api_id')->references('id')->on('organization_apis')->cascadeOnDelete();
        });

        Schema::table('third_party_apis', function (Blueprint $table) {
            $table->renameColumn('auth_token', 'api_token');
            $table->string('type')->default('ginesys_retail_void');
            $table->string('base_url')->nullable();
        });

        foreach (DB::table('third_party_apis')->get() as $row) {
            $parsed = parse_url($row->url);
            $baseUrl = ($parsed['scheme'] ?? 'https').'://'.($parsed['host'] ?? '');
            if (isset($parsed['port'])) {
                $baseUrl .= ':'.$parsed['port'];
            }
            if (isset($parsed['path'])) {
                $baseUrl .= str_replace('/api/rt/RetailInvoice/Adhoc', '', $parsed['path']);
            }

            DB::table('third_party_apis')->where('id', $row->id)->update([
                'base_url' => rtrim($baseUrl, '/'),
                'type' => 'ginesys_retail_void',
            ]);
        }

        Schema::table('third_party_apis', function (Blueprint $table) {
            $table->dropColumn(['method', 'params', 'auth_header_name', 'url']);
            $table->unique(['organization_id', 'type']);
        });

        Schema::rename('third_party_apis', 'organization_apis');
    }
};
