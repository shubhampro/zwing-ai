<?php

use Illuminate\Support\Facades\Schema;

test('stock_recon_sessions header table exists with expected columns', function () {
    expect(Schema::hasTable('stock_recon_sessions'))->toBeTrue();

    $columns = [
        'id',
        'user_id',
        'v_id',
        'zwing_file_name',
        'erp_file_name',
        'zwing_row_count',
        'erp_row_count',
        'status',
        'reconciled_at',
        'created_at',
        'updated_at',
    ];

    foreach ($columns as $column) {
        expect(Schema::hasColumn('stock_recon_sessions', $column))
            ->toBeTrue("stock_recon_sessions is missing column {$column}");
    }
});

test('zwing and erp stock reconsile tables exist with expected columns', function () {
    expect(Schema::hasTable('zwing_stock_reconsile'))->toBeTrue();
    expect(Schema::hasTable('erp_stock_reconsile'))->toBeTrue();

    $columns = [
        'id',
        'session_id',
        'batch_no',
        'v_id',
        'barcode',
        'icode',
        'stock_point_name',
        'qty',
        'created_at',
        'updated_at',
    ];

    foreach ($columns as $column) {
        expect(Schema::hasColumn('zwing_stock_reconsile', $column))
            ->toBeTrue("zwing_stock_reconsile is missing column {$column}");
        expect(Schema::hasColumn('erp_stock_reconsile', $column))
            ->toBeTrue("erp_stock_reconsile is missing column {$column}");
    }
});
