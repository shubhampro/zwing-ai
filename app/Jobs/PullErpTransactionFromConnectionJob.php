<?php

namespace App\Jobs;

use App\Enums\ExternalQueryStatus;
use App\Models\ExternalQueryLog;
use App\Models\Organization;
use App\Models\OrganizationDatabaseConnection;
use App\Models\TransactionReconSession;
use App\Services\OrganizationDatabaseConnector;
use App\Services\TransactionReconciliationPuller;
use App\Support\ExternalQueryQueue;
use App\Support\TransactionReconciliationQueries;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

class PullErpTransactionFromConnectionJob implements ShouldQueue
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
        TransactionReconciliationPuller $puller,
    ): void {
        $session = TransactionReconSession::query()->findOrFail($this->sessionId);
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

            // Keep tunnel warm the same way stock ERP pull does (org MySQL open first).
            $mysqlRuntime = $connector->openMysqlSshDatabase((string) $organization->db_name);

            $pgsqlConnection = OrganizationDatabaseConnection::query()
                ->findOrFail($this->pgsqlConnectionId);
            $pgsqlRuntime = $connector->open($pgsqlConnection);

            $puller->insertFromQuery(
                connector: $connector,
                runtimeName: $pgsqlRuntime,
                sql: TransactionReconciliationQueries::pgsql($session->type),
                bindings: [],
                table: 'erp_transaction_reconsile',
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
        $this->markFailed($exception, app(TransactionReconciliationPuller::class));
    }

    private function markFailed(Throwable $exception, TransactionReconciliationPuller $puller): void
    {
        TransactionReconSession::query()
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
