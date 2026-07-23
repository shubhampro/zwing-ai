<?php

namespace App\Jobs;

use App\Enums\ExternalQueryStatus;
use App\Models\ExternalQueryLog;
use App\Models\Organization;
use App\Models\OrganizationDatabaseConnection;
use App\Models\StockReconSession;
use App\Services\OrganizationDatabaseConnector;
use App\Support\ExternalQueryQueue;
use App\Support\Sprefcode;
use App\Support\StockReconciliationConnectionQueries;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PullStockReconciliationFromConnections implements ShouldQueue
{
    use Queueable;

    private const CHUNK_SIZE = 500;

    public int $timeout = 90;

    public int $tries = 1;

    public function __construct(
        public readonly int $sessionId,
        public readonly ?int $pgsqlConnectionId,
        public readonly bool $includeZwing,
        public readonly bool $includeErp,
        public readonly ?int $externalQueryLogId = null,
    ) {
        $this->onQueue(ExternalQueryQueue::NAME);
    }

    public function handle(OrganizationDatabaseConnector $connector): void
    {
        $session = StockReconSession::query()->findOrFail($this->sessionId);
        $log = $this->externalQueryLog();
        $log?->markProcessing();

        $session->update([
            'status' => 'processing',
            'failure_reason' => null,
        ]);

        $mysqlRuntime = null;
        $pgsqlRuntime = null;

        try {
            if ($this->includeZwing || $this->includeErp) {
                $organization = Organization::query()->find($session->organization_id);

                if ($organization === null || blank($organization->db_name)) {
                    throw new RuntimeException(
                        'Organization MySQL database name is missing. Attach a Zwing vendor with db_name first.',
                    );
                }

                $mysqlRuntime = $connector->openMysqlSshDatabase((string) $organization->db_name);
            }

            if ($this->includeZwing && $mysqlRuntime !== null) {
                $this->insertFromQuery(
                    connector: $connector,
                    runtimeName: $mysqlRuntime,
                    sql: StockReconciliationConnectionQueries::MYSQL_STOCK,
                    bindings: [],
                    table: 'zwing_stock_reconsile',
                    session: $session,
                    progressColumn: 'zwing_processed_rows',
                    skippedColumn: 'zwing_skipped_rows',
                    rowCountColumn: 'zwing_row_count',
                    queryMsColumn: 'zwing_query_ms',
                );
            }

            if ($this->includeErp) {
                if ($this->pgsqlConnectionId === null || $mysqlRuntime === null) {
                    throw new RuntimeException(
                        'Postgres connection and MySQL org database are required for ERP stock pull.',
                    );
                }

                $siteCodes = $this->activeSiteCodes($connector, $mysqlRuntime);

                if ($siteCodes === []) {
                    throw new RuntimeException('No active store site codes found on MySQL connection.');
                }

                $pgsqlConnection = OrganizationDatabaseConnection::query()
                    ->findOrFail($this->pgsqlConnectionId);
                $pgsqlRuntime = $connector->open($pgsqlConnection);

                [$sql, $bindings] = StockReconciliationConnectionQueries::pgsqlStock($siteCodes);

                $this->insertFromQuery(
                    connector: $connector,
                    runtimeName: $pgsqlRuntime,
                    sql: $sql,
                    bindings: $bindings,
                    table: 'erp_stock_reconsile',
                    session: $session,
                    progressColumn: 'erp_processed_rows',
                    skippedColumn: 'erp_skipped_rows',
                    rowCountColumn: 'erp_row_count',
                    queryMsColumn: 'erp_query_ms',
                );
            }

            $session->update([
                'status' => 'completed',
                'failure_reason' => null,
                'reconciled_at' => now(),
            ]);

            $session->refresh();

            $log?->markCompleted(
                result: [
                    'session_id' => $session->id,
                    'zwing_row_count' => $session->zwing_row_count,
                    'erp_row_count' => $session->erp_row_count,
                ],
                zwingQueryMs: $session->zwing_query_ms,
                erpQueryMs: $session->erp_query_ms,
            );
        } catch (Throwable $exception) {
            $this->markFailed($exception);

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
        $this->markFailed($exception);
    }

    private function markFailed(Throwable $exception): void
    {
        StockReconSession::query()
            ->where('id', $this->sessionId)
            ->update([
                'status' => 'failed',
                'failure_reason' => $this->safeFailureReason($exception),
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

    private function safeFailureReason(Throwable $exception): string
    {
        $message = $exception->getMessage();
        $message = preg_replace('/Database:\s*[^,]*/i', 'Database: [hidden]', $message) ?? $message;

        return Str::limit($message, 2000);
    }

    /**
     * @return list<string>
     */
    private function activeSiteCodes(OrganizationDatabaseConnector $connector, string $mysqlRuntime): array
    {
        $codes = [];

        $connector->eachRow(
            $mysqlRuntime,
            StockReconciliationConnectionQueries::MYSQL_ACTIVE_SITE_CODES,
            [],
            function (array $row) use (&$codes): void {
                $code = trim((string) ($row['store_reference_code'] ?? ''));

                if ($code !== '') {
                    $codes[] = $code;
                }
            },
        );

        return array_values(array_unique($codes));
    }

    /**
     * @param  list<mixed>  $bindings
     */
    private function insertFromQuery(
        OrganizationDatabaseConnector $connector,
        string $runtimeName,
        string $sql,
        array $bindings,
        string $table,
        StockReconSession $session,
        string $progressColumn,
        string $skippedColumn,
        string $rowCountColumn,
        string $queryMsColumn,
    ): void {
        $now = now()->toDateTimeString();
        $chunk = [];
        $skippedChunk = 0;
        $startedAt = hrtime(true);

        $connector->eachRow($runtimeName, $sql, $bindings, function (array $record) use (
            &$chunk,
            &$skippedChunk,
            $session,
            $table,
            $progressColumn,
            $skippedColumn,
            $now,
        ): void {
            if (! $this->isValidRow($record)) {
                $skippedChunk++;

                return;
            }

            $chunk[] = [
                'session_id' => $session->id,
                'batch_no' => trim((string) ($record['batch_no'] ?? '')),
                'v_id' => $session->v_id,
                'barcode' => trim((string) ($record['barcode'] ?? '')),
                'icode' => trim((string) $record['icode']),
                'stock_point_name' => trim((string) $record['stock_point_name']),
                'site_code' => trim((string) $record['site_code']),
                'sprefcode' => Sprefcode::parse(trim((string) ($record['sprefcode'] ?? ''))),
                'qty' => (float) $record['qty'],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($chunk) >= self::CHUNK_SIZE) {
                DB::table($table)->insert($chunk);
                $session->increment($progressColumn, count($chunk));

                if ($skippedChunk > 0) {
                    $session->increment($skippedColumn, $skippedChunk);
                    $skippedChunk = 0;
                }

                $chunk = [];
            }
        });

        if ($chunk !== []) {
            DB::table($table)->insert($chunk);
            $session->increment($progressColumn, count($chunk));
        }

        if ($skippedChunk > 0) {
            $session->increment($skippedColumn, $skippedChunk);
        }

        $session->refresh();

        $elapsedMs = (int) max(0, (hrtime(true) - $startedAt) / 1_000_000);

        $session->update([
            $rowCountColumn => $session->{$progressColumn},
            $queryMsColumn => $elapsedMs,
        ]);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function isValidRow(array $record): bool
    {
        if (! isset($record['icode']) || trim((string) $record['icode']) === '') {
            return false;
        }

        foreach (['site_code', 'stock_point_name'] as $col) {
            if (! isset($record[$col]) || trim((string) $record[$col]) === '') {
                return false;
            }
        }

        if (! isset($record['qty']) || ! is_numeric(trim((string) $record['qty']))) {
            return false;
        }

        return true;
    }
}
