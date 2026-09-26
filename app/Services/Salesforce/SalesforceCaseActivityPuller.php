<?php

namespace App\Services\Salesforce;

use App\Models\SfCase;
use App\Models\SfCaseActivity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SalesforceCaseActivityPuller
{
    public const CASE_ID_CHUNK_SIZE = 80;

    public const PERSIST_CHUNK_SIZE = 500;

    public function __construct(private SalesforceSoqlClient $client) {}

    public function pull(bool $dryRun = false): int
    {
        $caseIdsBySfId = SfCase::query()->pluck('id', 'sf_id');

        if ($caseIdsBySfId->isEmpty()) {
            return 0;
        }

        $count = 0;

        foreach ($caseIdsBySfId->chunk(self::CASE_ID_CHUNK_SIZE) as $chunk) {
            $rows = $this->fetchChunk($chunk);
            $count += count($rows);

            if ($dryRun || $rows === []) {
                continue;
            }

            foreach (array_chunk($rows, self::PERSIST_CHUNK_SIZE) as $persistChunk) {
                $this->persistChunk($persistChunk);
            }
        }

        return $count;
    }

    /**
     * @param  Collection<string, int|string>  $caseIdsBySfId
     * @return list<array<string, mixed>>
     */
    public function fetchChunk(Collection $caseIdsBySfId): array
    {
        if ($caseIdsBySfId->isEmpty()) {
            return [];
        }

        $caseSfIds = $caseIdsBySfId->keys()->all();

        return collect()
            ->concat($this->mapRecords(
                $this->client->query($this->commentSoqlForCaseIds($caseSfIds)),
                $caseIdsBySfId,
                SfCaseActivity::SOURCE_COMMENT,
            ))
            ->concat($this->mapRecords(
                $this->client->query($this->feedSoqlForCaseIds($caseSfIds)),
                $caseIdsBySfId,
                SfCaseActivity::SOURCE_FEED,
            ))
            ->concat($this->mapRecords(
                $this->client->query($this->emailSoqlForCaseIds($caseSfIds)),
                $caseIdsBySfId,
                SfCaseActivity::SOURCE_EMAIL,
            ))
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

        SfCaseActivity::upsert(
            $chunk,
            uniqueBy: ['sf_id'],
            update: ['sf_case_id', 'source', 'type', 'subject', 'body', 'author_name', 'is_incoming', 'occurred_at', 'synced_at'],
        );
    }

    /**
     * @param  list<string>  $caseSfIds
     */
    public function commentSoqlForCaseIds(array $caseSfIds): string
    {
        return 'SELECT Id, ParentId, CommentBody, IsPublished, CreatedDate, CreatedBy.Name FROM CaseComment WHERE ParentId IN ('.$this->quotedIds($caseSfIds).') ORDER BY Id';
    }

    /**
     * @param  list<string>  $caseSfIds
     */
    public function feedSoqlForCaseIds(array $caseSfIds): string
    {
        return 'SELECT Id, ParentId, Type, Title, Body, CreatedDate, CreatedBy.Name FROM CaseFeed WHERE ParentId IN ('.$this->quotedIds($caseSfIds).') AND Type != \'TrackedChange\' ORDER BY Id';
    }

    /**
     * @param  list<string>  $caseSfIds
     */
    public function emailSoqlForCaseIds(array $caseSfIds): string
    {
        return 'SELECT Id, ParentId, Subject, TextBody, FromAddress, Incoming, MessageDate, CreatedDate, CreatedBy.Name FROM EmailMessage WHERE ParentId IN ('.$this->quotedIds($caseSfIds).') ORDER BY Id';
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  Collection<string, int|string>  $caseIdsBySfId
     * @return Collection<int, array<string, mixed>>
     */
    private function mapRecords(array $records, Collection $caseIdsBySfId, string $source): Collection
    {
        return collect($records)
            ->filter(fn (array $record): bool => filled($record['Id'] ?? null) && filled($record['ParentId'] ?? null))
            ->map(fn (array $record): array => $this->mapActivity($record, $caseIdsBySfId, $source))
            ->filter(fn (array $row): bool => $row['sf_case_id'] !== null)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  Collection<string, int|string>  $caseIdsBySfId
     * @return array<string, mixed>
     */
    private function mapActivity(array $record, Collection $caseIdsBySfId, string $source): array
    {
        $localCaseId = $caseIdsBySfId->get((string) $record['ParentId']);

        return [
            'sf_id' => (string) $record['Id'],
            'sf_case_id' => $localCaseId !== null ? (int) $localCaseId : null,
            'source' => $source,
            'type' => $this->typeFor($record, $source),
            'subject' => $this->nullableString($record['Title'] ?? $record['Subject'] ?? null, 255),
            'body' => $this->nullableString($record['CommentBody'] ?? $record['TextBody'] ?? $record['Body'] ?? null),
            'author_name' => $this->authorName($record),
            'is_incoming' => $this->boolean($record['Incoming'] ?? false),
            'occurred_at' => $this->timestamp($record['MessageDate'] ?? $record['CreatedDate'] ?? null) ?? now()->utc()->toDateTimeString(),
            'synced_at' => now()->utc()->toDateTimeString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function typeFor(array $record, string $source): ?string
    {
        return match ($source) {
            SfCaseActivity::SOURCE_COMMENT => $this->boolean($record['IsPublished'] ?? false) ? 'Public' : 'Internal',
            SfCaseActivity::SOURCE_FEED => $this->nullableString($record['Type'] ?? null, 255),
            SfCaseActivity::SOURCE_EMAIL => $this->boolean($record['Incoming'] ?? false) ? 'Incoming' : 'Outgoing',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function authorName(array $record): ?string
    {
        $fromAddress = $this->nullableString($record['FromAddress'] ?? null, 255);

        if (filled($fromAddress)) {
            return $fromAddress;
        }

        return $this->relatedName($record, 'CreatedBy');
    }

    /**
     * @param  list<string>  $caseSfIds
     */
    private function quotedIds(array $caseSfIds): string
    {
        return collect($caseSfIds)
            ->filter()
            ->map(fn (string $id): string => "'".addslashes($id)."'")
            ->implode(',');
    }

    private function nullableString(mixed $value, ?int $limit = null): ?string
    {
        if (is_array($value)) {
            $value = $value['Name'] ?? $value['value'] ?? null;
        }

        if (! filled($value)) {
            return null;
        }

        $string = (string) $value;

        return $limit === null ? $string : mb_substr($string, 0, $limit);
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

    private function boolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function timestamp(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        return Carbon::parse((string) $value)->utc()->toDateTimeString();
    }
}
