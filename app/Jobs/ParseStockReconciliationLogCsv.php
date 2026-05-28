<?php

namespace App\Jobs;

use App\Models\StockReconSession;
use App\Support\Sprefcode;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;

class ParseStockReconciliationLogCsv implements ShouldQueue
{
    use Queueable;

    /** @var int Rows inserted per bulk-insert batch */
    private const CHUNK_SIZE = 500;

    /** @var list<string> */
    public const LOG_COLUMNS = ['site_code', 'icode', 'batch_no', 'sprefcode', 'doc_no', 'enttype', 'qty'];

    public function __construct(
        public readonly int $sessionId,
        public readonly string $zwingLogPath,
        public readonly string $erpLogPath,
    ) {}

    public function handle(): void
    {
        $session = StockReconSession::findOrFail($this->sessionId);

        if ($this->zwingLogPath !== '') {
            $this->insertLogRows(
                path: $this->zwingLogPath,
                table: 'stock_recon_zwing_logs',
                session: $session,
                progressColumn: 'zwing_log_processed_rows',
                skippedColumn: 'zwing_log_skipped_rows',
            );
        }

        if ($this->erpLogPath !== '') {
            $this->insertLogRows(
                path: $this->erpLogPath,
                table: 'stock_recon_erp_logs',
                session: $session,
                progressColumn: 'erp_log_processed_rows',
                skippedColumn: 'erp_log_skipped_rows',
            );
        }
    }

    /**
     * @param  array<string, string>  $record
     */
    public static function isValidLogRow(array $record): bool
    {
        if (! isset($record['icode']) || trim((string) $record['icode']) === '') {
            return false;
        }

        return isset($record['qty']) && is_numeric(trim((string) $record['qty']));
    }

    private function insertLogRows(
        string $path,
        string $table,
        StockReconSession $session,
        string $progressColumn,
        string $skippedColumn,
    ): void {
        $csv = Reader::createFromPath($path, 'r');
        $csv->setHeaderOffset(0);

        $rawHeaders = $csv->getHeader();
        $normalizedHeaders = array_map(
            fn (string $h) => strtolower(trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $h) ?? $h)),
            $rawHeaders,
        );

        $now = now()->toDateTimeString();
        $chunk = [];
        $skippedChunk = 0;

        foreach ($csv->getRecords($normalizedHeaders) as $record) {
            if (! self::isValidLogRow($record)) {
                $skippedChunk++;

                continue;
            }

            $sprefcode = trim((string) ($record['sprefcode'] ?? ''));

            if ($table === 'stock_recon_erp_logs') {
                $sprefcode = (string) Sprefcode::parse($sprefcode);
            }

            $chunk[] = [
                'stock_recon_session_id' => $session->id,
                'v_id' => $session->v_id,
                'site_code' => trim((string) ($record['site_code'] ?? '')),
                'icode' => trim((string) $record['icode']),
                'batch_no' => trim((string) ($record['batch_no'] ?? '')),
                'sprefcode' => $sprefcode,
                'doc_no' => trim((string) ($record['doc_no'] ?? '')),
                'enttype' => trim((string) ($record['enttype'] ?? '')),
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
}
