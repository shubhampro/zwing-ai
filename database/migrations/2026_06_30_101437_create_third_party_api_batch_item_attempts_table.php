<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('third_party_api_batch_item_attempts', function (Blueprint $table) {
            $table->comment('HTTP attempt history for a batch item, including retries');

            $table->id();
            $table->foreignId('third_party_api_batch_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->string('request_method');
            $table->string('request_url');
            $table->json('request_headers');
            $table->json('request_body');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('response_body')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['third_party_api_batch_item_id', 'attempt_number']);
        });

        if (! Schema::hasTable('third_party_api_batch_items')) {
            return;
        }

        foreach (DB::table('third_party_api_batch_items')->whereNotNull('processed_at')->get() as $item) {
            $batch = DB::table('third_party_api_batches')
                ->where('id', $item->third_party_api_batch_id)
                ->first();

            if ($batch === null) {
                continue;
            }

            $connection = DB::table('organization_third_party_apis')
                ->where('id', $batch->organization_third_party_api_id)
                ->first();

            $api = $connection
                ? DB::table('third_party_apis')->where('id', $connection->third_party_api_id)->first()
                : null;

            $baseUrl = $connection?->base_url ?? '';
            $path = $api?->path ?? '';
            $requestUrl = rtrim((string) $baseUrl, '/').'/'.ltrim((string) $path, '/');

            DB::table('third_party_api_batch_item_attempts')->insert([
                'third_party_api_batch_item_id' => $item->id,
                'attempt_number' => 1,
                'request_method' => $api?->method ?? 'POST',
                'request_url' => $requestUrl,
                'request_headers' => json_encode([
                    'Content-Type' => 'application/json',
                    $api?->auth_header_name ?? 'Authorization' => '••••••••',
                ]),
                'request_body' => $item->payload,
                'http_status' => $item->http_status,
                'response_body' => $item->response_body,
                'error_message' => $item->error_message,
                'created_at' => $item->processed_at ?? now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('third_party_api_batch_item_attempts');
    }
};
