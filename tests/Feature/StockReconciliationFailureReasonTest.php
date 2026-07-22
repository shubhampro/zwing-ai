<?php

use App\Jobs\PullStockReconciliationFromConnections;
use App\Models\Organization;
use App\Models\StockReconSession;
use App\Models\User;
use App\Services\OrganizationDatabaseConnector;
use Inertia\Testing\AssertableInertia as Assert;

it('stores failure reason when connection pull job fails', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'db_name' => 'zw_mn_1_demo',
    ]);
    $session = StockReconSession::query()->create([
        'user_id' => $user->id,
        'name' => 'Failing pull',
        'v_id' => $organization->vendor_id,
        'source' => 'connection',
        'organization_id' => $organization->id,
        'zwing_file_name' => 'mysql_ssh',
        'status' => 'pending',
    ]);

    $connector = Mockery::mock(OrganizationDatabaseConnector::class);
    $connector->shouldReceive('openMysqlSshDatabase')
        ->once()
        ->andThrow(new RuntimeException('relation "invitem" does not exist'));
    $connector->shouldReceive('close')->never();

    $job = new PullStockReconciliationFromConnections(
        sessionId: $session->id,
        pgsqlConnectionId: null,
        includeZwing: true,
        includeErp: false,
    );

    try {
        $job->handle($connector);
    } catch (RuntimeException) {
        // expected
    }

    $session->refresh();

    expect($session->status)->toBe('failed')
        ->and($session->failure_reason)->toContain('relation "invitem" does not exist');
});

it('shows failure reason on session show page', function () {
    $user = User::factory()->create();
    $session = StockReconSession::query()->create([
        'user_id' => $user->id,
        'name' => 'Failed session',
        'v_id' => 1,
        'status' => 'failed',
        'failure_reason' => 'relation "invitem" does not exist',
    ]);

    $this->actingAs($user)
        ->get(route('stock-transaction-reconciliation.show', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('stock-transaction-reconciliation/show')
            ->where('session.failure_reason', 'relation "invitem" does not exist'));
});
