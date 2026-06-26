<?php

namespace App\Support;

class InvoiceReconciliationComparison
{
    public static function comparisonSql(): string
    {
        $separator = InvoiceRefId::SEPARATOR;

        return <<<SQL
            WITH zwing_refs AS (
                SELECT
                    z.id,
                    z.session_id,
                    z.invoice_id,
                    trim(z_part.val) AS ref_id,
                    z.total_amount,
                    z.status
                FROM zwing_invoice_reconsile z
                CROSS JOIN LATERAL unnest(string_to_array(z.ref_id, '{$separator}')) AS z_part(val)
                WHERE trim(z_part.val) <> ''
            ),
            erp_refs AS (
                SELECT
                    e.id,
                    e.session_id,
                    e.invoice_id,
                    trim(e_part.val) AS ref_id,
                    e.total_amount,
                    e.status
                FROM erp_invoice_reconsile e
                CROSS JOIN LATERAL unnest(string_to_array(e.ref_id, '{$separator}')) AS e_part(val)
                WHERE trim(e_part.val) <> ''
            ),
            erp_invoices AS (
                SELECT DISTINCT session_id, invoice_id
                FROM erp_invoice_reconsile
            ),
            zwing_invoices AS (
                SELECT DISTINCT session_id, invoice_id
                FROM zwing_invoice_reconsile
            )
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
                    WHEN z.id IS NULL THEN
                        CASE
                            WHEN EXISTS (
                                SELECT 1
                                FROM zwing_invoices zi
                                WHERE zi.session_id = e.session_id
                                  AND zi.invoice_id = e.invoice_id
                            ) THEN 'ref_id_not_in_zwing'
                            ELSE 'invoice_not_in_zwing'
                        END
                    WHEN e.id IS NULL THEN
                        CASE
                            WHEN EXISTS (
                                SELECT 1
                                FROM erp_invoices ei
                                WHERE ei.session_id = z.session_id
                                  AND ei.invoice_id = z.invoice_id
                            ) THEN 'ref_id_not_in_erp'
                            ELSE 'invoice_not_in_erp'
                        END
                    WHEN z.total_amount = e.total_amount AND z.status = e.status THEN 'matched'
                    WHEN z.total_amount != e.total_amount THEN 'amount_mismatch'
                    ELSE 'status_mismatch'
                END AS match_status
            FROM zwing_refs z
            FULL OUTER JOIN erp_refs e
                ON  z.session_id = e.session_id
                AND z.invoice_id = e.invoice_id
                AND z.ref_id = e.ref_id
            WHERE COALESCE(z.session_id, e.session_id) = ?
        SQL;
    }
}
