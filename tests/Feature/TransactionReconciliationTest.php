<?php

use App\Enums\ExternalQueryJobType;
use App\Enums\TransactionReconType;
use App\Jobs\PullErpTransactionFromConnectionJob;
use App\Jobs\PullZwingTransactionFromConnectionJob;
use App\Models\ExternalQueryLog;
use App\Models\Organization;
use App\Models\OrganizationDatabaseConnection;
use App\Models\TransactionReconSession;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('transaction-reconciliation.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the transaction reconciliation index', function () {
    $user = User::factory()->create();
    TransactionReconSession::factory()->for($user)->create([
        'name' => 'Packet sample',
        'type' => TransactionReconType::Packet,
    ]);

    $this->actingAs($user)
        ->get(route('transaction-reconciliation.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('transaction-reconciliation/index')
            ->has('sessions', 1)
            ->where('sessions.0.name', 'Packet sample')
            ->where('sessions.0.type', 'packet')
            ->missing('types'));
});

test('users without permission are forbidden', function () {
    $user = User::factory()->create();
    $user->syncRoles([]);

    $this->actingAs($user)
        ->get(route('transaction-reconciliation.index'))
        ->assertForbidden();
});

test('authenticated users can visit create page', function () {
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
        ->get(route('transaction-reconciliation.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('transaction-reconciliation/create')
            ->has('organizations', 1)
            ->where('organizations.0.vendor_id', 321)
            ->has('organizations.0.pgsql_connections', 1)
            ->has('types', 6)
            ->where('types.0.key', 'packet')
            ->where('types.0.available', true)
            ->where('types.2.key', 'grt')
            ->where('types.2.available', true)
            ->where('types.1.available', false)
            ->where('types.4.key', 'cash')
            ->where('types.4.available', true)
            ->where('types.5.key', 'deposit')
            ->where('types.5.available', true));
});

test('packet pull creates session and chains zwing then erp jobs', function () {
    Bus::fake();

    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'vendor_id' => 555,
        'db_name' => 'zw_mn_555_demo',
    ]);
    $pgsql = OrganizationDatabaseConnection::factory()->pgsql()->create([
        'organization_id' => $organization->id,
    ]);

    $this->actingAs($user)
        ->post(route('transaction-reconciliation.store'), [
            'name' => 'Live packet pull',
            'type' => 'packet',
            'organization_id' => $organization->id,
            'pgsql_connection_id' => $pgsql->id,
            'include_zwing' => true,
            'include_erp' => true,
        ])
        ->assertRedirect();

    $session = TransactionReconSession::query()->where('user_id', $user->id)->firstOrFail();

    expect($session->name)->toBe('Live packet pull')
        ->and($session->type)->toBe(TransactionReconType::Packet)
        ->and($session->v_id)->toBe(555)
        ->and($session->organization_id)->toBe($organization->id)
        ->and($session->pgsql_connection_id)->toBe($pgsql->id)
        ->and($session->status)->toBe('pending');

    Bus::assertChained([
        new PullZwingTransactionFromConnectionJob(
            sessionId: $session->id,
            externalQueryLogId: (int) ExternalQueryLog::query()
                ->where('job_type', ExternalQueryJobType::PullTransactionZwing)
                ->value('id'),
            completeSession: false,
        ),
        new PullErpTransactionFromConnectionJob(
            sessionId: $session->id,
            pgsqlConnectionId: $pgsql->id,
            externalQueryLogId: (int) ExternalQueryLog::query()
                ->where('job_type', ExternalQueryJobType::PullTransactionErp)
                ->value('id'),
        ),
    ]);
});

test('packet pull requires at least one side', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'db_name' => 'zw_mn_1_demo',
    ]);

    $this->actingAs($user)
        ->post(route('transaction-reconciliation.store'), [
            'type' => 'packet',
            'organization_id' => $organization->id,
            'include_zwing' => false,
            'include_erp' => false,
        ])
        ->assertSessionHasErrors('include_zwing');
});

test('grn type is rejected until queries exist', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'db_name' => 'zw_mn_1_demo',
    ]);

    $this->actingAs($user)
        ->post(route('transaction-reconciliation.store'), [
            'type' => 'grn',
            'organization_id' => $organization->id,
            'include_zwing' => true,
            'include_erp' => false,
        ])
        ->assertSessionHasErrors('type');
});

