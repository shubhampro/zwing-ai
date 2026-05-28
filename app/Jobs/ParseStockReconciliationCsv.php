<?php

namespace App\Jobs;

use App\Models\StockReconSession;
use App\Support\Sprefcode;
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
        public readonly string $zwingLogPath = '',
        public readonly string $erpLogPath = '',
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

        if ($this->zwingLogPath !== '' || $this->erpLogPath !== '') {
            (new ParseStockReconciliationLogCsv(
                sessionId: $this->sessionId,
                zwingLogPath: $this->zwingLogPath,
                erpLogPath: $this->erpLogPath,
            ))->handle();
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
                'sprefcode' => Sprefcode::parse(trim((string) $record['sprefcode'])),
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
     * Required non-empty columns per row (batch_no is optional).
     */
    /**
     * A row is valid when it has at least one identifier (barcode or icode),
     * site_code + stock_point_name non-empty, and qty is numeric.
     *
     * @param  array<string, string>  $record
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
