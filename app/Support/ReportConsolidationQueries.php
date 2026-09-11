<?php

namespace App\Support;

final class ReportConsolidationQueries
{
    public const INVOICE = <<<'SQL'
SELECT
    invoice_id AS invoice_no,
    MAX(stores.name) AS store_name,
    MAX(invoice_details.date) AS date,
    SUM(COALESCE(invoice_details.total, 0)) + MAX(COALESCE(invoices.round_off, 0)) AS total
FROM invoice_details
LEFT JOIN invoices ON invoices.id = invoice_details.t_order_id
LEFT JOIN stores ON stores.store_id = invoices.store_id
WHERE DATE(invoice_details.date) BETWEEN ? AND ?
  AND invoices.status IN ('Success', 'Void')
GROUP BY invoice_details.t_order_id
SQL;

    public const MOP = <<<'SQL'
SELECT
    payments.invoice_id AS invoice_no,
    MAX(stores.name) AS store_name,
    MAX(payments.date) AS date,
    SUM(payments.amount) AS total
FROM payments
LEFT JOIN invoices ON payments.invoice_id = invoices.invoice_id
LEFT JOIN stores ON stores.store_id = invoices.store_id
WHERE DATE(invoices.created_at) BETWEEN ? AND ?
  AND invoices.status IN ('Success', 'Void')
  AND payments.is_remove = '0'
GROUP BY invoices.invoice_id
SQL;

    /**
     * @return list<string>
     */
    public static function bindings(string $dateFrom, string $dateTo): array
    {
        return [$dateFrom, $dateTo];
    }
}
