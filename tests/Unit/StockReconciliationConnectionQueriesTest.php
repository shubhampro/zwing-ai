<?php

use App\Support\StockReconciliationConnectionQueries;

it('builds pgsql stock sql with site placeholders', function () {
    [$sql, $bindings] = StockReconciliationConnectionQueries::pgsqlStock(['701', '711']);

    expect($sql)->toContain('WHERE lv.sitecode IN (?, ?)')
        ->and($sql)->toContain('ginview."lv_realtimestock_stkpoint"')
        ->and($sql)->toContain('LEFT JOIN main.invitem')
        ->and($bindings)->toBe(['701', '711']);
});

it('rejects empty site list for pgsql stock query', function () {
    StockReconciliationConnectionQueries::pgsqlStock([]);
})->throws(InvalidArgumentException::class);
