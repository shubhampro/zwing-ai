<?php

use App\Jobs\PullZwingStockFromConnectionJob;
use App\Models\ExternalQueryLog;
use App\Models\Organization;
use App\Models\StockReconSession;
use App\Models\User;
use App\Services\OrganizationDatabaseConnector;
use App\Services\StockReconciliationConnectionPuller;
use App\Support\ExternalQueryQueue;
use App\Support\StockReconciliationConnectionQueries;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia as Assert;

it('dispatches connection pull chain on external-query queue with log', function () {
    Bus::fake();

    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'db_name' => 'zw_mn_1_demo',
    ]);

    $this->actingAs($user)
        ->post(route('stock-transaction-reconciliation.connections'), [
            'organization_id' => $organization->id,
            'include_zwing' => true,
            'include_erp' => false,
        ])
        ->assertRedirect();

    Bus::assertChained([
        PullZwingStockFromConnectionJob::class,
    ]);

    expect(ExternalQueryLog::query()->count())->toBe(1)
        ->and(ExternalQueryLog::query()->first()?->job_type->value)->toBe('pull_stock_zwing');
});

it('records query duration and row count after connection pull', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'vendor_id' => 42,
        'db_name' => 'zw_mn_42_demo',
    ]);

    $session = StockReconSession::query()->create([
        'user_id' => $user->id,
        'name' => 'Timed pull',
        'v_id' => $organization->vendor_id,
        'source' => 'connection',
        'organization_id' => $organization->id,
        'zwing_file_name' => 'mysql_ssh',
        'status' => 'pending',
    ]);

    $connector = Mockery::mock(OrganizationDatabaseConnector::class);
    $connector->shouldReceive('openMysqlSshDatabase')
        ->once()
        ->andReturn('runtime_mysql');
    $connector->shouldReceive('eachRow')
        ->once()
        ->withArgs(function (string $runtime, string $sql, array $bindings, callable $callback): bool {
            expect($sql)->toBe(StockReconciliationConnectionQueries::MYSQL_STOCK);

            $callback([
                'site_code' => 'S1',
                'barcode' => 'B1',
                'icode' => 'I1',
                'batch_no' => 'BN1',
                'sprefcode' => 'SP-1',
                'stock_point_name' => 'Floor',
                'qty' => 5,
            ]);

            return true;
        });
    $connector->shouldReceive('close')->once()->with('runtime_mysql');

    $job = new PullZwingStockFromConnectionJob(
        sessionId: $session->id,
        completeSession: true,
    );

    expect($job->queue)->toBe(ExternalQueryQueue::NAME);

    $job->handle($connector, app(StockReconciliationConnectionPuller::class));

    $session->refresh();

    expect($session->status)->toBe('completed')
        ->and($session->zwing_processed_rows)->toBe(1)
        ->and($session->zwing_row_count)->toBe(1)
        ->and($session->zwing_query_ms)->toBeInt()
        ->and($session->zwing_query_ms)->toBeGreaterThanOrEqual(0);
});

it('exposes connection query times on show page', function () {
    $user = User::factory()->create();
    $session = StockReconSession::query()->create([
        'user_id' => $user->id,
        'name' => 'Timed session',
        'v_id' => 1,
        'source' => 'connection',
        'zwing_file_name' => 'mysql_ssh',
        'erp_file_name' => 'pgsql connection',
        'zwing_processed_rows' => 100,
        'erp_processed_rows' => 50,
        'zwing_row_count' => 100,
        'erp_row_count' => 50,
        'zwing_query_ms' => 1250,
        'erp_query_ms' => 3400,
        'status' => 'completed',
        'reconciled_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('stock-transaction-reconciliation.show', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('stock-transaction-reconciliation/show')
            ->where('session.source', 'connection')
            ->where('session.zwing_query_ms', 1250)
            ->where('session.erp_query_ms', 3400)
            ->where('session.zwing_row_count', 100)
            ->where('session.erp_row_count', 50));
});
