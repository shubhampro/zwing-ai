<?php

use App\Models\Organization;
use App\Models\OrganizationDatabaseConnection;
use App\Models\StockReconSession;
use App\Models\User;
use App\Services\OrganizationDatabaseConnector;
use App\Services\StockReconLogDetailService;
use App\Support\StockReconciliationConnectionQueries;
use Inertia\Testing\AssertableInertia as Assert;

it('fetches live connection logs and matches zwing to erp on doc_no + qty', function () {
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
        'name' => 'connection-log-detail',
        'v_id' => $organization->vendor_id,
        'source' => 'connection',
        'organization_id' => $organization->id,
        'pgsql_connection_id' => $pgsql->id,
        'zwing_file_name' => 'mysql_ssh',
        'erp_file_name' => 'pgsql connection',
        'status' => 'completed',
    ]);

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
                expect($sql)->toBe(StockReconciliationConnectionQueries::MYSQL_STOCK_LOGS)
                    ->and($bindings)->toBe(['MCV1454', 1, '702']);

                $callback([
                    'enttype' => 'IN',
                    'date' => '2026-07-01',
                    'qty' => 5,
                    'scheme_docno' => 'DOC-A',
                ]);
                $callback([
                    'enttype' => 'OUT',
                    'date' => '2026-07-02',
                    'qty' => 3,
                    'scheme_docno' => 'DOC-B',
                ]);

                return;
            }

            expect($runtime)->toBe('runtime_pgsql')
                ->and($sql)->toBe(StockReconciliationConnectionQueries::PGSQL_STOCK_LOGS)
                ->and($bindings)->toBe(['702', 'MCV1454', '%1%']);

            $callback([
                'enttype' => 'STI',
                'date' => '2026-07-01',
                'qty' => 5,
                'scheme_docno' => 'DOC-A',
            ]);
            $callback([
                'enttype' => 'SST',
                'date' => '2026-07-03',
                'qty' => 2,
                'scheme_docno' => 'DOC-C',
            ]);
        });
    $connector->shouldReceive('close')->once()->with('runtime_mysql');
    $connector->shouldReceive('close')->once()->with('runtime_pgsql');

    $this->app->instance(OrganizationDatabaseConnector::class, $connector);

    $result = app(StockReconLogDetailService::class)->forSku(
        session: $session,
        siteCode: '702',
        icode: 'MCV1454',
        batchNo: '',
        sprefcode: 'QRILZ002-1',
    );

    expect($result['has_zwing_logs'])->toBeTrue()
        ->and($result['has_erp_logs'])->toBeTrue()
        ->and($result['zwing_query_ms'])->toBeInt()
        ->and($result['zwing_query_ms'])->toBeGreaterThanOrEqual(0)
        ->and($result['erp_query_ms'])->toBeInt()
        ->and($result['erp_query_ms'])->toBeGreaterThanOrEqual(0)
        ->and($result['matched']['zwing'])->toHaveCount(1)
        ->and($result['matched']['zwing'][0]['doc_no'])->toBe('DOC-A')
        ->and($result['matched']['erp'])->toHaveCount(1)
        ->and($result['mismatch']['zwing'])->toHaveCount(1)
        ->and($result['mismatch']['zwing'][0]['doc_no'])->toBe('DOC-B')
        ->and($result['mismatch']['erp'])->toHaveCount(1)
        ->and($result['mismatch']['erp'][0]['doc_no'])->toBe('DOC-C');
});

it('exposes connection source on report so view logs can show without log csvs', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'vendor_id' => 99,
        'db_name' => 'zw_mn_99_demo',
    ]);

    $session = StockReconSession::query()->create([
        'user_id' => $user->id,
        'name' => 'connection-report-logs',
        'v_id' => $organization->vendor_id,
        'source' => 'connection',
        'organization_id' => $organization->id,
        'zwing_file_name' => 'mysql_ssh',
        'erp_file_name' => 'pgsql connection',
        'status' => 'completed',
        'reconciled_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('stock-transaction-reconciliation.report', [
            'stockReconSession' => $session,
            'filter' => 'qty_mismatch',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('stock-transaction-reconciliation/report')
            ->where('session.source', 'connection')
            ->where('session.zwing_file_name', 'mysql_ssh')
            ->where('session.erp_file_name', 'pgsql connection')
            ->where('session.zwing_log_file_name', null)
            ->where('session.erp_log_file_name', null)
            ->where('filter', 'qty_mismatch'));
});

it('authenticated user can fetch connection log details json via endpoint', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'vendor_id' => 88,
        'db_name' => 'zw_mn_88_demo',
    ]);
    $pgsql = OrganizationDatabaseConnection::factory()->pgsql()->create([
        'organization_id' => $organization->id,
    ]);

    $session = StockReconSession::query()->create([
        'user_id' => $user->id,
        'name' => 'connection-log-api',
        'v_id' => $organization->vendor_id,
        'source' => 'connection',
        'organization_id' => $organization->id,
        'pgsql_connection_id' => $pgsql->id,
        'zwing_file_name' => 'mysql_ssh',
        'erp_file_name' => null,
        'status' => 'completed',
    ]);

    $connector = Mockery::mock(OrganizationDatabaseConnector::class);
    $connector->shouldReceive('openMysqlSshDatabase')->once()->andReturn('runtime_mysql');
    $connector->shouldReceive('eachRow')
        ->once()
        ->withArgs(function (string $runtime, string $sql, array $bindings, callable $callback): bool {
            $callback([
                'enttype' => 'IN',
                'qty' => 10,
                'scheme_docno' => 'LIVE-1',
            ]);

            return true;
        });
    $connector->shouldReceive('close')->once()->with('runtime_mysql');

    $this->app->instance(OrganizationDatabaseConnector::class, $connector);

    $payload = $this->actingAs($user)
        ->getJson(route('stock-transaction-reconciliation.report.log-details', $session).'?'.http_build_query([
            'site_code' => '702',
            'icode' => 'MCV1454',
            'batch_no' => '',
            'sprefcode' => 'QRILZ002-1',
        ]))
        ->assertStatus(202)
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('job_type', 'log_details')
        ->assertJsonPath('result.has_zwing_logs', true)
        ->assertJsonPath('result.has_erp_logs', false)
        ->assertJsonPath('result.mismatch.zwing.0.doc_no', 'LIVE-1')
        ->assertJsonPath('result.erp_query_ms', null)
        ->json();

    expect($payload['result']['zwing_query_ms'])->toBeInt()->toBeGreaterThanOrEqual(0)
        ->and($payload['zwing_query_ms'])->toBeInt()->toBeGreaterThanOrEqual(0);
});
