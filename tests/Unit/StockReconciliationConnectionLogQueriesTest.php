<?php

use App\Support\StockReconciliationConnectionQueries;

it('defines mysql stock log sql with required binds', function () {
    expect(StockReconciliationConnectionQueries::MYSQL_STOCK_LOGS)
        ->toContain('FROM stock_logs')
        ->toContain('im_sku_flat_table.ref_item_code = ?')
        ->toContain('stock_points.ref_stock_point_code = ?')
        ->toContain('stores.store_reference_code = ?')
        ->toContain('document_number AS scheme_docno');
});

it('defines pgsql stock log sql with like stockpoint bind', function () {
    expect(StockReconciliationConnectionQueries::PGSQL_STOCK_LOGS)
        ->toContain('FROM invstock')
        ->toContain('admsite_code = ?')
        ->toContain('icode = ?')
        ->toContain('psite_stockpoint_code LIKE ?')
        ->toContain('scheme_docno');
});
