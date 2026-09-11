<?php

namespace App\Services;

use App\Models\ReportReconSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ReportConsolidationPuller
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
        ReportReconSession $session,
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
     *     invoice_no: string,
     *     store_name: string|null,
     *     date: string|null,
     *     total: string,
     *     created_at: string,
     *     updated_at: string
     * }
     */
    public function mapInsertRow(array $record, ReportReconSession $session, string $now): array
    {
        $date = $record['date'] ?? null;
        $storeName = $record['store_name'] ?? null;

        return [
            'session_id' => $session->id,
            'v_id' => $session->v_id,
            'invoice_no' => trim((string) $record['invoice_no']),
            'store_name' => filled($storeName) ? trim((string) $storeName) : null,
            'date' => filled($date) ? (string) $date : null,
            'total' => number_format((float) $record['total'], 4, '.', ''),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public function isValidRow(array $record): bool
    {
        if (! isset($record['invoice_no']) || trim((string) $record['invoice_no']) === '') {
            return false;
        }

        return isset($record['total']) && is_numeric($record['total']);
    }

    public function safeFailureReason(Throwable $exception): string
    {
        $message = $exception->getMessage();
        $message = preg_replace('/Database:\s*[^,]*/i', 'Database: [hidden]', $message) ?? $message;

        return Str::limit($message, 2000);
    }
}
