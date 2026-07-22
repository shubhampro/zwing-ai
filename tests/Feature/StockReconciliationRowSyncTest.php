<?php

use App\Models\Organization;
use App\Models\OrganizationDatabaseConnection;
use App\Models\StockReconSession;
use App\Models\User;
use App\Services\OrganizationDatabaseConnector;
use App\Services\StockReconRowSyncService;
use App\Support\StockReconciliationConnectionQueries;
use Illuminate\Support\Facades\DB;

function seedReconSide(
    string $table,
    int $sessionId,
    int $vId,
    string $siteCode,
    string $icode,
    string $batchNo,
    int $sprefcode,
    string $stockPointName,
    float $qty,
): void {
    DB::table($table)->insert([
        'session_id' => $sessionId,
        'batch_no' => $batchNo,
        'v_id' => $vId,
        'barcode' => 'BAR-'.$icode,
        'icode' => $icode,
        'stock_point_name' => $stockPointName,
        'site_code' => $siteCode,
        'sprefcode' => $sprefcode,
        'qty' => $qty,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('syncs only the requested comparison key from both databases', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'vendor_id' => 42,
        'db_name' => 'zw_mn_42_demo',
    ]);
    $pgsql = OrganizationDatabaseConnection::factory()->pgsql()->create([
        'organization_id' => $organization->id,
    ]);

    $session = StockReconSession::query()->create([
        'user_id' => $user->id,
        'name' => 'row-sync',
        'v_id' => $organization->vendor_id,
        'source' => 'connection',
        'organization_id' => $organization->id,
        'pgsql_connection_id' => $pgsql->id,
        'zwing_file_name' => 'mysql_ssh',
        'erp_file_name' => 'pgsql connection',
        'status' => 'completed',
    ]);

    seedReconSide('zwing_stock_reconsile', $session->id, $session->v_id, '702', 'MCV1454', '', 1, 'Floor', 5);
    seedReconSide('erp_stock_reconsile', $session->id, $session->v_id, '702', 'MCV1454', '', 1, 'Floor', 8);
    seedReconSide('zwing_stock_reconsile', $session->id, $session->v_id, '702', 'OTHER1', '', 1, 'Floor', 99);
    seedReconSide('erp_stock_reconsile', $session->id, $session->v_id, '702', 'OTHER1', '', 1, 'Floor', 99);

    $connector = Mockery::mock(OrganizationDatabaseConnector::class);
    $connector->shouldReceive('openMysqlSshDatabase')
        ->once()
        ->with('zw_mn_42_demo')
        ->andReturn('runtime_mysql');
    $connector->shouldReceive('open')
        ->once()
        ->with(Mockery::on(fn ($connection) => $connection->id === $pgsql->id))
        ->andReturn('runtime_pgsql');
    $connector->shouldReceive('eachRow')
        ->twice()
        ->andReturnUsing(function (string $runtime, string $sql, array $bindings, callable $callback): void {
            if ($runtime === 'runtime_mysql') {
                expect($sql)->toBe(StockReconciliationConnectionQueries::MYSQL_STOCK_ROW)
                    ->and($bindings)->toBe(['702', 'MCV1454', 1, '']);

                $callback([
                    'site_code' => '702',
                    'barcode' => 'BAR-NEW',
                    'icode' => 'MCV1454',
                    'batch_no' => '',
                    'sprefcode' => 1,
                    'stock_point_name' => 'Floor',
                    'qty' => 10,
                ]);

                return;
            }

            expect($runtime)->toBe('runtime_pgsql')
                ->and($sql)->toBe(StockReconciliationConnectionQueries::PGSQL_STOCK_ROW)
                ->and($bindings)->toBe(['702', 'MCV1454', '', '%1%']);

            $callback([
                'site_code' => '702',
                'barcode' => 'BAR-NEW',
                'icode' => 'MCV1454',
                'batch_no' => '',
                'sprefcode' => 'QRILZ002-1',
                'stock_point_name' => 'Floor',
                'qty' => 10,
            ]);
        });
    $connector->shouldReceive('close')->once()->with('runtime_mysql');
    $connector->shouldReceive('close')->once()->with('runtime_pgsql');

    $this->app->instance(OrganizationDatabaseConnector::class, $connector);

    $result = app(StockReconRowSyncService::class)->sync(
        session: $session,
        siteCode: '702',
        icode: 'MCV1454',
        batchNo: '',
        sprefcode: 'QRILZ002-1',
    );

    expect($result['removed'])->toBeFalse()
        ->and($result['zwing_qty'])->toBe(10.0)
        ->and($result['erp_qty'])->toBe(10.0)
        ->and($result['match_status'])->toBe('matched')
        ->and($result['zwing_query_ms'])->toBeInt()->toBeGreaterThanOrEqual(0)
        ->and($result['erp_query_ms'])->toBeInt()->toBeGreaterThanOrEqual(0);

    expect(DB::table('zwing_stock_reconsile')->where('session_id', $session->id)->where('icode', 'MCV1454')->value('qty'))
        ->toEqual(10);
    expect(DB::table('erp_stock_reconsile')->where('session_id', $session->id)->where('icode', 'MCV1454')->value('qty'))
        ->toEqual(10);
    expect(DB::table('zwing_stock_reconsile')->where('session_id', $session->id)->where('icode', 'OTHER1')->value('qty'))
        ->toEqual(99);
    expect(DB::table('erp_stock_reconsile')->where('session_id', $session->id)->where('icode', 'OTHER1')->value('qty'))
        ->toEqual(99);
});

