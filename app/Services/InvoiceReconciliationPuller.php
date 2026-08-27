<?php

namespace App\Services;

use App\Models\InvoiceReconSession;
use App\Support\InvoiceRefId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class InvoiceReconciliationPuller
{
    private const CHUNK_SIZE = 500;

    /**
     * @param  list<mixed>  $bindings
     */
    public function insertFromQuery(
        OrganizationDatabaseConnector $connector,
        string $runtimeName,
        string $sql,
        array $bindings,
        string $table,
        InvoiceReconSession $session,
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

            $chunk[] = $this->mapInsertRow($record, $session, $now);

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
     * @return array{
     *     session_id: int,
     *     v_id: int,
     *     invoice_id: string,
     *     ref_id: string,
     *     total_amount: string,
     *     status: string,
     *     created_at: string,
     *     updated_at: string
     * }
     */
    public function mapInsertRow(array $record, InvoiceReconSession $session, string $now): array
    {
        return [
            'session_id' => $session->id,
            'v_id' => $session->v_id,
            'invoice_id' => trim((string) $record['invoice_id']),
            'ref_id' => InvoiceRefId::normalize((string) ($record['ref_id'] ?? '')),
            'total_amount' => number_format((float) $record['total_amount'], 4, '.', ''),
            'status' => trim((string) ($record['status'] ?? '')),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public function isValidRow(array $record): bool
    {
        if (! isset($record['invoice_id']) || trim((string) $record['invoice_id']) === '') {
            return false;
        }

        if (! InvoiceRefId::isValid((string) ($record['ref_id'] ?? ''))) {
            return false;
        }

        if (! isset($record['total_amount']) || ! is_numeric($record['total_amount'])) {
            return false;
        }

        return isset($record['status']) && trim((string) $record['status']) !== '';
    }

    public function safeFailureReason(Throwable $exception): string
    {
        $message = $exception->getMessage();
        $message = preg_replace('/Database:\s*[^,]*/i', 'Database: [hidden]', $message) ?? $message;

        return Str::limit($message, 2000);
    }
}
