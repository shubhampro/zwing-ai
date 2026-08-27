<?php

namespace App\Support;

final class InvoiceReconciliationQueries
{
    public const MYSQL = <<<'SQL'
SELECT
    invoices.invoice_id AS invoice_id,
    '0' AS ref_id,
    IF(
        invoices.transaction_type = 'return',
        -invoices.total,
        invoices.total
    ) AS total_amount,
    CASE
        WHEN invoices.status = 'success' THEN 'Success'
        WHEN invoices.status = 'void' THEN 'Void'
        ELSE invoices.status
    END AS status
FROM invoices
WHERE invoices.status IN ('void', 'success')
  AND DATE(invoices.created_at) BETWEEN ? AND ?
SQL;

    public const PGSQL = <<<'SQL'
SELECT
    s.scheme_docno AS invoice_id,
    '0' AS ref_id,
    SUM(s.netpayable) AS total_amount,
    'Success' AS status
FROM salcsmain s
LEFT JOIN main.hrdemp hd ON hd.ecode = s.release_ecode
WHERE hd.fname LIKE '%ZPOS%'
  AND DATE(s.csdate) BETWEEN ? AND ?
GROUP BY
    s.scheme_docno

UNION ALL

SELECT
    ss.scheme_docno AS invoice_id,
    '0' AS ref_id,
    SUM(ss.netpayable) AS total_amount,
    'Success' AS status
FROM salssmain ss
LEFT JOIN main.hrdemp hd ON hd.ecode = ss.last_access_ecode
WHERE hd.fname LIKE '%ZPOS%'
  AND DATE(ss.ssdate) BETWEEN ? AND ?
GROUP BY
    ss.scheme_docno

UNION ALL

SELECT
    sd.scheme_docno AS invoice_id,
    '0' AS ref_id,
    SUM(sd.netpayable) AS total_amount,
    'Void' AS status
FROM salcsmain_deleted sd
LEFT JOIN main.hrdemp hd ON hd.ecode = sd.release_ecode
WHERE hd.fname LIKE '%ZPOS%'
  AND DATE(sd.csdate) BETWEEN ? AND ?
  AND NOT EXISTS (
      SELECT 1
      FROM salcsmain s
      LEFT JOIN main.hrdemp hd ON hd.ecode = s.release_ecode
      WHERE hd.fname LIKE '%ZPOS%'
        AND s.scheme_docno = sd.scheme_docno
        AND DATE(s.csdate) BETWEEN ? AND ?
  )
GROUP BY
    sd.scheme_docno
SQL;

    /**
     * @return list<string>
     */
    public static function mysqlBindings(string $dateFrom, string $dateTo): array
    {
        return self::repeatDatePair($dateFrom, $dateTo, 1);
    }

    /**
     * @return list<string>
     */
    public static function pgsqlBindings(string $dateFrom, string $dateTo): array
    {
        return self::repeatDatePair($dateFrom, $dateTo, 4);
    }

    /**
     * @return list<string>
     */
    private static function repeatDatePair(string $dateFrom, string $dateTo, int $pairs): array
    {
        $bindings = [];

        for ($i = 0; $i < $pairs; $i++) {
            $bindings[] = $dateFrom;
            $bindings[] = $dateTo;
        }

        return $bindings;
    }
}
