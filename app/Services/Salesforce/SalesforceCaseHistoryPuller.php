<?php

namespace App\Services\Salesforce;

use App\Models\SfCaseGroupHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SalesforceCaseHistoryPuller
{
    public const CASE_ID_CHUNK_SIZE = 80;

    public const PERSIST_CHUNK_SIZE = 500;

    public function __construct(private SalesforceSoqlClient $client) {}

    /**
     * @param  Collection<string, int|string>  $caseIdsBySfId
     * @return list<array<string, mixed>>
     */
    public function fetchChunk(Collection $caseIdsBySfId): array
    {
        if ($caseIdsBySfId->isEmpty()) {
            return [];
        }

        return collect($this->client->query($this->soqlForCaseIds($caseIdsBySfId->keys()->all())))
            ->filter(fn (array $record): bool => filled($record['Id'] ?? null) && filled($record['CaseId'] ?? null))
            ->map(fn (array $record): array => $this->mapHistory($record, $caseIdsBySfId))
            ->filter(fn (array $row): bool => $row['sf_case_id'] !== null)
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $chunk
     */
    public function persistChunk(array $chunk): void
    {
        if ($chunk === []) {
            return;
        }

        SfCaseGroupHistory::upsert(
            $chunk,
            uniqueBy: ['sf_id'],
            update: ['sf_case_id', 'field', 'old_value', 'new_value', 'changed_by', 'changed_at', 'synced_at'],
        );
    }

    /**
     * @param  list<string>  $caseSfIds
     */
    public function soqlForCaseIds(array $caseSfIds): string
    {
        $quotedIds = collect($caseSfIds)
            ->filter()
            ->map(fn (string $id): string => "'".addslashes($id)."'")
            ->implode(',');

        return 'SELECT Id, CaseId, Field, OldValue, NewValue, CreatedDate, CreatedBy.Name FROM CaseHistory WHERE Field IN (\'Group__c\', \'Owner\') AND CaseId IN ('.$quotedIds.') ORDER BY Id';
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  Collection<string, int|string>  $caseIdsBySfId
     * @return array<string, mixed>
     */
    private function mapHistory(array $record, Collection $caseIdsBySfId): array
    {
        $caseSfId = (string) $record['CaseId'];
        $localCaseId = $caseIdsBySfId->get($caseSfId);

        return [
            'sf_id' => (string) $record['Id'],
            'sf_case_id' => $localCaseId !== null ? (int) $localCaseId : null,
            'field' => (string) ($record['Field'] ?? ''),
            'old_value' => $this->historyValue($record['OldValue'] ?? null),
            'new_value' => $this->historyValue($record['NewValue'] ?? null),
            'changed_by' => $this->relatedName($record, 'CreatedBy'),
            'changed_at' => $this->timestamp($record['CreatedDate'] ?? null) ?? now()->utc()->toDateTimeString(),
            'synced_at' => now()->utc()->toDateTimeString(),
        ];
    }

    private function historyValue(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value['Name'] ?? $value['value'] ?? null;
        }

        return filled($value) ? mb_substr((string) $value, 0, 255) : null;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function relatedName(array $record, string $key): ?string
    {
        $related = $record[$key] ?? null;

        if (! is_array($related)) {
            return null;
        }

        $name = $related['Name'] ?? null;

        return filled($name) ? (string) $name : null;
    }

    private function timestamp(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        return Carbon::parse((string) $value)->utc()->toDateTimeString();
    }
}
