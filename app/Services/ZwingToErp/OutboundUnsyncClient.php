<?php

namespace App\Services\ZwingToErp;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OutboundUnsyncClient
{
    /**
     * @return array{
     *     success: bool,
     *     result: array<int, array<string, mixed>>,
     *     stats: array<string, int|float>
     * }
     */
    public function fetch(
        int $vendorId,
        string $partnerCode,
        string $startDate,
        string $endDate,
        ?string $eventName = null,
    ): array {
        $baseUrl = rtrim((string) config('services.zwing_to_erp.base_url'), '/');
        $username = (string) config('services.zwing_to_erp.username');
        $password = (string) config('services.zwing_to_erp.password');

        if ($baseUrl === '' || $username === '' || $password === '') {
            throw new RuntimeException('ZWING To ERP API is not configured. Set ZWING_TO_ERP_BASE_URL, ZWING_TO_ERP_USERNAME, and ZWING_TO_ERP_PASSWORD in .env.');
        }

        $payload = [
            'v_id' => $vendorId,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'partner_code' => $partnerCode,
        ];

        if ($eventName !== null && $eventName !== '') {
            $payload['event_name'] = $eventName;
        }

        try {
            $response = Http::withBasicAuth($username, $password)
                ->timeout(60)
                ->acceptJson()
                ->post("{$baseUrl}/log/unsynclist", $payload)
                ->throw();
        } catch (RequestException $exception) {
            $message = $exception->response?->json('message')
                ?? $exception->response?->body()
                ?? $exception->getMessage();

            throw new RuntimeException("ZWING To ERP API request failed: {$message}", previous: $exception);
        }

        /** @var array{success?: bool, result?: array<int, array<string, mixed>>, stats?: array<string, int|float>} $body */
        $body = $response->json();

        if (($body['success'] ?? false) !== true) {
            throw new RuntimeException('ZWING To ERP API returned an unsuccessful response.');
        }

        return [
            'success' => true,
            'result' => $body['result'] ?? [],
            'stats' => $body['stats'] ?? [],
        ];
    }
}
