<?php

namespace App\Services\TransactionChecker;

use Illuminate\Database\ConnectionInterface;

/**
 * GRT Checker
 *
 * Validates that every posted GRT header has matching stock_out records and
 * that the qty in grt_details matches the sum of stock_out qty.
 *
 * Tables used: grt_headers, grt_details, stock_out
 */
class GrtChecker implements TransactionCheckerInterface
{
    public function run(ConnectionInterface $db): array
    {
        $rows = $db->select("
            SELECT
                h.id,
                h.grt_no                                              AS doc_no,
                h.created_at                                          AS doc_date,
                h.status,
                COALESCE(d.detail_qty, 0)                             AS detail_qty,
                COALESCE(so.stock_out_qty, 0)                         AS stock_out_qty,
                CASE
                    WHEN so.stock_out_qty IS NULL                     THEN 'MISSING_STOCK'
                    WHEN ABS(d.detail_qty - so.stock_out_qty) > 0.001 THEN 'QTY_MISMATCH'
                    ELSE 'OK'
                END                                                   AS result
            FROM grt_headers h
            LEFT JOIN (
                SELECT grt_id, SUM(CAST(qty AS DECIMAL(12,3))) AS detail_qty
                FROM grt_details
                WHERE status = 'POST'
                GROUP BY grt_id
            ) d ON d.grt_id = h.id
            LEFT JOIN (
                SELECT grn_id AS grt_id, SUM(qty) AS stock_out_qty
                FROM stock_out
                WHERE transaction_type = 'GRT'
                  AND status = 'POST'
                GROUP BY grn_id
            ) so ON so.grt_id = h.id
            WHERE h.status = 'POST'
            ORDER BY h.created_at DESC
            LIMIT 500
        ");

        $rows = array_map(fn (object $r) => (array) $r, $rows);

        $summary = [
            'total' => count($rows),
            'matched' => count(array_filter($rows, fn ($r) => $r['result'] === 'OK')),
            'mismatch' => count(array_filter($rows, fn ($r) => $r['result'] === 'QTY_MISMATCH')),
            'missing_stock' => count(array_filter($rows, fn ($r) => $r['result'] === 'MISSING_STOCK')),
        ];

        return compact('summary', 'rows');
    }
}
