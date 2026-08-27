<?php

namespace App\Jobs;

use App\Enums\ExternalQueryStatus;
use App\Models\ExternalQueryLog;
use App\Models\InvoiceReconSession;
use App\Models\Organization;
use App\Models\OrganizationDatabaseConnection;
use App\Services\InvoiceReconciliationPuller;
use App\Services\OrganizationDatabaseConnector;
use App\Support\ExternalQueryQueue;
use App\Support\InvoiceReconciliationQueries;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

class PullErpInvoiceFromConnectionJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 90;

    public int $tries = 1;

    public function __construct(
        public readonly int $sessionId,
        public readonly int $pgsqlConnectionId,
        public readonly ?int $externalQueryLogId = null,
    ) {
        $this->onQueue(ExternalQueryQueue::NAME);
    }

    public function handle(
        OrganizationDatabaseConnector $connector,
        InvoiceReconciliationPuller $puller,
    ): void {
        $session = InvoiceReconSession::query()->findOrFail($this->sessionId);
        $log = $this->externalQueryLog();
        $log?->markProcessing();

        $session->update([
            'status' => 'processing',
            'failure_reason' => null,
        ]);

        $mysqlRuntime = null;
        $pgsqlRuntime = null;

        try {
            $organization = Organization::query()->find($session->organization_id);

            if ($organization === null || blank($organization->db_name)) {
                throw new RuntimeException(
                    'Organization MySQL database name is missing. Attach a Zwing vendor with db_name first.',
                );
            }

            if ($session->date_from === null || $session->date_to === null) {
                throw new RuntimeException('Invoice pull requires date_from and date_to.');
            }

            $mysqlRuntime = $connector->openMysqlSshDatabase((string) $organization->db_name);

            $pgsqlConnection = OrganizationDatabaseConnection::query()
                ->findOrFail($this->pgsqlConnectionId);
            $pgsqlRuntime = $connector->open($pgsqlConnection);

            $puller->insertFromQuery(
                connector: $connector,
                runtimeName: $pgsqlRuntime,
                sql: InvoiceReconciliationQueries::PGSQL,
                bindings: InvoiceReconciliationQueries::pgsqlBindings(
                    $session->date_from->toDateString(),
                    $session->date_to->toDateString(),
                ),
                table: 'erp_invoice_reconsile',
                session: $session,
                progressColumn: 'erp_processed_rows',
                skippedColumn: 'erp_skipped_rows',
                rowCountColumn: 'erp_row_count',
                queryMsColumn: 'erp_query_ms',
            );

            $session->refresh();

            $session->update([
                'status' => 'completed',
                'failure_reason' => null,
                'reconciled_at' => now(),
            ]);

            $log?->markCompleted(
                result: [
                    'session_id' => $session->id,
                    'erp_row_count' => $session->erp_row_count,
                ],
                erpQueryMs: $session->erp_query_ms,
            );
        } catch (Throwable $exception) {
            $this->markFailed($exception, $puller);

            throw $exception;
        } finally {
            if ($pgsqlRuntime !== null) {
                $connector->close($pgsqlRuntime);
            }

            if ($mysqlRuntime !== null) {
                $connector->close($mysqlRuntime);
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->markFailed($exception, app(InvoiceReconciliationPuller::class));
    }

    private function markFailed(Throwable $exception, InvoiceReconciliationPuller $puller): void
    {
        InvoiceReconSession::query()
            ->where('id', $this->sessionId)
            ->update([
                'status' => 'failed',
                'failure_reason' => $puller->safeFailureReason($exception),
            ]);

        $log = $this->externalQueryLog();

        if ($log !== null && $log->status !== ExternalQueryStatus::Failed) {
            $log->markFailed($exception);
        }
    }

    private function externalQueryLog(): ?ExternalQueryLog
    {
        if ($this->externalQueryLogId === null) {
            return null;
        }

        return ExternalQueryLog::query()->find($this->externalQueryLogId);
    }
}
