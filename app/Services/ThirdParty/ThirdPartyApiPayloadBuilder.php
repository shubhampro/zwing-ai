<?php

namespace App\Services\ThirdParty;

use App\Models\ThirdPartyApi;

class ThirdPartyApiPayloadBuilder
{
    public function __construct(
        private readonly ThirdPartyApi $api,
    ) {}

    /**
     * @param  array<string, string>  $normalizedRecord
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>|null
     */
    public function fromCsvRow(array $normalizedRecord, array $defaults = []): ?array
    {
        $payload = [];

        foreach ($this->api->params ?? [] as $param) {
            $key = $param['key'] ?? null;

            if (! is_string($key) || $key === '') {
                continue;
            }

            $csvColumn = strtolower((string) ($param['csv_column'] ?? $key));
            $value = trim((string) ($normalizedRecord[$csvColumn] ?? ''));

            if ($value === '' && array_key_exists($key, $defaults)) {
                $value = trim((string) $defaults[$key]);
            }

            if ($value === '' && array_key_exists('default', $param)) {
                $value = trim((string) $param['default']);
            }

            if (($param['required'] ?? false) && $value === '') {
                return null;
            }

            if ($value !== '') {
                $payload[$key] = $value;
            }
        }

        return $payload === [] ? null : $payload;
    }

    /**
     * @return list<string>
     */
    public function requiredCsvColumns(): array
    {
        return collect($this->api->params ?? [])
            ->filter(fn (array $param) => (bool) ($param['required'] ?? false))
            ->map(fn (array $param) => strtolower((string) ($param['csv_column'] ?? $param['key'] ?? '')))
            ->filter()
            ->values()
            ->all();
    }
}