it('deletes only the synced key when remote stock is gone', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'vendor_id' => 55,
        'db_name' => 'zw_mn_55_demo',
    ]);
    $pgsql = OrganizationDatabaseConnection::factory()->pgsql()->create([
        'organization_id' => $organization->id,
    ]);

    $session = StockReconSession::query()->create([
        'user_id' => $user->id,
        'name' => 'row-sync-delete',
        'v_id' => $organization->vendor_id,
        'source' => 'connection',
        'organization_id' => $organization->id,
        'pgsql_connection_id' => $pgsql->id,
        'zwing_file_name' => 'mysql_ssh',
        'erp_file_name' => 'pgsql connection',
        'status' => 'completed',
    ]);

    seedReconSide('zwing_stock_reconsile', $session->id, $session->v_id, '702', 'GONE1', '', 1, 'Floor', 4);
    seedReconSide('erp_stock_reconsile', $session->id, $session->v_id, '702', 'GONE1', '', 1, 'Floor', 4);
    seedReconSide('zwing_stock_reconsile', $session->id, $session->v_id, '702', 'KEEP1', '', 1, 'Floor', 7);

    $connector = Mockery::mock(OrganizationDatabaseConnector::class);
    $connector->shouldReceive('openMysqlSshDatabase')->once()->andReturn('runtime_mysql');
    $connector->shouldReceive('open')->once()->andReturn('runtime_pgsql');
    $connector->shouldReceive('eachRow')->twice()->andReturnUsing(function (): void {
        // no remote rows
    });
    $connector->shouldReceive('close')->twice();

    $this->app->instance(OrganizationDatabaseConnector::class, $connector);

    $result = app(StockReconRowSyncService::class)->sync(
        session: $session,
        siteCode: '702',
        icode: 'GONE1',
        batchNo: '',
        sprefcode: '1',
    );

    expect($result['removed'])->toBeTrue()
        ->and($result['match_status'])->toBeNull();

    expect(DB::table('zwing_stock_reconsile')->where('session_id', $session->id)->where('icode', 'GONE1')->exists())
        ->toBeFalse();
    expect(DB::table('erp_stock_reconsile')->where('session_id', $session->id)->where('icode', 'GONE1')->exists())
        ->toBeFalse();
    expect(DB::table('zwing_stock_reconsile')->where('session_id', $session->id)->where('icode', 'KEEP1')->value('qty'))
        ->toEqual(7);
});

it('rejects sync for csv sessions via endpoint', function () {
    $user = User::factory()->create();
    $session = StockReconSession::query()->create([
        'user_id' => $user->id,
        'name' => 'csv-session',
        'v_id' => 1,
        'source' => 'csv',
        'status' => 'completed',
    ]);

    $this->actingAs($user)
        ->postJson(route('stock-transaction-reconciliation.report.sync-row', $session), [
            'site_code' => '702',
            'icode' => 'MCV1454',
            'batch_no' => '',
            'sprefcode' => '1',
        ])
        ->assertForbidden();
});

it('syncs a row through the report endpoint', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'vendor_id' => 77,
        'db_name' => 'zw_mn_77_demo',
    ]);
    $pgsql = OrganizationDatabaseConnection::factory()->pgsql()->create([
        'organization_id' => $organization->id,
    ]);

    $session = StockReconSession::query()->create([
        'user_id' => $user->id,
        'name' => 'row-sync-api',
        'v_id' => $organization->vendor_id,
        'source' => 'connection',
        'organization_id' => $organization->id,
        'pgsql_connection_id' => $pgsql->id,
        'zwing_file_name' => 'mysql_ssh',
        'erp_file_name' => null,
        'status' => 'completed',
    ]);

    seedReconSide('zwing_stock_reconsile', $session->id, $session->v_id, '702', 'MCV1454', 'B1', 2, 'Back', 3);

    $connector = Mockery::mock(OrganizationDatabaseConnector::class);
    $connector->shouldReceive('openMysqlSshDatabase')->once()->andReturn('runtime_mysql');
    $connector->shouldReceive('eachRow')
        ->once()
        ->withArgs(function (string $runtime, string $sql, array $bindings, callable $callback): bool {
            expect($bindings)->toBe(['702', 'MCV1454', 2, 'B1']);
            $callback([
                'site_code' => '702',
                'barcode' => 'X',
                'icode' => 'MCV1454',
                'batch_no' => 'B1',
                'sprefcode' => 2,
                'stock_point_name' => 'Back',
                'qty' => 11,
            ]);

            return true;
        });
    $connector->shouldReceive('close')->once()->with('runtime_mysql');

    $this->app->instance(OrganizationDatabaseConnector::class, $connector);

    $this->actingAs($user)
        ->postJson(route('stock-transaction-reconciliation.report.sync-row', $session), [
            'site_code' => '702',
            'icode' => 'MCV1454',
            'batch_no' => 'B1',
            'sprefcode' => '2',
        ])
        ->assertStatus(202)
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('job_type', 'sync_row')
        ->assertJsonPath('result.zwing_qty', 11)
        ->assertJsonPath('result.erp_qty', null)
        ->assertJsonPath('result.match_status', 'zwing_only')
        ->assertJsonPath('result.removed', false);

    expect(DB::table('zwing_stock_reconsile')->where('session_id', $session->id)->where('icode', 'MCV1454')->value('qty'))
        ->toEqual(11);
});
