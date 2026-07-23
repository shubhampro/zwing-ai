<?php

namespace App\Services;

use App\Models\StockReconSession;
use App\Support\Sprefcode;
use App\Support\StockReconciliationConnectionQueries;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class StockReconciliationConnectionPuller
{
    private const CHUNK_SIZE = 500;

    /**
     * @return list<string>
     */
    public function activeSiteCodes(OrganizationDatabaseConnector $connector, string $mysqlRuntime): array
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
    public function insertFromQuery(
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
