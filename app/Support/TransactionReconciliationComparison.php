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
                CASE
                    WHEN z.id IS NULL THEN 'packet_not_in_zwing'
                    WHEN e.id IS NULL THEN 'packet_not_in_erp'
                    WHEN z.code IS DISTINCT FROM e.code THEN 'code_mismatch'
                    WHEN z.type IS DISTINCT FROM e.type THEN 'type_mismatch'
                    WHEN z.status IS DISTINCT FROM e.status THEN 'status_mismatch'
                    ELSE 'matched'
                END AS match_status
            FROM (
                SELECT id, txn_id, code, type, status
                FROM zwing_transaction_reconsile
                WHERE session_id = ?
            ) z
            FULL OUTER JOIN (
                SELECT id, txn_id, code, type, status
                FROM erp_transaction_reconsile
                WHERE session_id = ?
            ) e ON z.txn_id = e.txn_id
        SQL;
    }
}
