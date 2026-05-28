<?php

namespace App\Services\TransactionChecker;

use Illuminate\Database\ConnectionInterface;

/**
 * GRN Checker
 *
 * Validates that every posted GRN has matching stock_in records and
 * that the qty in grn_list matches the sum of stock_in qty.
 *
 * Tables used: grn, grn_list, stock_in
 */
class GrnChecker implements TransactionCheckerInterface
{
    public function run(ConnectionInterface $db): array
    {
        // Fetch all posted GRNs with their detail qty sum and stock_in qty sum
        $rows = $db->select("
            SELECT
                g.id,
                g.grn_no                                          AS doc_no,
                g.created_at                                      AS doc_date,
                g.status,
                COALESCE(gl.detail_qty, 0)                        AS detail_qty,
                COALESCE(si.stock_in_qty, 0)                      AS stock_in_qty,
                CASE
                    WHEN si.stock_in_qty IS NULL                  THEN 'MISSING_STOCK'
                    WHEN ABS(gl.detail_qty - si.stock_in_qty) > 0.001 THEN 'QTY_MISMATCH'
                    ELSE 'OK'
                END                                               AS result
            FROM grn g
            LEFT JOIN (
                SELECT grn_id, SUM(qty) AS detail_qty
                FROM grn_list
                WHERE status IN ('posted','saved')
                GROUP BY grn_id
            ) gl ON gl.grn_id = g.id
            LEFT JOIN (
                SELECT grn_id, SUM(qty) AS stock_in_qty
                FROM stock_in
                WHERE transaction_type = 'GRN'
                  AND status = 'POST'
                GROUP BY grn_id
            ) si ON si.grn_id = g.id
            WHERE g.status = 'posted'
            ORDER BY g.created_at DESC
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
