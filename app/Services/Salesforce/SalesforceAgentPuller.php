<?php

namespace App\Services\Salesforce;

use App\Models\SfAgent;

class SalesforceAgentPuller
{
    public const AGENT_SOQL = 'SELECT Id, Name, IsActive FROM User WHERE Id IN (SELECT Agent__c FROM Case WHERE Agent__c != null AND CreatedDate >= '.SalesforceCasePuller::CREATED_SINCE.') ORDER BY Id';

    private const CHUNK_SIZE = 500;

    public function __construct(private SalesforceSoqlClient $client) {}

    public function pull(bool $dryRun = false): int
    {
        $rows = collect($this->client->query(self::AGENT_SOQL))
            ->filter(fn (array $record): bool => filled($record['Id'] ?? null))
            ->map(fn (array $record): array => $this->mapAgent($record))
            ->values()
            ->all();

        if ($dryRun || $rows === []) {
            return count($rows);
        }

        foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
            SfAgent::upsert(
                $chunk,
                uniqueBy: ['sf_id'],
                update: ['name', 'is_active', 'synced_at'],
            );
        }

        return count($rows);
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array{sf_id: string, name: string, is_active: bool, synced_at: string}
     */
    private function mapAgent(array $record): array
    {
        return [
            'sf_id' => (string) $record['Id'],
            'name' => (string) ($record['Name'] ?? ''),
            'is_active' => (bool) ($record['IsActive'] ?? true),
            'synced_at' => now()->utc()->toDateTimeString(),
        ];
    }
}
