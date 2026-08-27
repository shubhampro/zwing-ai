<?php

use App\Support\InvoiceReconciliationQueries;

test('zwing invoice query uses date bindings and maps required columns', function () {
    $sql = InvoiceReconciliationQueries::MYSQL;

    expect($sql)
        ->toContain('invoices.invoice_id AS invoice_id')
        ->toContain("'0' AS ref_id")
        ->not->toContain('GROUP_CONCAT')
        ->not->toContain('mops')
        ->not->toContain('payments')
        ->toContain('AS total_amount')
        ->toContain("WHEN invoices.status = 'success' THEN 'Success'")
        ->toContain("WHEN invoices.status = 'void' THEN 'Void'")
        ->toContain('DATE(invoices.created_at) BETWEEN ? AND ?')
        ->not->toContain('2026-01-01')
        ->and(InvoiceReconciliationQueries::mysqlBindings('2026-06-01', '2026-08-27'))
        ->toBe(['2026-06-01', '2026-08-27']);
});

test('erp invoice query keeps four date pairs and no hardcoded range', function () {
    $sql = InvoiceReconciliationQueries::PGSQL;

    expect($sql)
        ->toContain('s.scheme_docno AS invoice_id')
        ->toContain("'0' AS ref_id")
        ->not->toContain('salcsmop')
        ->not->toContain('STRING_AGG')
        ->toContain("'Success' AS status")
        ->toContain("'Void' AS status")
        ->toContain('FROM salcsmain')
        ->toContain('FROM salssmain')
        ->toContain('FROM salcsmain_deleted')
        ->toContain('release_ecode = 62998')
        ->not->toContain('2026-01-01')
        ->not->toContain('2026-08-27')
        ->and(substr_count($sql, 'BETWEEN ? AND ?'))->toBe(4)
        ->and(InvoiceReconciliationQueries::pgsqlBindings('2026-01-01', '2026-08-27'))
        ->toHaveCount(8)
        ->toBe([
            '2026-01-01', '2026-08-27',
            '2026-01-01', '2026-08-27',
            '2026-01-01', '2026-08-27',
            '2026-01-01', '2026-08-27',
        ]);
});
