<?php

namespace App\Jobs;

use App\Enums\ExternalQueryJobType;
use App\Enums\ExternalQueryStatus;
use App\Models\ExternalQueryLog;
use App\Models\Organization;
use App\Models\OrganizationDatabaseConnection;
use App\Services\DbHealth\DbHealthChecker;
use App\Services\OrganizationDatabaseConnectionTester;
use App\Services\TransactionCheckerService;
use App\Services\ZwingVendorService;
use App\Support\ExternalQueryQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class RunExternalQueryJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $tries = 1;

    public function __construct(public readonly int $externalQueryLogId)
    {
        $this->onQueue(ExternalQueryQueue::NAME);
    }

    public function handle(
        ZwingVendorService $zwingVendors,
        OrganizationDatabaseConnectionTester $connectionTester,
        TransactionCheckerService $transactionChecker,
        DbHealthChecker $dbHealthChecker,
    ): void {
        $log = ExternalQueryLog::query()->findOrFail($this->externalQueryLogId);
        $log->markProcessing();

        $startedAt = hrtime(true);

        try {
            $result = match ($log->job_type) {
                ExternalQueryJobType::ListZwingVendors => $this->listZwingVendors($zwingVendors),
                ExternalQueryJobType::AttachZwingVendor => $this->attachZwingVendor($log, $zwingVendors),
                ExternalQueryJobType::UpdateFromZwingVendor => $this->updateFromZwingVendor($log, $zwingVendors),
                ExternalQueryJobType::TestOrgDbConnection => $this->testOrgDbConnection($log, $connectionTester),
                ExternalQueryJobType::ListTxnCheckerDatabases => $this->listTxnCheckerDatabases($log, $transactionChecker),
                ExternalQueryJobType::RunTxnChecker => $this->runTxnChecker($log, $transactionChecker),
                ExternalQueryJobType::ServerHealthCheck => $this->serverHealthCheck($dbHealthChecker),
                ExternalQueryJobType::PullStock,
                ExternalQueryJobType::SyncRow,
                ExternalQueryJobType::LogDetails => throw new RuntimeException(
                    "Job type {$log->job_type->value} uses a dedicated job class.",
                ),
            };

            $elapsedMs = (int) max(0, (hrtime(true) - $startedAt) / 1_000_000);

            $log->markCompleted(
                result: $result,
                zwingQueryMs: $elapsedMs,
                erpQueryMs: null,
            );
        } catch (Throwable $exception) {
            $log->markFailed($exception);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception === null) {
            return;
        }

        $log = ExternalQueryLog::query()->find($this->externalQueryLogId);

        if ($log !== null && $log->status !== ExternalQueryStatus::Failed) {
            $log->markFailed($exception);
        }
    }

    /**
     * @return array{vendors: list<array{id: int, name: string, ba_code: string}>, attached_vendor_ids: list<int>}
     */
    private function listZwingVendors(ZwingVendorService $zwingVendors): array
    {
        $attachedVendorIds = Organization::query()
            ->pluck('vendor_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return [
            'vendors' => $zwingVendors->list(),
            'attached_vendor_ids' => $attachedVendorIds,
        ];
    }

    /**
     * @return array{organization_id: int, vendor_id: int}
     */
    private function attachZwingVendor(ExternalQueryLog $log, ZwingVendorService $zwingVendors): array
    {
        $vendorId = (int) ($log->context['vendor_id'] ?? 0);
        $vendor = $zwingVendors->find($vendorId);

        if ($vendor === null) {
            throw ValidationException::withMessages([
                'vendor_id' => __('Vendor not found in Zwing Master.'),
            ]);
        }

        if (Organization::query()->where('ba_code', $vendor['ba_code'])->exists()) {
            throw ValidationException::withMessages([
                'vendor_id' => __('An organization with BA code :code already exists.', [
                    'code' => $vendor['ba_code'],
                ]),
            ]);
        }

        $organization = Organization::query()->create([
            'name' => $vendor['name'],
            'ba_code' => $vendor['ba_code'],
            'vendor_id' => $vendor['id'],
            'db_name' => $vendor['db_name'] !== '' ? $vendor['db_name'] : null,
        ]);

        return [
            'organization_id' => $organization->id,
            'vendor_id' => $vendor['id'],
        ];
    }

    /**
     * @return array{organization_id: int, vendor_id: int}
     */
    private function updateFromZwingVendor(ExternalQueryLog $log, ZwingVendorService $zwingVendors): array
    {
        $vendorId = (int) ($log->context['vendor_id'] ?? 0);
        $vendor = $zwingVendors->find($vendorId);

        if ($vendor === null) {
            throw ValidationException::withMessages([
                'vendor_id' => __('Vendor not found in Zwing Master.'),
            ]);
        }

        $organization = Organization::query()
            ->where('vendor_id', $vendorId)
            ->firstOrFail();

        if (Organization::query()
            ->where('ba_code', $vendor['ba_code'])
            ->where('id', '!=', $organization->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'vendor_id' => __('An organization with BA code :code already exists.', [
                    'code' => $vendor['ba_code'],
                ]),
            ]);
        }

        $organization->update([
            'name' => $vendor['name'],
            'ba_code' => $vendor['ba_code'],
            'db_name' => $vendor['db_name'] !== '' ? $vendor['db_name'] : null,
        ]);

        return [
            'organization_id' => $organization->id,
            'vendor_id' => $vendor['id'],
        ];
    }

    /**
     * @return array{ok: bool, message: string, latency_ms: int|null}
     */
    private function testOrgDbConnection(
        ExternalQueryLog $log,
        OrganizationDatabaseConnectionTester $connectionTester,
    ): array {
        $connectionId = (int) ($log->context['organization_database_connection_id'] ?? 0);
        $connection = OrganizationDatabaseConnection::query()->findOrFail($connectionId);

        return $connectionTester->test($connection);
    }

    /**
     * @return array{databases: list<string>}
     */
    private function listTxnCheckerDatabases(
        ExternalQueryLog $log,
        TransactionCheckerService $transactionChecker,
    ): array {
        $orgId = (int) ($log->context['org_id'] ?? 0);

        return [
            'databases' => $transactionChecker->databasesForOrganization($orgId),
        ];
    }

    /**
     * @return array{summary: array<string, int>, rows: array<int, array<string, mixed>>}
     */
    private function runTxnChecker(
        ExternalQueryLog $log,
        TransactionCheckerService $transactionChecker,
    ): array {
        return $transactionChecker->check(
            connection: (string) ($log->context['connection'] ?? ''),
            transactionType: (string) ($log->context['transaction_type'] ?? ''),
            orgId: (int) ($log->context['org_id'] ?? 0),
            database: (string) ($log->context['database'] ?? ''),
            userId: $log->user_id,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serverHealthCheck(DbHealthChecker $dbHealthChecker): array
    {
        $result = $dbHealthChecker->run();

        if ($result === null) {
            throw new RuntimeException('A health check is already running. Try again shortly.');
        }

        return $result;
    }
}
