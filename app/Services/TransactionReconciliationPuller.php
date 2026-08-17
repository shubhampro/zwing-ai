<?php

namespace App\Services;

use App\Models\TransactionReconSession;
use Illuminate\Support\Carbon;
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

            $chunk[] = $this->mapInsertRow($record, $session->id, $now);

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
     * @return array{
     *     session_id: int,
     *     txn_id: string,
     *     code: string,
     *     type: string,
     *     status: string,
     *     site_id: ?string,
     *     txn_date: ?string,
     *     amount: ?string,
     *     created_at: string,
     *     updated_at: string
     * }
     */
    public function mapInsertRow(array $record, int $sessionId, string $now): array
    {
        return [
            'session_id' => $sessionId,
            'txn_id' => trim((string) $record['txn_id']),
            'code' => trim((string) ($record['code'] ?? '')),
            'type' => trim((string) ($record['type'] ?? '')),
            'status' => trim((string) ($record['status'] ?? '')),
            'site_id' => $this->nullableString($record, 'site_id'),
            'txn_date' => $this->nullableDate($record, 'txn_date')
                ?? $this->nullableDate($record, 'date'),
            'amount' => $this->nullableAmount($record, 'amount'),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public function isValidRow(array $record): bool
    {
        return isset($record['txn_id']) && trim((string) $record['txn_id']) !== '';
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function nullableString(array $record, string $key): ?string
    {
        if (! array_key_exists($key, $record) || $record[$key] === null) {
            return null;
        }

        $value = trim((string) $record[$key]);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function nullableDate(array $record, string $key): ?string
    {
        $value = $this->nullableString($record, $key);

        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function nullableAmount(array $record, string $key): ?string
    {
        if (! array_key_exists($key, $record) || $record[$key] === null || $record[$key] === '') {
            return null;
        }

        if (! is_numeric($record[$key])) {
            return null;
        }

        return number_format((float) $record[$key], 4, '.', '');
    }
}
