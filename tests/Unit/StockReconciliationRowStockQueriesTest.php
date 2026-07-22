<?php

use App\Support\StockReconciliationConnectionQueries;

it('defines row-scoped mysql stock query with site icode stockpoint and batch binds', function () {
    $sql = StockReconciliationConnectionQueries::MYSQL_STOCK_ROW;

    expect($sql)->toContain('store_reference_code = ?')
        ->and($sql)->toContain('ref_sku_code = ?')
        ->and($sql)->toContain('ref_stock_point_code = ?')
        ->and($sql)->toContain("COALESCE(batch.batch_no, '') = ?");
});

it('defines row-scoped pgsql stock query with site icode batch and stockpoint like', function () {
    $sql = StockReconciliationConnectionQueries::PGSQL_STOCK_ROW;

    expect($sql)->toContain('lv.sitecode = ?')
        ->and($sql)->toContain('lv.icode = ?')
        ->and($sql)->toContain("COALESCE(lv.batch_serial_no, '') = ?")
        ->and($sql)->toContain('lv.stockpointcode LIKE ?')
        ->and($sql)->toContain('ginview."lv_realtimestock_stkpoint"');
});
