<?php

use App\Support\StockReconciliationConnectionQueries;

it('defines mysql resolve and qty sum sql for zwing keys', function () {
    expect(StockReconciliationConnectionQueries::MYSQL_RESOLVE_STORE_ID)
        ->toContain('FROM stores')
        ->toContain('store_reference_code = ?');

    expect(StockReconciliationConnectionQueries::MYSQL_RESOLVE_STOCK_POINT_ID)
        ->toContain('FROM stock_points')
        ->toContain('ref_stock_point_code = ?')
        ->toContain('store_id = ?');

    expect(StockReconciliationConnectionQueries::MYSQL_RESOLVE_SKU_CODE)
        ->toContain('FROM im_sku_flat_table')
        ->toContain('ref_item_code = ?')
        ->toContain('sku_code');

    expect(StockReconciliationConnectionQueries::MYSQL_STOCK_LOGS_QTY_SUM)
        ->toContain('FROM stock_logs')
        ->toContain('COALESCE(SUM(qty), 0) AS qty_sum')
        ->toContain('store_id = ?')
        ->toContain('stock_point_id = ?')
        ->toContain('sku_code = ?');

    expect(StockReconciliationConnectionQueries::MYSQL_STOCK_POINT_SUMMARY_QTY_SUM)
        ->toContain('FROM stock_point_summary')
        ->toContain('COALESCE(SUM(qty), 0) AS qty_sum')
        ->toContain('store_id = ?')
        ->toContain('stock_point_id = ?')
        ->toContain('sku_code = ?');
});
