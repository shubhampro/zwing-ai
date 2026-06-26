<?php

namespace App\Support;

class InvoiceReconciliationComparison
{
    public static function comparisonSql(): string
    {
        return <<<'SQL'
            SELECT
                z.invoice_id                          AS zwing_invoice_id,
                e.invoice_id                          AS erp_invoice_id,
                COALESCE(z.invoice_id, e.invoice_id)  AS invoice_id,
                z.ref_id                              AS zwing_ref_id,
                e.ref_id                              AS erp_ref_id,
                COALESCE(z.ref_id, e.ref_id)          AS ref_id,
                z.total_amount                        AS zwing_total_amount,
                e.total_amount                        AS erp_total_amount,
                z.status                              AS zwing_status,
                e.status                              AS erp_status,
                CASE
                    WHEN z.id IS NULL THEN 'erp_only'
                    WHEN e.id IS NULL THEN 'zwing_only'
                    WHEN z.total_amount = e.total_amount AND z.status = e.status THEN 'matched'
                    WHEN z.total_amount != e.total_amount THEN 'amount_mismatch'
                    ELSE 'status_mismatch'
                END AS match_status
            FROM zwing_invoice_reconsile z
            FULL OUTER JOIN erp_invoice_reconsile e
                ON  z.session_id = e.session_id
                AND z.ref_id = e.ref_id
            WHERE COALESCE(z.session_id, e.session_id) = ?
        SQL;
    }
}
