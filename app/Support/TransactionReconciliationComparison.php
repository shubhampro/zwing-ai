<?php

namespace App\Support;

class TransactionReconciliationComparison
{
    /**
     * @return list<string>
     */
    public static function mismatchMatchStatuses(): array
    {
        return [
            'code_mismatch',
            'type_mismatch',
            'amount_mismatch',
            'date_mismatch',
            'status_mismatch',
        ];
    }

    public static function mismatchMatchStatusesSqlList(): string
    {
        return "'".implode("', '", self::mismatchMatchStatuses())."'";
    }

    public static function comparisonSql(): string
    {
        return <<<'SQL'
            SELECT
                COALESCE(z.txn_id, e.txn_id) AS txn_id,
                z.code AS zwing_code,
                e.code AS erp_code,
                COALESCE(z.code, e.code) AS code,
                z.type AS zwing_type,
                e.type AS erp_type,
                z.status AS zwing_status,
                e.status AS erp_status,
                COALESCE(z.site_id, e.site_id) AS site_id,
                z.txn_date AS zwing_date,
                e.txn_date AS erp_date,
                z.amount AS zwing_amount,
                e.amount AS erp_amount,
                CASE
                    WHEN z.id IS NULL THEN 'packet_not_in_zwing'
                    WHEN e.id IS NULL THEN 'packet_not_in_erp'
                    WHEN z.code IS DISTINCT FROM e.code THEN 'code_mismatch'
                    WHEN z.type IS DISTINCT FROM e.type THEN 'type_mismatch'
                    WHEN z.amount IS NOT NULL AND e.amount IS NOT NULL AND z.amount IS DISTINCT FROM e.amount THEN 'amount_mismatch'
                    WHEN z.txn_date IS NOT NULL AND e.txn_date IS NOT NULL AND z.txn_date IS DISTINCT FROM e.txn_date THEN 'date_mismatch'
                    WHEN z.status IS DISTINCT FROM e.status THEN 'status_mismatch'
                    ELSE 'matched'
                END AS match_status
            FROM (
                SELECT id, txn_id, code, type, status, site_id, txn_date, amount
                FROM zwing_transaction_reconsile
                WHERE session_id = ?
            ) z
            FULL OUTER JOIN (
                SELECT id, txn_id, code, type, status, site_id, txn_date, amount
                FROM erp_transaction_reconsile
                WHERE session_id = ?
            ) e ON z.txn_id = e.txn_id
        SQL;
    }
}
