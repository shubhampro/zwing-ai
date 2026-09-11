<?php

namespace App\Jobs;

use App\Enums\ExternalQueryStatus;
use App\Models\ExternalQueryLog;
use App\Models\Organization;
use App\Models\ReportReconSession;
use App\Services\OrganizationDatabaseConnector;
use App\Services\ReportConsolidationPuller;
use App\Support\ExternalQueryQueue;
use App\Support\ReportConsolidationQueries;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

class PullReportConsolidationFromConnectionJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 180;

    public int $tries = 1;

    public function __construct(
        public readonly int $sessionId,
        public readonly ?int $externalQueryLogId = null,
    ) {
        $this->onQueue(ExternalQueryQueue::NAME);
    }

    public function handle(
        OrganizationDatabaseConnector $connector,
        ReportConsolidationPuller $puller,
    ): void {
        $session = ReportReconSession::query()->findOrFail($this->sessionId);
        $log = $this->externalQueryLog();
        $log?->markProcessing();

        $session->update([
            'status' => 'processing',
            'failure_reason' => null,
        ]);

        $mysqlRuntime = null;

        try {
            $organization = Organization::query()->find($session->organization_id);

            if ($organization === null || blank($organization->db_name)) {
                throw new RuntimeException(
                    'Organization MySQL database name is missing. Attach a Zwing vendor with db_name first.',
                );
            }

            if ($session->date_from === null || $session->date_to === null) {
                throw new RuntimeException('Report consolidation pull requires date_from and date_to.');
            }

            $bindings = ReportConsolidationQueries::bindings(
                $session->date_from->toDateString(),
                $session->date_to->toDateString(),
            );

            $mysqlRuntime = $connector->openMysqlSshDatabase((string) $organization->db_name);

            $puller->insertFromQuery(
                connector: $connector,
                runtimeName: $mysqlRuntime,
                sql: ReportConsolidationQueries::INVOICE,
                bindings: $bindings,
                table: 'report_recon_invoices',
                session: $session,
                progressColumn: 'invoice_processed_rows',
                skippedColumn: 'invoice_skipped_rows',
                rowCountColumn: 'invoice_row_count',
                queryMsColumn: 'invoice_query_ms',
            );

            $puller->insertFromQuery(
                connector: $connector,
                runtimeName: $mysqlRuntime,
                sql: ReportConsolidationQueries::MOP,
                bindings: $bindings,
                table: 'report_recon_mops',
                session: $session,
                progressColumn: 'mop_processed_rows',
                skippedColumn: 'mop_skipped_rows',
                rowCountColumn: 'mop_row_count',
                queryMsColumn: 'mop_query_ms',
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
                    'invoice_row_count' => $session->invoice_row_count,
                    'mop_row_count' => $session->mop_row_count,
                ],
                zwingQueryMs: (int) $session->invoice_query_ms + (int) $session->mop_query_ms,
            );
        } catch (Throwable $exception) {
            $this->markFailed($exception, $puller);

            throw $exception;
        } finally {
            if ($mysqlRuntime !== null) {
                $connector->close($mysqlRuntime);
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->markFailed($exception, app(ReportConsolidationPuller::class));
    }

    private function markFailed(Throwable $exception, ReportConsolidationPuller $puller): void
    {
        ReportReconSession::query()
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
