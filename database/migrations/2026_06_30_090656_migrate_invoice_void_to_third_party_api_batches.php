<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoice_void_sessions')) {
            return;
        }

        if (! Schema::hasTable('third_party_api_batches')) {
            Schema::create('third_party_api_batches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('third_party_api_id')->constrained()->cascadeOnDelete();
                $table->string('name')->unique();
                $table->string('file_name')->nullable();
                $table->unsignedInteger('row_count')->nullable();
                $table->unsignedInteger('processed_count')->default(0);
                $table->unsignedInteger('success_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->unsignedInteger('skipped_count')->default(0);
                $table->json('defaults')->default('{}');
                $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('third_party_api_batch_items')) {
            Schema::create('third_party_api_batch_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('third_party_api_batch_id')->constrained()->cascadeOnDelete();
                $table->json('payload');
                $table->enum('status', ['pending', 'success', 'failed', 'skipped'])->default('pending');
                $table->unsignedSmallInteger('http_status')->nullable();
                $table->text('response_body')->nullable();
                $table->string('error_message')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->index(['third_party_api_batch_id', 'status']);
            });
        }

        foreach (DB::table('invoice_void_sessions')->get() as $session) {
            $defaults = array_filter([
                'voidDate' => $session->default_void_date,
                'voidRemarks' => $session->default_void_remarks,
            ], fn ($value) => $value !== null && $value !== '');

            $batchId = DB::table('third_party_api_batches')->insertGetId([
                'user_id' => $session->user_id,
                'third_party_api_id' => $session->third_party_api_id,
                'name' => $session->name,
                'file_name' => $session->file_name,
                'row_count' => $session->row_count,
                'processed_count' => $session->processed_count,
                'success_count' => $session->success_count,
                'failed_count' => $session->failed_count,
                'skipped_count' => $session->skipped_count,
                'defaults' => json_encode($defaults),
                'status' => $session->status,
                'completed_at' => $session->completed_at,
                'created_at' => $session->created_at,
                'updated_at' => $session->updated_at,
            ]);

            $items = DB::table('invoice_void_items')
                ->where('invoice_void_session_id', $session->id)
                ->get();

            foreach ($items as $item) {
                DB::table('third_party_api_batch_items')->insert([
                    'third_party_api_batch_id' => $batchId,
                    'payload' => json_encode(array_filter([
                        'intgInvoiceid' => $item->intg_invoice_id,
                        'transactionSiteCode' => $item->transaction_site_code,
                        'voidDate' => $item->void_date,
                        'voidRemarks' => $item->void_remarks,
                    ])),
                    'status' => $item->status,
                    'http_status' => $item->http_status,
                    'response_body' => $item->response_body,
                    'error_message' => $item->error_message,
                    'processed_at' => $item->processed_at,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ]);
            }
        }

        Schema::dropIfExists('invoice_void_items');
        Schema::dropIfExists('invoice_void_sessions');
    }

    public function down(): void
    {
        // Irreversible data shape change.
    }
};
