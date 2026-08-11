<?php

namespace App\Services;

use App\Models\TransactionReconSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class TransactionReconciliationPuller
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
        TransactionReconSession $session,
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
                'txn_id' => trim((string) $record['txn_id']),
                'code' => trim((string) ($record['code'] ?? '')),
                'type' => trim((string) ($record['type'] ?? '')),
                'status' => trim((string) ($record['status'] ?? '')),
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

    public function safeFailureReason(Throwable $exception): string
    {
        $message = $exception->getMessage();
        $message = preg_replace('/Database:\s*[^,]*/i', 'Database: [hidden]', $message) ?? $message;

        return Str::limit($message, 2000);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public function isValidRow(array $record): bool
    {
        return isset($record['txn_id']) && trim((string) $record['txn_id']) !== '';
    }
}
