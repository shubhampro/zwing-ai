<?php

use App\Jobs\PullStockReconciliationFromConnections;
use App\Models\Organization;
use App\Models\StockReconSession;
use App\Models\User;
use App\Services\OrganizationDatabaseConnector;
use App\Support\StockReconciliationConnectionQueries;
use Illuminate\Support\Facades\DB;

it('pulls zwing rows through mysql_ssh connector and marks session completed', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'vendor_id' => 88,
        'db_name' => 'zw_mn_88_demo',
    ]);

    $session = StockReconSession::query()->create([
        'user_id' => $user->id,
        'name' => 'Connection pull',
        'v_id' => $organization->vendor_id,
        'source' => 'connection',
        'organization_id' => $organization->id,
        'zwing_file_name' => 'mysql_ssh',
        'status' => 'pending',
    ]);

    $connector = Mockery::mock(OrganizationDatabaseConnector::class);
    $connector->shouldReceive('openMysqlSshDatabase')
        ->once()
        ->with('zw_mn_88_demo')
        ->andReturn('runtime_mysql');
    $connector->shouldReceive('eachRow')
        ->once()
        ->withArgs(function (string $runtime, string $sql, array $bindings, callable $callback): bool {
            expect($runtime)->toBe('runtime_mysql')
                ->and($sql)->toBe(StockReconciliationConnectionQueries::MYSQL_STOCK)
                ->and($bindings)->toBe([]);

            $callback([
                'site_code' => 'S1',
                'barcode' => 'B1',
                'icode' => 'I1',
                'batch_no' => 'BN1',
                'sprefcode' => 'SP-2',
                'stock_point_name' => 'Floor',
                'qty' => 12,
            ]);
            $callback([
                'site_code' => '',
                'barcode' => 'B2',
                'icode' => 'I2',
                'batch_no' => '',
                'sprefcode' => '',
                'stock_point_name' => 'Floor',
                'qty' => 1,
            ]);

            return true;
        });
    $connector->shouldReceive('close')->once()->with('runtime_mysql');

    $job = new PullStockReconciliationFromConnections(
        sessionId: $session->id,
        pgsqlConnectionId: null,
        includeZwing: true,
        includeErp: false,
    );

    $job->handle($connector);

    $session->refresh();

    expect($session->status)->toBe('completed')
        ->and($session->zwing_processed_rows)->toBe(1)
        ->and($session->zwing_skipped_rows)->toBe(1)
        ->and(DB::table('zwing_stock_reconsile')->where('session_id', $session->id)->count())->toBe(1)
        ->and((int) DB::table('zwing_stock_reconsile')->where('session_id', $session->id)->value('sprefcode'))->toBe(2);
});
