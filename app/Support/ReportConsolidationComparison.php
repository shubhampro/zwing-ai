<?php

namespace App\Support;

class ReportConsolidationComparison
{
    /**
     * @return list<string>
     */
    public static function mismatchMatchStatuses(): array
    {
        return [
            'amount_mismatch',
        ];
    }

    public static function mismatchMatchStatusesSqlList(): string
    {
        return "'".implode("', '", self::mismatchMatchStatuses())."'";
    }

    public static function comparisonSql(): string
    {
        return <<<'SQL'
            WITH invoice_agg AS (
                SELECT
                    session_id,
                    invoice_no,
                    MIN(store_name) AS store_name,
                    MIN(date) AS date,
                    MIN(total) AS total
                FROM report_recon_invoices
                GROUP BY session_id, invoice_no
            ),
            mop_agg AS (
                SELECT
                    session_id,
                    invoice_no,
                    MIN(store_name) AS store_name,
                    MIN(date) AS date,
                    MIN(total) AS total
                FROM report_recon_mops
                GROUP BY session_id, invoice_no
            )
            SELECT
                i.invoice_no                         AS invoice_invoice_no,
                m.invoice_no                         AS mop_invoice_no,
                COALESCE(i.invoice_no, m.invoice_no) AS invoice_no,
                COALESCE(i.store_name, m.store_name) AS store_name,
                i.date                               AS invoice_date,
                m.date                               AS mop_date,
                i.total                              AS invoice_total,
                m.total                              AS mop_total,
                CASE
                    WHEN i.invoice_no IS NULL THEN 'mop_only'
                    WHEN m.invoice_no IS NULL THEN 'invoice_only'
                    WHEN i.total = m.total THEN 'matched'
                    ELSE 'amount_mismatch'
                END AS match_status
            FROM invoice_agg i
            FULL OUTER JOIN mop_agg m
                ON  i.session_id = m.session_id
                AND i.invoice_no = m.invoice_no
            WHERE COALESCE(i.session_id, m.session_id) = ?
        SQL;
    }
}
