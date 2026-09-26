<?php

namespace App\Services\Salesforce;

use App\Exceptions\SalesforceQueryException;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use JsonException;

class SalesforceCliSoqlClient implements SalesforceSoqlClient
{
    public function query(string $soql): array
    {
        $result = Process::timeout((int) config('salesforce.query_timeout'))
            ->run([
                (string) config('salesforce.cli'),
                'data',
                'query',
                '--target-org',
                (string) config('salesforce.org'),
                '--query',
                $soql,
                '--json',
            ]);

        $payload = $this->decodePayload($result, 'Salesforce query failed');

        /** @var array<string, mixed> $queryResult */
        $queryResult = $payload['result'] ?? [];

        if (($queryResult['done'] ?? false) !== true) {
            throw new SalesforceQueryException('Salesforce query result is incomplete. Use bulk export for large sets.');
        }

        /** @var list<array<string, mixed>> $records */
        $records = $queryResult['records'] ?? [];

        return $records;
    }

    public function describe(string $sobject): array
    {
        $result = Process::timeout((int) config('salesforce.query_timeout'))
            ->run([
                (string) config('salesforce.cli'),
                'sobject',
                'describe',
                '--target-org',
                (string) config('salesforce.org'),
                '--sobject',
                $sobject,
                '--json',
            ]);

        $payload = $this->decodePayload($result, 'Salesforce describe failed');

        /** @var array<string, mixed> $describeResult */
        $describeResult = $payload['result'] ?? [];

        return $describeResult;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(ProcessResult $result, string $failurePrefix): array
    {
        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new SalesforceQueryException(
                'Salesforce CLI returned invalid JSON: '.($result->errorOutput() ?: $result->output()),
                previous: $exception,
            );
        }

        if (! $result->successful() || ($payload['status'] ?? 1) !== 0) {
            $message = $payload['message'] ?? $result->errorOutput() ?: $result->output();

            throw new SalesforceQueryException($failurePrefix.': '.$message);
        }

        return $payload;
    }
}
