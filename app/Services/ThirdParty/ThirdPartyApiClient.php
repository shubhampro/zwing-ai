<?php

namespace App\Services\ThirdParty;

use App\Models\OrganizationThirdPartyApi;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class ThirdPartyApiClient
{
    public function __construct(
        private readonly OrganizationThirdPartyApi $connection,
    ) {
        $this->connection->loadMissing('thirdPartyApi');

        if (! $this->connection->isConfigured()) {
            throw new InvalidArgumentException('Organization API connection credentials are incomplete.');
        }
    }

    public static function forConnection(OrganizationThirdPartyApi $connection): self
    {
        return new self($connection);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function send(array $payload): Response
    {
        $api = $this->connection->thirdPartyApi;

        $timeout = max(1, (int) config('third_party.http_timeout_seconds'));

        return Http::timeout($timeout)
            ->connectTimeout(min(10, $timeout))
            ->withHeaders([
                'Content-Type' => 'application/json',
                $api->auth_header_name => $this->connection->auth_token,
            ])->send($api->method->value, $this->connection->endpointUrl(), [
                'json' => $payload,
            ]);
    }
}
