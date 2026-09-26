<?php

namespace App\Services\Salesforce;

use App\Exceptions\SalesforceQueryException;
use App\Models\SfApplication;
use App\Models\SfGroup;
use App\Models\SfModule;
use App\Models\SfProduct;
use App\Models\SfSubModule;
use App\Models\SfType;
use Illuminate\Database\Eloquent\Model;

class SalesforceCasePicklistPuller
{
    public const SOBJECT = 'Case';

    public const FIELD_PRODUCT = 'Product__c';

    public const FIELD_APPLICATION = 'Application__c';

    public const FIELD_MODULE = 'Module__c';

    public const FIELD_SUB_MODULE = 'Sub_module__c';

    public const FIELD_TYPE = 'Type';

    public const FIELD_GROUP = 'Group__c';

    /**
     * @var array<string, mixed>|null
     */
    private ?array $describe = null;

    public function __construct(private SalesforceSoqlClient $client) {}

    public function pullProducts(bool $dryRun = false): int
    {
        return $this->persist(SfProduct::class, $this->values(self::FIELD_PRODUCT), $dryRun);
    }

    public function pullApplications(bool $dryRun = false): int
    {
        return $this->persist(SfApplication::class, $this->values(self::FIELD_APPLICATION), $dryRun);
    }

    public function pullModules(bool $dryRun = false): int
    {
        return $this->persist(SfModule::class, $this->values(self::FIELD_MODULE), $dryRun);
    }

    public function pullSubModules(bool $dryRun = false): int
    {
        return $this->persist(SfSubModule::class, $this->values(self::FIELD_SUB_MODULE), $dryRun);
    }

    public function pullTypes(bool $dryRun = false): int
    {
        return $this->persist(SfType::class, $this->values(self::FIELD_TYPE), $dryRun);
    }

    public function pullGroups(bool $dryRun = false): int
    {
        return $this->persist(SfGroup::class, $this->values(self::FIELD_GROUP), $dryRun);
    }

    /**
     * @return list<array{name: string, sort_order: int, is_active: bool, synced_at: string}>
     */
    private function values(string $fieldName): array
    {
        $this->describe ??= $this->client->describe(self::SOBJECT);
        $fields = $this->describe['fields'] ?? [];

        if (! is_array($fields)) {
            throw new SalesforceQueryException("Salesforce Case describe did not include fields for [{$fieldName}].");
        }

        $field = collect($fields)->first(
            fn (mixed $candidate): bool => is_array($candidate) && ($candidate['name'] ?? null) === $fieldName,
        );

        if (! is_array($field)) {
            throw new SalesforceQueryException("Salesforce Case field [{$fieldName}] was not found.");
        }

        $syncedAt = now()->utc()->toDateTimeString();

        return collect($field['picklistValues'] ?? [])
            ->values()
            ->map(function (mixed $value, int $index) use ($syncedAt): ?array {
                if (! is_array($value) || blank($value['value'] ?? null)) {
                    return null;
                }

                return [
                    'name' => (string) $value['value'],
                    'sort_order' => $index,
                    'is_active' => (bool) ($value['active'] ?? true),
                    'synced_at' => $syncedAt,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  class-string<Model>  $model
     * @param  list<array{name: string, sort_order: int, is_active: bool, synced_at: string}>  $rows
     */
    private function persist(string $model, array $rows, bool $dryRun): int
    {
        if ($dryRun || $rows === []) {
            return count($rows);
        }

        $model::upsert($rows, uniqueBy: ['name'], update: ['sort_order', 'is_active', 'synced_at']);

        return count($rows);
    }
}
