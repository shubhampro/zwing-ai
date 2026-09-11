<?php

use App\Support\ReportConsolidationQueries;

test('invoice query aggregates invoice details by order and uses max date', function () {
    $sql = ReportConsolidationQueries::INVOICE;

    expect($sql)
        ->toContain('invoice_id AS invoice_no')
        ->toContain('MAX(stores.name) AS store_name')
        ->toContain('MAX(invoice_details.date) AS date')
        ->toContain('SUM(COALESCE(invoice_details.total, 0)) + MAX(COALESCE(invoices.round_off, 0)) AS total')
        ->toContain('FROM invoice_details')
        ->toContain('LEFT JOIN invoices ON invoices.id = invoice_details.t_order_id')
        ->toContain('LEFT JOIN stores ON stores.store_id = invoices.store_id')
        ->toContain("invoices.status IN ('Success', 'Void')")
        ->toContain('DATE(invoice_details.date) BETWEEN ? AND ?')
        ->not->toContain('invoice_details.created_at')
        ->toContain('GROUP BY invoice_details.t_order_id')
        ->not->toContain('2026-08-01')
        ->and(ReportConsolidationQueries::bindings('2026-08-01', '2026-08-31'))
        ->toBe(['2026-08-01', '2026-08-31']);
});

test('mop query aggregates payments by invoice and uses max date', function () {
    $sql = ReportConsolidationQueries::MOP;

    expect($sql)
        ->toContain('payments.invoice_id AS invoice_no')
        ->toContain('MAX(stores.name) AS store_name')
        ->toContain('MAX(payments.date) AS date')
        ->toContain('SUM(payments.amount) AS total')
        ->toContain('LEFT JOIN invoices')
        ->toContain('LEFT JOIN stores ON stores.store_id = invoices.store_id')
        ->toContain("invoices.status IN ('Success', 'Void')")
        ->toContain("payments.is_remove = '0'")
        ->toContain('DATE(invoices.created_at) BETWEEN ? AND ?')
        ->toContain('GROUP BY invoices.invoice_id')
        ->not->toContain('2026-08-01');
});
