<?php

namespace App\Jobs;

use App\Models\ExpenseCashReconSession;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;
use Throwable;

class ParseExpenseCashReconciliationCsv implements ShouldQueue
{
    use Queueable;

    private const CHUNK_SIZE = 500;

    public function __construct(
        public readonly int $sessionId,
        public readonly string $zwingPath,
        public readonly string $erpPath,
    ) {}

    public function handle(): void
    {
        $session = ExpenseCashReconSession::findOrFail($this->sessionId);
        $session->update(['status' => 'processing']);

        if ($this->zwingPath !== '') {
            $this->insertRows(
                path: $this->zwingPath,
                table: 'zwing_expense_cash_reconsile',
                session: $session,
                progressColumn: 'zwing_processed_rows',
                skippedColumn: 'zwing_skipped_rows',
            );
        }

        if ($this->erpPath !== '') {
            $this->insertRows(
                path: $this->erpPath,
                table: 'erp_expense_cash_reconsile',
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
        ExpenseCashReconSession::where('id', $this->sessionId)
            ->update(['status' => 'failed']);
    }

    private function insertRows(
        string $path,
        string $table,
        ExpenseCashReconSession $session,
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
            if (! $this->isValidRow($record)) {
                $skippedChunk++;

                continue;
            }

            $chunk[] = [
                'session_id' => $session->id,
                'v_id' => $session->v_id,
                'site_id' => trim((string) $record['site_id']),
                'doc_no' => trim((string) $record['doc_no']),
                'txn_date' => $this->parseDate((string) $record['date']),
                'amount' => (float) $record['amount'],
                'status' => trim((string) $record['status']),
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
     * @param  array<string, string>  $record
     */
    private function isValidRow(array $record): bool
    {
        if (! isset($record['site_id']) || trim((string) $record['site_id']) === '') {
            return false;
        }

        if (! isset($record['doc_no']) || trim((string) $record['doc_no']) === '') {
            return false;
        }

        if (! isset($record['date']) || $this->parseDate((string) $record['date']) === null) {
            return false;
        }

        if (! isset($record['amount']) || ! is_numeric(trim((string) $record['amount']))) {
            return false;
        }

        if (! isset($record['status']) || trim((string) $record['status']) === '') {
            return false;
        }

        return true;
    }

    private function parseDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