test('deposit pull creates session and chains zwing then erp jobs', function () {
    Bus::fake();

    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'vendor_id' => 888,
        'db_name' => 'zw_mn_888_demo',
    ]);
    $pgsql = OrganizationDatabaseConnection::factory()->pgsql()->create([
        'organization_id' => $organization->id,
    ]);

    $this->actingAs($user)
        ->post(route('transaction-reconciliation.store'), [
            'name' => 'Live deposit pull',
            'type' => 'deposit',
            'organization_id' => $organization->id,
            'pgsql_connection_id' => $pgsql->id,
            'include_zwing' => true,
            'include_erp' => true,
        ])
        ->assertRedirect();

    $session = TransactionReconSession::query()->where('user_id', $user->id)->firstOrFail();

    expect($session->name)->toBe('Live deposit pull')
        ->and($session->type)->toBe(TransactionReconType::Deposit)
        ->and($session->status)->toBe('pending');

    Bus::assertChained([
        new PullZwingTransactionFromConnectionJob(
            sessionId: $session->id,
            externalQueryLogId: (int) ExternalQueryLog::query()
                ->where('job_type', ExternalQueryJobType::PullTransactionZwing)
                ->value('id'),
            completeSession: false,
        ),
        new PullErpTransactionFromConnectionJob(
            sessionId: $session->id,
            pgsqlConnectionId: $pgsql->id,
            externalQueryLogId: (int) ExternalQueryLog::query()
                ->where('job_type', ExternalQueryJobType::PullTransactionErp)
                ->value('id'),
        ),
    ]);
});

test('grt pull creates session and chains zwing then erp jobs', function () {
    Bus::fake();

    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'vendor_id' => 777,
        'db_name' => 'zw_mn_777_demo',
    ]);
    $pgsql = OrganizationDatabaseConnection::factory()->pgsql()->create([
        'organization_id' => $organization->id,
    ]);

    $this->actingAs($user)
        ->post(route('transaction-reconciliation.store'), [
            'name' => 'Live grt pull',
            'type' => 'grt',
            'organization_id' => $organization->id,
            'pgsql_connection_id' => $pgsql->id,
            'include_zwing' => true,
            'include_erp' => true,
        ])
        ->assertRedirect();

    $session = TransactionReconSession::query()->where('user_id', $user->id)->firstOrFail();

    expect($session->name)->toBe('Live grt pull')
        ->and($session->type)->toBe(TransactionReconType::Grt)
        ->and($session->status)->toBe('pending');

    Bus::assertChained([
        new PullZwingTransactionFromConnectionJob(
            sessionId: $session->id,
            externalQueryLogId: (int) ExternalQueryLog::query()
                ->where('job_type', ExternalQueryJobType::PullTransactionZwing)
                ->value('id'),
            completeSession: false,
        ),
        new PullErpTransactionFromConnectionJob(
            sessionId: $session->id,
            pgsqlConnectionId: $pgsql->id,
            externalQueryLogId: (int) ExternalQueryLog::query()
                ->where('job_type', ExternalQueryJobType::PullTransactionErp)
                ->value('id'),
        ),
    ]);
});

test('cash pull creates session and chains zwing then erp jobs', function () {
    Bus::fake();

    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'vendor_id' => 888,
        'db_name' => 'zw_mn_888_demo',
    ]);
    $pgsql = OrganizationDatabaseConnection::factory()->pgsql()->create([
        'organization_id' => $organization->id,
    ]);

    $this->actingAs($user)
        ->post(route('transaction-reconciliation.store'), [
            'name' => 'Live cash pull',
            'type' => 'cash',
            'organization_id' => $organization->id,
            'pgsql_connection_id' => $pgsql->id,
            'include_zwing' => true,
            'include_erp' => true,
        ])
        ->assertRedirect();

    $session = TransactionReconSession::query()->where('user_id', $user->id)->firstOrFail();

    expect($session->name)->toBe('Live cash pull')
        ->and($session->type)->toBe(TransactionReconType::Cash)
        ->and($session->status)->toBe('pending');

    Bus::assertChained([
        new PullZwingTransactionFromConnectionJob(
            sessionId: $session->id,
            externalQueryLogId: (int) ExternalQueryLog::query()
                ->where('job_type', ExternalQueryJobType::PullTransactionZwing)
                ->value('id'),
            completeSession: false,
        ),
        new PullErpTransactionFromConnectionJob(
            sessionId: $session->id,
            pgsqlConnectionId: $pgsql->id,
            externalQueryLogId: (int) ExternalQueryLog::query()
                ->where('job_type', ExternalQueryJobType::PullTransactionErp)
                ->value('id'),
        ),
    ]);
});

