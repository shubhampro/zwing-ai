<?php

namespace App\Services;

use App\Models\InvoiceReconSession;
use App\Models\StockReconSession;
use App\Support\InvoiceReconciliationComparison;
use Illuminate\Support\Facades\DB;

class ReconciliationSummaryService
{
    /**
     * @return array<string, mixed>|null
     */
    public function latestStockSummaryForUser(int $userId): ?array
    {
        $session = StockReconSession::query()
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->latest('reconciled_at')
            ->first(['id', 'name', 'v_id', 'reconciled_at']);

        if ($session === null) {
            return null;
        }

        $counts = DB::selectOne(<<<SQL
            SELECT
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE match_status = 'matched')      AS matched,
                COUNT(*) FILTER (WHERE match_status = 'qty_mismatch') AS qty_mismatch,
                COUNT(*) FILTER (WHERE match_status = 'zwing_only')   AS zwing_only,
                COUNT(*) FILTER (WHERE match_status = 'erp_only')     AS erp_only
            FROM ({$this->stockComparisonSql()}) AS cmp
        SQL, [$session->id]);

        return $this->formatSummary($session, $counts, 'qty_mismatch');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestInvoiceSummaryForUser(int $userId): ?array
    {
        $session = InvoiceReconSession::query()
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->latest('reconciled_at')
            ->first(['id', 'name', 'v_id', 'reconciled_at']);

        if ($session === null) {
            return null;
        }

        $counts = DB::selectOne(<<<SQL
            SELECT
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE match_status = 'matched')         AS matched,
                COUNT(*) FILTER (WHERE match_status = 'amount_mismatch') AS amount_mismatch,
                COUNT(*) FILTER (WHERE match_status = 'status_mismatch') AS status_mismatch,
                COUNT(*) FILTER (WHERE match_status IN ('invoice_not_in_erp', 'ref_id_not_in_erp')) AS zwing_only,
                COUNT(*) FILTER (WHERE match_status IN ('invoice_not_in_zwing', 'ref_id_not_in_zwing')) AS erp_only
            FROM ({$this->invoiceComparisonSql()}) AS cmp
        SQL, [$session->id]);

        return $this->formatSummary($session, $counts, 'amount_mismatch', 'status_mismatch');
    }

    /**
     * @param  object{total: int|string, matched: int|string, zwing_only: int|string, erp_only: int|string, qty_mismatch?: int|string, amount_mismatch?: int|string, status_mismatch?: int|string}  $counts
     * @return array<string, mixed>
     */
    private function formatSummary(
        StockReconSession|InvoiceReconSession $session,
        object $counts,
        string $primaryMismatchKey,
        ?string $secondaryMismatchKey = null,
    ): array {
        $total = (int) $counts->total;
        $matched = (int) $counts->matched;
        $zwingOnly = (int) $counts->zwing_only;
        $erpOnly = (int) $counts->erp_only;
        $primaryMismatch = (int) ($counts->{$primaryMismatchKey} ?? 0);
        $secondaryMismatch = $secondaryMismatchKey !== null
            ? (int) ($counts->{$secondaryMismatchKey} ?? 0)
            : 0;
        $mismatchTotal = $primaryMismatch + $secondaryMismatch;

        $percent = fn (int $value): float => $total > 0 ? round(($value / $total) * 100, 1) : 0.0;

        return [
            'session' => [
                'id' => $session->id,
                'name' => $session->name,
                'v_id' => $session->v_id,
                'reconciled_at' => $session->reconciled_at?->toIso8601String(),
            ],
            'total' => $total,
            'matched' => $matched,
            'matched_percent' => $percent($matched),
            'zwing_only' => $zwingOnly,
            'zwing_only_percent' => $percent($zwingOnly),
            'erp_only' => $erpOnly,
            'erp_only_percent' => $percent($erpOnly),
            'mismatch' => $mismatchTotal,
            'mismatch_percent' => $percent($mismatchTotal),
            'primary_mismatch' => $primaryMismatch,
            'primary_mismatch_percent' => $percent($primaryMismatch),
            'secondary_mismatch' => $secondaryMismatch,
            'secondary_mismatch_percent' => $percent($secondaryMismatch),
        ];
    }

    private function stockComparisonSql(): string
    {
        return <<<'SQL'
            SELECT
                CASE
                    WHEN z.id IS NULL THEN 'erp_only'
                    WHEN e.id IS NULL THEN 'zwing_only'
                    WHEN z.qty = e.qty THEN 'matched'
                    ELSE 'qty_mismatch'
                END AS match_status
            FROM zwing_stock_reconsile z
            FULL OUTER JOIN erp_stock_reconsile e
                ON  z.session_id = e.session_id
                AND z.site_code  = e.site_code
                AND z.icode      = e.icode
                AND z.batch_no   = e.batch_no
                AND z.sprefcode  = e.sprefcode
            WHERE COALESCE(z.session_id, e.session_id) = ?
        SQL;
    }

    private function invoiceComparisonSql(): string
    {
        return InvoiceReconciliationComparison::comparisonSql();
    }
}
