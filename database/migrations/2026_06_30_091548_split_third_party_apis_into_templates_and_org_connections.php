<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('organization_third_party_apis')) {
            return;
        }

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

        if (! Schema::hasColumn('third_party_apis', 'organization_id')) {
            return;
        }

        Schema::table('third_party_apis', function (Blueprint $table) {
            if (! Schema::hasColumn('third_party_apis', 'path')) {
                $table->string('path')->nullable()->after('name');
            }
        });

        $connectionMap = [];

        foreach (DB::table('third_party_apis')->get() as $api) {
            [$baseUrl, $path] = $this->splitUrl((string) $api->url);

            DB::table('third_party_apis')->where('id', $api->id)->update([
                'path' => $path,
            ]);

            $connectionId = DB::table('organization_third_party_apis')->insertGetId([
                'organization_id' => $api->organization_id,
                'third_party_api_id' => $api->id,
                'base_url' => $baseUrl,
                'auth_token' => $api->auth_token,
                'is_active' => $api->is_active,
                'created_at' => $api->created_at,
                'updated_at' => $api->updated_at,
            ]);

            $connectionMap[$api->id] = $connectionId;
        }

        DB::statement('ALTER TABLE third_party_apis DROP CONSTRAINT IF EXISTS organization_apis_organization_id_foreign');
        DB::statement('ALTER TABLE third_party_apis DROP CONSTRAINT IF EXISTS third_party_apis_organization_id_foreign');

        Schema::table('third_party_apis', function (Blueprint $table) {
            if (Schema::hasColumn('third_party_apis', 'organization_id')) {
                $table->dropColumn('organization_id');
            }
            if (Schema::hasColumn('third_party_apis', 'url')) {
                $table->dropColumn('url');
            }
            if (Schema::hasColumn('third_party_apis', 'auth_token')) {
                $table->dropColumn('auth_token');
            }
        });

        if (Schema::hasColumn('third_party_api_batches', 'third_party_api_id')) {
            if (! Schema::hasColumn('third_party_api_batches', 'organization_third_party_api_id')) {
                Schema::table('third_party_api_batches', function (Blueprint $table) {
                    $table->foreignId('organization_third_party_api_id')->nullable()->after('user_id');
                });
            }

            foreach (DB::table('third_party_api_batches')->get() as $batch) {
                $connectionId = $connectionMap[$batch->third_party_api_id] ?? null;

                if ($connectionId !== null) {
                    DB::table('third_party_api_batches')
                        ->where('id', $batch->id)
                        ->update(['organization_third_party_api_id' => $connectionId]);
                }
            }

            DB::statement('ALTER TABLE third_party_api_batches DROP CONSTRAINT IF EXISTS third_party_api_batches_third_party_api_id_foreign');
            DB::statement('ALTER TABLE third_party_api_batches DROP CONSTRAINT IF EXISTS invoice_void_sessions_third_party_api_id_foreign');

            Schema::table('third_party_api_batches', function (Blueprint $table) {
                if (Schema::hasColumn('third_party_api_batches', 'third_party_api_id')) {
                    $table->dropColumn('third_party_api_id');
                }
            });

            DB::statement('ALTER TABLE third_party_api_batches ALTER COLUMN organization_third_party_api_id SET NOT NULL');
            DB::statement('ALTER TABLE third_party_api_batches ADD CONSTRAINT third_party_api_batches_organization_third_party_api_id_foreign FOREIGN KEY (organization_third_party_api_id) REFERENCES organization_third_party_apis(id) ON DELETE CASCADE');
        }
    }

    public function down(): void
    {
        // Irreversible split.
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitUrl(string $url): array
    {
        $parsed = parse_url($url);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';
        $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';
        $path = $parsed['path'] ?? '/';
        $query = isset($parsed['query']) ? '?'.$parsed['query'] : '';

        return [
            rtrim("{$scheme}://{$host}{$port}", '/'),
            $path.$query,
        ];
    }
};