test('report matches packet rows on txn id', function () {
    $user = User::factory()->create();
    $session = TransactionReconSession::factory()->for($user)->completed()->create();

    DB::table('zwing_transaction_reconsile')->insert([
        [
            'session_id' => $session->id,
            'txn_id' => '1',
            'code' => 'PCB1',
            'type' => 'Adhoc',
            'status' => 'SUCCESS',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'session_id' => $session->id,
            'txn_id' => '2',
            'code' => 'PCB2',
            'type' => 'Adhoc',
            'status' => 'SUCCESS',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    DB::table('erp_transaction_reconsile')->insert([
        [
            'session_id' => $session->id,
            'txn_id' => '1',
            'code' => 'PCB1',
            'type' => 'Adhoc',
            'status' => 'SUCCESS',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'session_id' => $session->id,
            'txn_id' => '3',
            'code' => 'PCB3',
            'type' => 'Adhoc',
            'status' => 'VOID',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $this->actingAs($user)
        ->get(route('transaction-reconciliation.report', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('transaction-reconciliation/report')
            ->where('summary.matched', 1)
            ->where('summary.zwing_only', 1)
            ->where('summary.erp_only', 1)
            ->where('summary.mismatch', 0)
            ->where('summary.total', 3)
            ->has('statusOptions.zwing')
            ->has('statusOptions.erp'));
});

test('report flags cash amount mismatch', function () {
    $user = User::factory()->create();
    $session = TransactionReconSession::factory()->for($user)->completed()->create([
        'type' => TransactionReconType::Cash,
    ]);

    DB::table('zwing_transaction_reconsile')->insert([
        'session_id' => $session->id,
        'txn_id' => '101|CASH-1',
        'code' => 'CASH-1',
        'type' => '101',
        'status' => 'APPROVED',
        'site_id' => '101',
        'txn_date' => '2026-06-01',
        'amount' => 100,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('erp_transaction_reconsile')->insert([
        'session_id' => $session->id,
        'txn_id' => '101|CASH-1',
        'code' => 'CASH-1',
        'type' => '101',
        'status' => 'APPROVED',
        'site_id' => '101',
        'txn_date' => '2026-06-01',
        'amount' => 120,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('transaction-reconciliation.report', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('transaction-reconciliation/report')
            ->where('session.uses_cash_columns', true)
            ->where('summary.amount_mismatch', 1)
            ->where('summary.matched', 0)
            ->where('summary.mismatch', 1)
            ->where('rows.0.match_status', 'amount_mismatch'));
});

test('report export streams csv', function () {
    $user = User::factory()->create();
    $session = TransactionReconSession::factory()->for($user)->completed()->create();

    DB::table('zwing_transaction_reconsile')->insert([
        'session_id' => $session->id,
        'txn_id' => '1',
        'code' => 'PCB1',
        'type' => 'Adhoc',
        'status' => 'SUCCESS',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('erp_transaction_reconsile')->insert([
        'session_id' => $session->id,
        'txn_id' => '1',
        'code' => 'PCB1',
        'type' => 'Adhoc',
        'status' => 'SUCCESS',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('transaction-reconciliation.report.export', $session))
        ->assertOk()
        ->assertHeader('content-disposition');
});

test('user can delete own session', function () {
    $user = User::factory()->create();
    $session = TransactionReconSession::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('transaction-reconciliation.destroy', $session))
        ->assertRedirect(route('transaction-reconciliation.index'));

    expect(TransactionReconSession::query()->whereKey($session->id)->exists())->toBeFalse();
});
