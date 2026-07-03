<?php

namespace App\Services\ThirdParty;

use App\Models\OrganizationThirdPartyApi;
use App\Models\ThirdPartyApiBatchItem;
use App\Models\ThirdPartyApiBatchItemAttempt;
use Illuminate\Support\Str;
use Throwable;

class ProcessThirdPartyApiBatchItem
{
    public function process(ThirdPartyApiBatchItem $item, OrganizationThirdPartyApi $connection): void
    {
        $connection->loadMissing('thirdPartyApi');
        $api = $connection->thirdPartyApi;
        $payload = $item->payload ?? [];
        $responseLimit = (int) config('third_party.response_body_limit');
        $attemptNumber = ((int) $item->attempts()->max('attempt_number')) + 1;

        $requestHeaders = [
            'Content-Type' => 'application/json',
            $api->auth_header_name => '••••••••',
        ];

        try {
            $response = ThirdPartyApiClient::forConnection($connection)->send($payload);
            $body = Str::limit($response->body(), $responseLimit, '');

            ThirdPartyApiBatchItemAttempt::create([
                'third_party_api_batch_item_id' => $item->id,
                'attempt_number' => $attemptNumber,
                'request_method' => $api->method->value,
                'request_url' => $connection->endpointUrl(),
                'request_headers' => $requestHeaders,
                'request_body' => $payload,
                'http_status' => $response->status(),
                'response_body' => $body,
                'error_message' => $response->successful() ? null : "HTTP {$response->status()}",
                'created_at' => now(),
            ]);

            if ($response->successful()) {
                $item->update([
                    'status' => 'success',
                    'http_status' => $response->status(),
                    'response_body' => $body,
                    'error_message' => null,
                    'processed_at' => now(),
                ]);

                return;
            }

            $item->update([
                'status' => 'failed',
                'http_status' => $response->status(),
                'response_body' => $body,
                'error_message' => "HTTP {$response->status()}",
                'processed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            ThirdPartyApiBatchItemAttempt::create([
                'third_party_api_batch_item_id' => $item->id,
                'attempt_number' => $attemptNumber,
                'request_method' => $api->method->value,
                'request_url' => $connection->endpointUrl(),
                'request_headers' => $requestHeaders,
                'request_body' => $payload,
                'http_status' => null,
                'response_body' => null,
                'error_message' => Str::limit($exception->getMessage(), 255, ''),
                'created_at' => now(),
            ]);

            $item->update([
                'status' => 'failed',
                'http_status' => null,
                'response_body' => null,
                'error_message' => Str::limit($exception->getMessage(), 255, ''),
                'processed_at' => now(),
            ]);
        }
    }
}
