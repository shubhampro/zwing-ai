<?php

namespace App\Support;

class InvoiceReconciliationComparison
{
    /**
     * @return list<string>
     */
    public static function mismatchMatchStatuses(): array
    {
        return [
            'mop_ref_mismatch',
            'amount_mismatch',
            'status_mismatch',
        ];
    }

    public static function mismatchMatchStatusesSqlList(): string
    {
        return "'".implode("', '", self::mismatchMatchStatuses())."'";
    }

    public static function mopRefMismatchMatchStatusSqlList(): string
    {
        return "'mop_ref_mismatch'";
    }

    public static function comparisonSql(): string
    {
        $separator = InvoiceRefId::SEPARATOR;

        return <<<SQL
            WITH zwing_parts AS (
                SELECT
                    z.session_id,
                    z.invoice_id,
                    z.total_amount,
                    z.status,
                    trim(z_part.val) AS ref_part
                FROM zwing_invoice_reconsile z
                CROSS JOIN LATERAL unnest(string_to_array(z.ref_id, '{$separator}')) AS z_part(val)
                WHERE trim(z_part.val) <> ''
            ),
            erp_parts AS (
                SELECT
                    e.session_id,
                    e.invoice_id,
                    e.total_amount,
                    e.status,
                    trim(e_part.val) AS ref_part
                FROM erp_invoice_reconsile e
                CROSS JOIN LATERAL unnest(string_to_array(e.ref_id, '{$separator}')) AS e_part(val)
                WHERE trim(e_part.val) <> ''
            ),
            zwing_agg AS (
                SELECT
                    session_id,
                    invoice_id,
                    string_agg(DISTINCT ref_part, '{$separator}' ORDER BY ref_part) AS mop_ref_id,
                    MIN(total_amount) AS total_amount,
                    MIN(status) AS status
                FROM zwing_parts
                GROUP BY session_id, invoice_id
            ),
            erp_agg AS (
                SELECT
                    session_id,
                    invoice_id,
                    string_agg(DISTINCT ref_part, '{$separator}' ORDER BY ref_part) AS mop_ref_id,
                    MIN(total_amount) AS total_amount,
                    MIN(status) AS status
                FROM erp_parts
                GROUP BY session_id, invoice_id
            )
            SELECT
                z.invoice_id                          AS zwing_invoice_id,
                e.invoice_id                          AS erp_invoice_id,
                COALESCE(z.invoice_id, e.invoice_id)  AS invoice_id,
                z.mop_ref_id                          AS zwing_ref_id,
                e.mop_ref_id                          AS erp_ref_id,
                COALESCE(z.mop_ref_id, e.mop_ref_id)  AS ref_id,
                z.total_amount                        AS zwing_total_amount,
                e.total_amount                        AS erp_total_amount,
                z.status                              AS zwing_status,
                e.status                              AS erp_status,
                CASE
                    WHEN z.invoice_id IS NULL THEN 'invoice_not_in_zwing'
                    WHEN e.invoice_id IS NULL THEN 'invoice_not_in_erp'
                    WHEN z.mop_ref_id IS DISTINCT FROM e.mop_ref_id THEN 'mop_ref_mismatch'
                    WHEN z.total_amount = e.total_amount AND z.status IS NOT DISTINCT FROM e.status THEN 'matched'
                    WHEN z.total_amount != e.total_amount THEN 'amount_mismatch'
                    ELSE 'status_mismatch'
                END AS match_status
            FROM zwing_agg z
            FULL OUTER JOIN erp_agg e
                ON  z.session_id = e.session_id
                AND z.invoice_id = e.invoice_id
            WHERE COALESCE(z.session_id, e.session_id) = ?
        SQL;
    }
}
