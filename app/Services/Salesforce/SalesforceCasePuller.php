<?php

namespace App\Services\Salesforce;

use App\Models\SfAccount;
use App\Models\SfCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SalesforceCasePuller
{
    public const CHUNK_SIZE = 500;

    public const CASE_SOQL = 'SELECT Id, CaseNumber, Subject, Description, Status, Priority, Type, Origin, IsClosed, Is_Spam__c, Product__c, Product_Name__c, Application__c, Module__c, Sub_module__c, Group__c, First_Assigned_Group__c, Owner.Name, Agent__r.Name, AccountId, Requester_Name__c, Tags__c, Size__c, Jira_ID__c, Jira_Status__c, CreatedDate, Resolved_Date_Time__c, ClosedDate, LastModifiedDate FROM Case WHERE Product__c = \'Zwing\' OR Group__c = \'Zwing-Tech\' ORDER BY Id';

    public function __construct(private SalesforceSoqlClient $client) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function fetch(): array
    {
        $accountIdsBySfId = SfAccount::query()->pluck('id', 'sf_id');

        return collect($this->client->query(self::CASE_SOQL))
            ->filter(fn (array $record): bool => filled($record['Id'] ?? null) && filled($record['CaseNumber'] ?? null))
            ->map(fn (array $record): array => $this->mapCase($record, $accountIdsBySfId))
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

        SfCase::upsert($chunk, uniqueBy: ['sf_id'], update: $this->upsertColumns());
    }

    /**
     * @return list<string>
     */
    private function upsertColumns(): array
    {
        return [
            'case_number',
            'subject',
            'description',
            'status',
            'priority',
            'type',
            'origin',
            'is_closed',
            'is_spam',
            'product',
            'product_name',
            'application',
            'module',
            'sub_module',
            'group_name',
            'first_assigned_group',
            'owner_name',
            'agent_name',
            'sf_account_id',
            'requester_name',
            'tags',
            'size',
            'jira_id',
            'jira_status',
            'created_at_sf',
            'resolved_at_sf',
            'closed_at_sf',
            'last_modified_at_sf',
            'synced_at',
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  Collection<string, int|string>  $accountIdsBySfId
     * @return array<string, mixed>
     */
    private function mapCase(array $record, Collection $accountIdsBySfId): array
    {
        $accountSfId = $this->nullableString($record, 'AccountId');
        $sfAccountId = $accountSfId !== null
            ? $accountIdsBySfId->get($accountSfId)
            : null;

        return [
            'sf_id' => (string) $record['Id'],
            'case_number' => (string) $record['CaseNumber'],
            'subject' => (string) ($record['Subject'] ?? ''),
            'description' => $this->nullableString($record, 'Description'),
            'status' => (string) ($record['Status'] ?? ''),
            'priority' => $this->nullableString($record, 'Priority'),
            'type' => $this->nullableString($record, 'Type'),
            'origin' => $this->nullableString($record, 'Origin'),
            'is_closed' => (bool) ($record['IsClosed'] ?? false),
            'is_spam' => (bool) ($record['Is_Spam__c'] ?? false),
            'product' => (string) ($record['Product__c'] ?? 'Zwing'),
            'product_name' => $this->nullableString($record, 'Product_Name__c'),
            'application' => $this->nullableString($record, 'Application__c'),
            'module' => $this->nullableString($record, 'Module__c'),
            'sub_module' => $this->nullableString($record, 'Sub_module__c'),
            'group_name' => $this->nullableString($record, 'Group__c'),
            'first_assigned_group' => $this->nullableString($record, 'First_Assigned_Group__c'),
            'owner_name' => $this->relatedName($record, 'Owner'),
            'agent_name' => $this->relatedName($record, 'Agent__r'),
            'sf_account_id' => $sfAccountId !== null ? (int) $sfAccountId : null,
            'requester_name' => $this->nullableString($record, 'Requester_Name__c'),
            'tags' => $this->nullableString($record, 'Tags__c'),
            'size' => $this->nullableString($record, 'Size__c'),
            'jira_id' => $this->nullableString($record, 'Jira_ID__c'),
            'jira_status' => $this->nullableString($record, 'Jira_Status__c'),
            'created_at_sf' => $this->timestamp($record['CreatedDate'] ?? null) ?? now()->utc()->toDateTimeString(),
            'resolved_at_sf' => $this->timestamp($record['Resolved_Date_Time__c'] ?? null),
            'closed_at_sf' => $this->timestamp($record['ClosedDate'] ?? null),
            'last_modified_at_sf' => $this->timestamp($record['LastModifiedDate'] ?? null),
            'synced_at' => now()->utc()->toDateTimeString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function nullableString(array $record, string $key): ?string
    {
        $value = $record[$key] ?? null;

        return filled($value) ? (string) $value : null;
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

        return $this->nullableString($related, 'Name');
    }

    private function timestamp(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        return Carbon::parse((string) $value)->utc()->toDateTimeString();
    }
}
