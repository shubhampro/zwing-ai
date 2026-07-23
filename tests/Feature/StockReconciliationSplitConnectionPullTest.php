<?php

use App\Enums\ExternalQueryJobType;
use App\Jobs\PullErpStockFromConnectionJob;
use App\Jobs\PullZwingStockFromConnectionJob;
use App\Models\ExternalQueryLog;
use App\Models\Organization;
use App\Models\OrganizationDatabaseConnection;
use App\Models\StockReconSession;
use App\Models\User;
use App\Services\OrganizationDatabaseConnector;
use App\Services\StockReconciliationConnectionPuller;
use App\Support\StockReconciliationConnectionQueries;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

it('leaves session processing after zwing when erp job follows', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'vendor_id' => 11,
        'db_name' => 'zw_mn_11_demo',
    ]);

    $session = StockReconSession::query()->create([
        'user_id' => $user->id,
        'name' => 'Split pull',
        'v_id' => $organization->vendor_id,
        'source' => 'connection',
        'organization_id' => $organization->id,
        'zwing_file_name' => 'mysql_ssh',
        'erp_file_name' => 'pgsql connection',
        'status' => 'pending',
    ]);

    $connector = Mockery::mock(OrganizationDatabaseConnector::class);
    $connector->shouldReceive('openMysqlSshDatabase')->once()->andReturn('runtime_mysql');
    $connector->shouldReceive('eachRow')
        ->once()
        ->withArgs(function (string $runtime, string $sql, array $bindings, callable $callback): bool {
            $callback([
                'site_code' => 'S1',
                'barcode' => 'B1',
                'icode' => 'I1',
                'batch_no' => 'BN1',
                'sprefcode' => 'SP-1',
                'stock_point_name' => 'Floor',
                'qty' => 3,
            ]);

            return true;
        });
    $connector->shouldReceive('close')->once()->with('runtime_mysql');

    $job = new PullZwingStockFromConnectionJob(
        sessionId: $session->id,
        completeSession: false,
    );

    $job->handle($connector, app(StockReconciliationConnectionPuller::class));

    $session->refresh();

    expect($session->status)->toBe('processing')
        ->and($session->zwing_row_count)->toBe(1)
        ->and($session->reconciled_at)->toBeNull();
});

it('pulls erp rows and marks session completed', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'vendor_id' => 22,
        'db_name' => 'zw_mn_22_demo',
    ]);
    $pgsql = OrganizationDatabaseConnection::factory()->pgsql()->create([
        'organization_id' => $organization->id,
    ]);

    $session = StockReconSession::query()->create([
        'user_id' => $user->id,
        'name' => 'ERP pull',
        'v_id' => $organization->vendor_id,
        'source' => 'connection',
        'organization_id' => $organization->id,
        'pgsql_connection_id' => $pgsql->id,
        'erp_file_name' => 'pgsql connection',
        'status' => 'processing',
        'zwing_row_count' => 1,
    ]);

    $connector = Mockery::mock(OrganizationDatabaseConnector::class);
    $connector->shouldReceive('openMysqlSshDatabase')->once()->andReturn('runtime_mysql');
    $connector->shouldReceive('open')->once()->with(Mockery::on(
        fn ($connection) => $connection instanceof OrganizationDatabaseConnection
            && $connection->id === $pgsql->id
    ))->andReturn('runtime_pgsql');
    $connector->shouldReceive('eachRow')
        ->twice()
        ->withArgs(function (string $runtime, string $sql, array $bindings, callable $callback): bool {
            if ($runtime === 'runtime_mysql') {
                expect($sql)->toBe(StockReconciliationConnectionQueries::MYSQL_ACTIVE_SITE_CODES);
                $callback(['store_reference_code' => '701']);

                return true;
            }

            expect($runtime)->toBe('runtime_pgsql');
            $callback([
                'site_code' => '701',
                'barcode' => 'EB1',
                'icode' => 'EI1',
                'batch_no' => 'EBN1',
                'sprefcode' => 'SP-9',
                'stock_point_name' => 'WH',
                'qty' => 7,
            ]);

            return true;
        });
    $connector->shouldReceive('close')->once()->with('runtime_pgsql');
    $connector->shouldReceive('close')->once()->with('runtime_mysql');

    $job = new PullErpStockFromConnectionJob(
        sessionId: $session->id,
        pgsqlConnectionId: $pgsql->id,
    );

    $job->handle($connector, app(StockReconciliationConnectionPuller::class));

    $session->refresh();

    expect($session->status)->toBe('completed')
        ->and($session->erp_row_count)->toBe(1)
        ->and($session->reconciled_at)->not->toBeNull()
        ->and(DB::table('erp_stock_reconsile')->where('session_id', $session->id)->count())->toBe(1);
});

it('creates separate external query logs for zwing and erp pulls', function () {
    Bus::fake();

    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'db_name' => 'zw_mn_33_demo',
    ]);
    $pgsql = OrganizationDatabaseConnection::factory()->pgsql()->create([
        'organization_id' => $organization->id,
    ]);

    $this->actingAs($user)
        ->post(route('stock-transaction-reconciliation.connections'), [
            'organization_id' => $organization->id,
            'pgsql_connection_id' => $pgsql->id,
            'include_zwing' => true,
            'include_erp' => true,
        ])
        ->assertRedirect();

    $types = ExternalQueryLog::query()->pluck('job_type')->map->value->all();

    expect($types)->toEqualCanonicalizing([
        ExternalQueryJobType::PullStockZwing->value,
        ExternalQueryJobType::PullStockErp->value,
    ]);

    Bus::assertChained([
        PullZwingStockFromConnectionJob::class,
        PullErpStockFromConnectionJob::class,
    ]);
});
