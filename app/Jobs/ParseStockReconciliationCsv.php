<?php

namespace App\Jobs;

use App\Models\StockReconSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;
use Throwable;

class ParseStockReconciliationCsv implements ShouldQueue
{
    use Queueable;

    /** @var int Rows inserted per bulk-insert batch */
    private const CHUNK_SIZE = 500;

    public function __construct(
        public readonly int $sessionId,
        public readonly string $zwingPath,
        public readonly string $erpPath,
    ) {}

    public function handle(): void
    {
        $session = StockReconSession::findOrFail($this->sessionId);
        $session->update(['status' => 'processing']);

        if ($this->zwingPath !== '') {
            $this->insertRows(
                path: $this->zwingPath,
                table: 'zwing_stock_reconsile',
                session: $session,
                progressColumn: 'zwing_processed_rows',
                skippedColumn: 'zwing_skipped_rows',
            );
        }

        if ($this->erpPath !== '') {
            $this->insertRows(
                path: $this->erpPath,
                table: 'erp_stock_reconsile',
                session: $session,
                progressColumn: 'erp_processed_rows',
                skippedColumn: 'erp_skipped_rows',
            );
        }

        $session->update([
            'status' => 'completed',
            'reconciled_at' => now(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        StockReconSession::where('id', $this->sessionId)
            ->update(['status' => 'failed']);
    }

    /**
     * Stream a CSV, bulk-insert valid rows, skip invalid ones,
     * and update both progress and skipped counters after every chunk.
     */
    private function insertRows(
        string $path,
        string $table,
        StockReconSession $session,
        string $progressColumn,
        string $skippedColumn,
    ): void {
        $csv = Reader::createFromPath($path, 'r');
        $csv->setHeaderOffset(0);

        // Normalise header names: strip whitespace, BOM, and lowercase
        $rawHeaders = $csv->getHeader();
        $normalizedHeaders = array_map(
            fn (string $h) => strtolower(trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $h) ?? $h)),
            $rawHeaders,
        );

        $now = now()->toDateTimeString();
        $chunk = [];
        $skippedChunk = 0;

        foreach ($csv->getRecords($normalizedHeaders) as $record) {
            if (! $this->isValidRow($record)) {
                $skippedChunk++;

                continue;
            }

            $chunk[] = [
                'session_id' => $session->id,
                'batch_no' => trim((string) $record['batch_no']),
                'v_id' => $session->v_id,
                'barcode' => trim((string) $record['barcode']),
                'icode' => trim((string) $record['icode']),
                'stock_point_name' => trim((string) $record['stock_point_name']),
                'site_code' => trim((string) $record['site_code']),
                'sprefcode' => $this->parseSprefcode(trim((string) $record['sprefcode'])),
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
        }

        if ($chunk !== []) {
            DB::table($table)->insert($chunk);
            $session->increment($progressColumn, count($chunk));
        }

        if ($skippedChunk > 0) {
            $session->increment($skippedColumn, $skippedChunk);
        }
    }

    /**
     * Extract the numeric suffix from a sprefcode value.
     * e.g. "HOAD1695-1" → 1, "42" → 42, "ABC-XYZ-5" → 5.
     * Returns 0 when no numeric part is found.
     */
    private function parseSprefcode(string $value): int
    {
        $parts = explode('-', $value);
        $last = trim((string) end($parts));

        return is_numeric($last) ? (int) $last : 0;
    }

    /**
     * Required non-empty columns per row (batch_no is optional).
     */
    private const REQUIRED_ROW_COLUMNS = ['barcode', 'icode', 'site_code', 'sprefcode', 'stock_point_name'];

    /**
     * A row is valid when all required columns are non-empty and qty is numeric.
     * batch_no is intentionally excluded — it may be blank in the source data.
     *
     * @param  array<string, string>  $record
     */
    private function isValidRow(array $record): bool
    {
        foreach (self::REQUIRED_ROW_COLUMNS as $col) {
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
