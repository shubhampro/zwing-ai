<?php

namespace App\Services\TransactionChecker;

use Illuminate\Database\ConnectionInterface;

/**
 * SST Checker (Stock Store Transfer)
 *
 * Validates that every posted SST in stock_point_transfers has:
 *   - matching stock_out records (items leaving source)
 *   - matching stock_in records (items arriving at destination)
 *   - and that both qtys match the transfer detail qty.
 *
 * Tables used: stock_point_transfers, stock_point_transfer_details, stock_in, stock_out
 */
class SstChecker implements TransactionCheckerInterface
{
    public function run(ConnectionInterface $db): array
    {
        $rows = $db->select("
            SELECT
                t.id,
                t.doc_no,
                t.created_at                                            AS doc_date,
                t.status,
                COALESCE(td.detail_qty, 0)                              AS detail_qty,
                COALESCE(si.stock_in_qty, 0)                            AS stock_in_qty,
                COALESCE(so.stock_out_qty, 0)                           AS stock_out_qty,
                CASE
                    WHEN si.stock_in_qty IS NULL OR so.stock_out_qty IS NULL
                        THEN 'MISSING_STOCK'
                    WHEN ABS(td.detail_qty - si.stock_in_qty) > 0.001
                      OR ABS(td.detail_qty - so.stock_out_qty) > 0.001
                        THEN 'QTY_MISMATCH'
                    ELSE 'OK'
                END                                                     AS result
            FROM stock_point_transfers t
            LEFT JOIN (
                SELECT stock_point_transfer_id, SUM(qty) AS detail_qty
                FROM stock_point_transfer_details
                WHERE status = 'POST'
                GROUP BY stock_point_transfer_id
            ) td ON td.stock_point_transfer_id = t.id
            LEFT JOIN (
                SELECT grn_id AS transfer_id, SUM(qty) AS stock_in_qty
                FROM stock_in
                WHERE transaction_type = 'SST'
                  AND status = 'POST'
                GROUP BY grn_id
            ) si ON si.transfer_id = t.id
            LEFT JOIN (
                SELECT grn_id AS transfer_id, SUM(qty) AS stock_out_qty
                FROM stock_out
                WHERE transaction_type = 'SST'
                  AND status = 'POST'
                GROUP BY grn_id
            ) so ON so.transfer_id = t.id
            WHERE t.type = 'SST'
              AND t.status = 'POST'
            ORDER BY t.created_at DESC
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
