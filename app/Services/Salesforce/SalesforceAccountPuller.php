<?php

namespace App\Services\Salesforce;

use App\Models\SfAccount;
use Illuminate\Support\Carbon;

class SalesforceAccountPuller
{
    public const ACCOUNT_SOQL = 'SELECT Id, Name, LastModifiedDate FROM Account WHERE Id IN (SELECT AccountId FROM Case WHERE AccountId != null) ORDER BY Id';

    private const CHUNK_SIZE = 500;

    public function __construct(private SalesforceSoqlClient $client) {}

    public function pull(bool $dryRun = false): int
    {
        $rows = collect($this->client->query(self::ACCOUNT_SOQL))
            ->filter(fn (array $record): bool => filled($record['Id'] ?? null))
            ->map(fn (array $record): array => $this->mapAccount($record))
            ->values()
            ->all();

        if ($dryRun || $rows === []) {
            return count($rows);
        }

        foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
            SfAccount::upsert(
                $chunk,
                uniqueBy: ['sf_id'],
                update: ['name', 'last_modified_at_sf', 'synced_at'],
            );
        }

        return count($rows);
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array{sf_id: string, name: string, last_modified_at_sf: string|null, synced_at: string}
     */
    private function mapAccount(array $record): array
    {
        $lastModified = $record['LastModifiedDate'] ?? null;

        return [
            'sf_id' => (string) $record['Id'],
            'name' => (string) ($record['Name'] ?? ''),
            'last_modified_at_sf' => filled($lastModified)
                ? Carbon::parse((string) $lastModified)->utc()->toDateTimeString()
                : null,
            'synced_at' => now()->utc()->toDateTimeString(),
        ];
    }
}
