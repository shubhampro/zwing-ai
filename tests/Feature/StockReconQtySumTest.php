<?php

use App\Models\Organization;
use App\Models\StockReconSession;
use App\Models\User;
use App\Services\OrganizationDatabaseConnector;
use App\Services\StockReconQtySumService;
use App\Support\StockReconciliationConnectionQueries;

it('resolves zwing keys and sums stock_logs and stock_point_summary qty', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'vendor_id' => 42,
        'db_name' => 'zw_mn_42_demo',
    ]);

    $session = StockReconSession::query()->create([
        'user_id' => $user->id,
        'name' => 'connection-qty-sum',
        'v_id' => $organization->vendor_id,
        'source' => 'connection',
        'organization_id' => $organization->id,
        'zwing_file_name' => 'mysql_ssh',
        'erp_file_name' => null,
        'status' => 'completed',
    ]);

    $connector = Mockery::mock(OrganizationDatabaseConnector::class);
    $connector->shouldReceive('openMysqlSshDatabase')
        ->once()
        ->with('zw_mn_42_demo')
        ->andReturn('runtime_mysql');

    $connector->shouldReceive('eachRow')
        ->times(5)
        ->andReturnUsing(function (string $runtime, string $sql, array $bindings, callable $callback): void {
            expect($runtime)->toBe('runtime_mysql');

            if ($sql === StockReconciliationConnectionQueries::MYSQL_RESOLVE_STORE_ID) {
                expect($bindings)->toBe(['702']);
                $callback(['store_id' => 11]);

                return;
            }

            if ($sql === StockReconciliationConnectionQueries::MYSQL_RESOLVE_STOCK_POINT_ID) {
                expect($bindings)->toBe(['QRILZ002-1', 11]);
                $callback(['id' => 22]);

                return;
            }

            if ($sql === StockReconciliationConnectionQueries::MYSQL_RESOLVE_SKU_CODE) {
                expect($bindings)->toBe(['MCV1454']);
                $callback(['sku_code' => 333]);

                return;
            }

            if ($sql === StockReconciliationConnectionQueries::MYSQL_STOCK_LOGS_QTY_SUM) {
                expect($bindings)->toBe([11, 22, 333]);
                $callback(['qty_sum' => 15]);

                return;
            }

            expect($sql)->toBe(StockReconciliationConnectionQueries::MYSQL_STOCK_POINT_SUMMARY_QTY_SUM)
                ->and($bindings)->toBe([11, 22, 333]);
            $callback(['qty_sum' => 12]);
        });

    $connector->shouldReceive('close')->once()->with('runtime_mysql');

    $this->app->instance(OrganizationDatabaseConnector::class, $connector);

    $result = app(StockReconQtySumService::class)->forSku(
        session: $session,
        siteCode: '702',
        icode: 'MCV1454',
        sprefcode: 'QRILZ002-1',
    );

    expect($result['store_id'])->toBe(11)
        ->and($result['stock_point_id'])->toBe(22)
        ->and($result['sku_code'])->toBe(333)
        ->and($result['stock_logs_qty_sum'])->toBe(15.0)
        ->and($result['stock_point_summary_qty_sum'])->toBe(12.0)
        ->and($result['query_ms'])->toBeInt()
        ->and($result['query_ms'])->toBeGreaterThanOrEqual(0);
});

it('authenticated user can fetch connection qty sums via endpoint', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'vendor_id' => 88,
        'db_name' => 'zw_mn_88_demo',
    ]);

    $session = StockReconSession::query()->create([
        'user_id' => $user->id,
        'name' => 'connection-qty-sum-api',
        'v_id' => $organization->vendor_id,
        'source' => 'connection',
        'organization_id' => $organization->id,
        'zwing_file_name' => 'mysql_ssh',
        'erp_file_name' => null,
        'status' => 'completed',
    ]);

    $connector = Mockery::mock(OrganizationDatabaseConnector::class);
    $connector->shouldReceive('openMysqlSshDatabase')->once()->andReturn('runtime_mysql');
    $connector->shouldReceive('eachRow')
        ->times(5)
        ->andReturnUsing(function (string $runtime, string $sql, array $bindings, callable $callback): void {
            match ($sql) {
                StockReconciliationConnectionQueries::MYSQL_RESOLVE_STORE_ID => $callback(['store_id' => 1]),
                StockReconciliationConnectionQueries::MYSQL_RESOLVE_STOCK_POINT_ID => $callback(['id' => 2]),
                StockReconciliationConnectionQueries::MYSQL_RESOLVE_SKU_CODE => $callback(['sku_code' => 3]),
                StockReconciliationConnectionQueries::MYSQL_STOCK_LOGS_QTY_SUM => $callback(['qty_sum' => 9]),
                StockReconciliationConnectionQueries::MYSQL_STOCK_POINT_SUMMARY_QTY_SUM => $callback(['qty_sum' => 7]),
                default => throw new RuntimeException('Unexpected SQL'),
            };
        });
    $connector->shouldReceive('close')->once()->with('runtime_mysql');

    $this->app->instance(OrganizationDatabaseConnector::class, $connector);

    $this->actingAs($user)
        ->getJson(route('stock-transaction-reconciliation.report.qty-sums', $session).'?'.http_build_query([
            'site_code' => '702',
            'icode' => 'MCV1454',
            'sprefcode' => 'QRILZ002-1',
        ]))
        ->assertStatus(202)
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('job_type', 'qty_sums')
        ->assertJsonPath('result.store_id', 1)
        ->assertJsonPath('result.stock_point_id', 2)
        ->assertJsonPath('result.sku_code', 3)
        ->assertJsonPath('result.stock_logs_qty_sum', 9)
        ->assertJsonPath('result.stock_point_summary_qty_sum', 7);
});

it('rejects qty sums for csv sessions', function () {
    $user = User::factory()->create();
    $session = StockReconSession::query()->create([
        'user_id' => $user->id,
        'name' => 'csv-qty-sum',
        'v_id' => 1,
        'source' => 'csv',
        'status' => 'completed',
    ]);

    $this->actingAs($user)
        ->getJson(route('stock-transaction-reconciliation.report.qty-sums', $session).'?'.http_build_query([
            'site_code' => '702',
            'icode' => 'MCV1454',
            'sprefcode' => 'QRILZ002-1',
        ]))
        ->assertStatus(422);
});
