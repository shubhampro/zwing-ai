<?php

use App\Jobs\PullStockReconciliationFromConnections;
use App\Models\Organization;
use App\Models\OrganizationDatabaseConnection;
use App\Models\StockReconSession;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected from create-from-connections', function () {
    $this->get(route('stock-transaction-reconciliation.create-from-connections'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit create-from-connections with orgs that have db_name', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'vendor_id' => 321,
        'db_name' => 'zw_mn_321_demo',
    ]);
    OrganizationDatabaseConnection::factory()->pgsql()->create([
        'organization_id' => $organization->id,
        'host' => 'pgsql.example.com',
    ]);

    $this->actingAs($user)
        ->get(route('stock-transaction-reconciliation.create-from-connections'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('stock-transaction-reconciliation/create-from-connections')
            ->has('organizations', 1)
            ->where('organizations.0.vendor_id', 321)
            ->where('organizations.0.has_db_name', true)
            ->has('organizations.0.pgsql_connections', 1)
            ->where('organizations.0.pgsql_connections.0.host_masked', 'p********m')
            ->where('suggestedSessionName', fn (string $name) => str_contains($name, 'connection stock')
                && (str_contains($name, 'AM') || str_contains($name, 'PM')))
            ->missing('organizations.0.db_name')
            ->missing('organizations.0.pgsql_connections.0.host')
            ->missing('organizations.0.pgsql_connections.0.database_name')
            ->missing('organizations.0.pgsql_connections.0.password'));
});

test('connection pull creates session and dispatches job using mysql_ssh', function () {
    Queue::fake();

    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'vendor_id' => 555,
        'db_name' => 'zw_mn_555_demo',
    ]);
    $pgsql = OrganizationDatabaseConnection::factory()->pgsql()->create([
        'organization_id' => $organization->id,
    ]);

    $this->actingAs($user)
        ->post(route('stock-transaction-reconciliation.connections'), [
            'name' => 'Live stock pull',
            'organization_id' => $organization->id,
            'pgsql_connection_id' => $pgsql->id,
            'include_zwing' => true,
            'include_erp' => true,
        ])
        ->assertRedirect();

    $session = StockReconSession::query()->where('user_id', $user->id)->firstOrFail();

    expect($session->name)->toBe('Live stock pull')
        ->and($session->v_id)->toBe(555)
        ->and($session->source)->toBe('connection')
        ->and($session->organization_id)->toBe($organization->id)
        ->and($session->zwing_file_name)->toBe('mysql_ssh')
        ->and($session->erp_file_name)->toBe('pgsql connection')
        ->and($session->status)->toBe('pending');

    Queue::assertPushedOn(
        StockReconSession::CONNECTION_QUEUE,
        PullStockReconciliationFromConnections::class,
        fn (PullStockReconciliationFromConnections $job) => $job->sessionId === $session->id
            && $job->pgsqlConnectionId === $pgsql->id
            && $job->includeZwing === true
            && $job->includeErp === true,
    );
});

test('connection pull auto-generates session name when blank', function () {
    Queue::fake();

    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'name' => 'Acme Retail',
        'vendor_id' => 777,
        'db_name' => 'zw_mn_777_demo',
    ]);

    $this->actingAs($user)
        ->post(route('stock-transaction-reconciliation.connections'), [
            'organization_id' => $organization->id,
            'include_zwing' => true,
            'include_erp' => false,
        ])
        ->assertRedirect();

    $session = StockReconSession::query()->where('user_id', $user->id)->firstOrFail();

    expect($session->name)->toStartWith('Acme Retail · connection stock · ')
        ->and($session->name)->toMatch('/\b(AM|PM)\b/');
});

test('connection pull requires at least one side', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'db_name' => 'zw_mn_1_demo',
    ]);

    $this->actingAs($user)
        ->post(route('stock-transaction-reconciliation.connections'), [
            'name' => 'Empty pull',
            'organization_id' => $organization->id,
            'include_zwing' => false,
            'include_erp' => false,
        ])
        ->assertSessionHasErrors('include_zwing');
});

test('erp pull requires pgsql connection', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'db_name' => 'zw_mn_1_demo',
    ]);

    $this->actingAs($user)
        ->post(route('stock-transaction-reconciliation.connections'), [
            'name' => 'ERP only missing pgsql',
            'organization_id' => $organization->id,
            'include_zwing' => false,
            'include_erp' => true,
        ])
        ->assertSessionHasErrors('pgsql_connection_id');
});

test('organization without db_name cannot start connection pull', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'db_name' => null,
    ]);
    $pgsql = OrganizationDatabaseConnection::factory()->pgsql()->create([
        'organization_id' => $organization->id,
    ]);

    $this->actingAs($user)
        ->post(route('stock-transaction-reconciliation.connections'), [
            'name' => 'Missing db name',
            'organization_id' => $organization->id,
            'pgsql_connection_id' => $pgsql->id,
            'include_zwing' => true,
            'include_erp' => true,
        ])
        ->assertSessionHasErrors('organization_id');
});

test('pgsql connection must belong to selected organization', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'db_name' => 'zw_mn_1_demo',
    ]);
    $other = Organization::factory()->create([
        'db_name' => 'zw_mn_2_demo',
    ]);
    $foreignPgsql = OrganizationDatabaseConnection::factory()->pgsql()->create([
        'organization_id' => $other->id,
    ]);

    $this->actingAs($user)
        ->post(route('stock-transaction-reconciliation.connections'), [
            'name' => 'Wrong org connection',
            'organization_id' => $organization->id,
            'pgsql_connection_id' => $foreignPgsql->id,
            'include_zwing' => true,
            'include_erp' => true,
        ])
        ->assertSessionHasErrors('pgsql_connection_id');
});
