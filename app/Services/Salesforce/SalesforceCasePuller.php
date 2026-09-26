<?php

namespace App\Services\Salesforce;

use App\Models\SfAccount;
use App\Models\SfAgent;
use App\Models\SfApplication;
use App\Models\SfCase;
use App\Models\SfGroup;
use App\Models\SfModule;
use App\Models\SfProduct;
use App\Models\SfSubModule;
use App\Models\SfType;
use App\Support\IndiaDateTime;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class SalesforceCasePuller
{
    public const CHUNK_SIZE = 500;

    public const CREATED_SINCE = '2026-03-01T00:00:00+05:30';

    public const CASE_SELECT = 'SELECT Id, CaseNumber, Subject, Description, Status, Priority, Type, Origin, IsClosed, Is_Spam__c, Product__c, Product_Name__c, Application__c, Module__c, Sub_module__c, Group__c, First_Assigned_Group__c, Owner.Name, Agent__c, Agent__r.Name, AccountId, Requester_Name__c, Tags__c, Size__c, Jira_ID__c, Jira_Status__c, CreatedDate, Resolved_Date_Time__c, ClosedDate, LastModifiedDate FROM Case';

    public function __construct(private SalesforceSoqlClient $client) {}

    public function pull(bool $dryRun = false, ?string $from = null, ?string $to = null): int
    {
        $rows = $this->fetch($from, $to);

        if ($dryRun || $rows === []) {
            return count($rows);
        }

        foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
            $this->persistChunk($chunk);
        }

        return count($rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetch(?string $from = null, ?string $to = null): array
    {
        return $this->mapRecords($this->queryWindows($from, $to));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function queryWindow(string $start, string $end): array
    {
        return $this->client->query($this->soqlForWindow($start, $end));
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return list<array<string, mixed>>
     */
    public function mapRecords(array $records): array
    {
        $lookups = $this->lookups();

        return collect($records)
            ->unique(fn (array $record): string => (string) ($record['Id'] ?? ''))
            ->filter(fn (array $record): bool => filled($record['Id'] ?? null) && filled($record['CaseNumber'] ?? null))
            ->map(fn (array $record): array => $this->mapCase($record, $lookups))
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
    public function soqlWindows(?string $from = null, ?string $to = null): array
    {
        return collect($this->windows($from, $to))
            ->map(fn (array $window): string => $this->soqlForWindow($window[0], $window[1]))
            ->values()
            ->all();
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public function windows(?string $from = null, ?string $to = null): array
    {
        return $this->createdDateWindows($from, $to);
    }

    /**
     * Inclusive IST start and exclusive IST end.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function range(?string $from = null, ?string $to = null): array
    {
        $start = $this->parseInclusiveDay($from, Carbon::parse(self::CREATED_SINCE)->timezone(IndiaDateTime::TIMEZONE));
        $endInclusive = $this->parseInclusiveDay($to, Carbon::now(IndiaDateTime::TIMEZONE));

        if ($start->gt($endInclusive)) {
            throw new InvalidArgumentException('The --from date must be on or before --to.');
        }

        return [$start, $endInclusive->copy()->addDay()->startOfDay()];
    }

    /**
     * @return list<string>
     */
    private function queryWindows(?string $from = null, ?string $to = null): array
    {
        $records = [];

        foreach ($this->soqlWindows($from, $to) as $soql) {
            $records = [...$records, ...$this->client->query($soql)];
        }

        return $records;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function createdDateWindows(?string $from = null, ?string $to = null): array
    {
        [$start, $end] = $this->range($from, $to);
        $windows = [];
        $cursor = $start->copy();

        while ($cursor->lt($end)) {
            $next = $cursor->copy()->addWeek();

            if ($next->gt($end)) {
                $next = $end->copy();
            }

            $windows[] = [$cursor->format('Y-m-d\TH:i:sP'), $next->format('Y-m-d\TH:i:sP')];
            $cursor = $next;
        }

        return $windows;
    }

    private function parseInclusiveDay(?string $value, Carbon $default): Carbon
    {
        if (! filled($value)) {
            return $default->copy()->timezone(IndiaDateTime::TIMEZONE)->startOfDay();
        }

        $value = trim($value);

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $matches) !== 1) {
            throw new InvalidArgumentException("Invalid date [{$value}]. Use Y-m-d (IST).");
        }

        try {
            $date = Carbon::createFromDate(
                (int) $matches[1],
                (int) $matches[2],
                (int) $matches[3],
                IndiaDateTime::TIMEZONE,
            );
        } catch (InvalidFormatException) {
            throw new InvalidArgumentException("Invalid date [{$value}]. Use Y-m-d (IST).");
        }

        if (
            $date->year !== (int) $matches[1]
            || $date->month !== (int) $matches[2]
            || $date->day !== (int) $matches[3]
        ) {
            throw new InvalidArgumentException("Invalid date [{$value}]. Use Y-m-d (IST).");
        }

        return $date->startOfDay();
    }

    private function soqlForWindow(string $start, string $end): string
    {
        return self::CASE_SELECT
            ." WHERE CreatedDate >= {$start} AND CreatedDate < {$end}"
            .' ORDER BY Id';
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
            'sf_type_id',
            'origin',
            'is_closed',
            'is_spam',
            'product',
            'sf_product_id',
            'product_name',
            'application',
            'sf_application_id',
            'module',
            'sf_module_id',
            'sub_module',
            'sf_sub_module_id',
            'group_name',
            'sf_group_id',
            'first_assigned_group',
            'sf_first_assigned_group_id',
            'owner_name',
            'agent_name',
            'sf_agent_id',
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
     * @return array{
     *     accounts: Collection<string, int|string>,
     *     products: Collection<string, int|string>,
     *     applications: Collection<string, int|string>,
     *     modules: Collection<string, int|string>,
     *     subModules: Collection<string, int|string>,
     *     types: Collection<string, int|string>,
     *     groups: Collection<string, int|string>,
     *     agents: Collection<string, int|string>
     * }
     */
    private function lookups(): array
    {
        return [
            'accounts' => SfAccount::query()->pluck('id', 'sf_id'),
            'products' => SfProduct::query()->pluck('id', 'name'),
            'applications' => SfApplication::query()->pluck('id', 'name'),
            'modules' => SfModule::query()->pluck('id', 'name'),
            'subModules' => SfSubModule::query()->pluck('id', 'name'),
            'types' => SfType::query()->pluck('id', 'name'),
            'groups' => SfGroup::query()->pluck('id', 'name'),
            'agents' => SfAgent::query()->pluck('id', 'sf_id'),
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array{
     *     accounts: Collection<string, int|string>,
     *     products: Collection<string, int|string>,
     *     applications: Collection<string, int|string>,
     *     modules: Collection<string, int|string>,
     *     subModules: Collection<string, int|string>,
     *     types: Collection<string, int|string>,
     *     groups: Collection<string, int|string>,
     *     agents: Collection<string, int|string>
     * }  $lookups
     * @return array<string, mixed>
     */
    private function mapCase(array $record, array $lookups): array
    {
        $accountSfId = $this->nullableString($record, 'AccountId');
        $agentSfId = $this->nullableString($record, 'Agent__c');
        $product = $this->nullableString($record, 'Product__c') ?? 'No Product';
        $application = $this->nullableString($record, 'Application__c');
        $module = $this->nullableString($record, 'Module__c');
        $subModule = $this->nullableString($record, 'Sub_module__c');
        $type = $this->nullableString($record, 'Type');
        $groupName = $this->nullableString($record, 'Group__c');
        $firstGroup = $this->nullableString($record, 'First_Assigned_Group__c');

        return [
            'sf_id' => (string) $record['Id'],
            'case_number' => (string) $record['CaseNumber'],
            'subject' => (string) ($record['Subject'] ?? ''),
            'description' => $this->nullableString($record, 'Description'),
            'status' => (string) ($record['Status'] ?? ''),
            'priority' => $this->nullableString($record, 'Priority'),
            'type' => $type,
            'sf_type_id' => $this->lookupId($lookups['types'], $type),
            'origin' => $this->nullableString($record, 'Origin'),
            'is_closed' => (bool) ($record['IsClosed'] ?? false),
            'is_spam' => (bool) ($record['Is_Spam__c'] ?? false),
            'product' => $product,
            'sf_product_id' => $this->lookupId($lookups['products'], $product),
            'product_name' => $this->nullableString($record, 'Product_Name__c'),
            'application' => $application,
            'sf_application_id' => $this->lookupId($lookups['applications'], $application),
            'module' => $module,
            'sf_module_id' => $this->lookupId($lookups['modules'], $module),
            'sub_module' => $subModule,
            'sf_sub_module_id' => $this->lookupId($lookups['subModules'], $subModule),
            'group_name' => $groupName,
            'sf_group_id' => $this->lookupId($lookups['groups'], $groupName),
            'first_assigned_group' => $firstGroup,
            'sf_first_assigned_group_id' => $this->lookupId($lookups['groups'], $firstGroup),
            'owner_name' => $this->relatedName($record, 'Owner'),
            'agent_name' => $this->relatedName($record, 'Agent__r'),
            'sf_agent_id' => $this->lookupId($lookups['agents'], $agentSfId),
            'sf_account_id' => $this->lookupId($lookups['accounts'], $accountSfId),
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
     * @param  Collection<string, int|string>  $idsByKey
     */
    private function lookupId(Collection $idsByKey, ?string $key): ?int
    {
        if ($key === null) {
            return null;
        }

        $id = $idsByKey->get($key);

        return $id !== null ? (int) $id : null;
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
