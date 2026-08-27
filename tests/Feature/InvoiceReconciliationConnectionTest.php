<?php

use App\Enums\ExternalQueryJobType;
use App\Jobs\PullErpInvoiceFromConnectionJob;
use App\Jobs\PullZwingInvoiceFromConnectionJob;
use App\Models\ExternalQueryLog;
use App\Models\InvoiceReconSession;
use App\Models\Organization;
use App\Models\OrganizationDatabaseConnection;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia as Assert;

test('create page includes organizations for connection pull', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'vendor_id' => 321,
        'db_name' => 'zw_mn_321_demo',
    ]);
    OrganizationDatabaseConnection::factory()->pgsql()->create([
        'organization_id' => $organization->id,
    ]);

    $this->actingAs($user)
        ->get(route('invoice-reconciliation.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('invoice-reconciliation/create')
            ->has('organizations', 1)
            ->where('organizations.0.vendor_id', 321)
            ->has('organizations.0.pgsql_connections', 1));
});

test('invoice connection pull creates session and chains zwing then erp jobs', function () {
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
        ->post(route('invoice-reconciliation.connections'), [
            'name' => 'Live invoice pull',
            'organization_id' => $organization->id,
            'pgsql_connection_id' => $pgsql->id,
            'date_from' => '2026-01-01',
            'date_to' => '2026-08-27',
            'include_zwing' => true,
            'include_erp' => true,
        ])
        ->assertRedirect();

    $session = InvoiceReconSession::query()->where('user_id', $user->id)->firstOrFail();

    expect($session->name)->toBe('Live invoice pull')
        ->and($session->source)->toBe('connection')
        ->and($session->v_id)->toBe(555)
        ->and($session->organization_id)->toBe($organization->id)
        ->and($session->pgsql_connection_id)->toBe($pgsql->id)
        ->and($session->date_from?->toDateString())->toBe('2026-01-01')
        ->and($session->date_to?->toDateString())->toBe('2026-08-27')
        ->and($session->status)->toBe('pending');

    Bus::assertChained([
        new PullZwingInvoiceFromConnectionJob(
            sessionId: $session->id,
            externalQueryLogId: (int) ExternalQueryLog::query()
                ->where('job_type', ExternalQueryJobType::PullInvoiceZwing)
                ->value('id'),
            completeSession: false,
        ),
        new PullErpInvoiceFromConnectionJob(
            sessionId: $session->id,
            pgsqlConnectionId: $pgsql->id,
            externalQueryLogId: (int) ExternalQueryLog::query()
                ->where('job_type', ExternalQueryJobType::PullInvoiceErp)
                ->value('id'),
        ),
    ]);
});

test('invoice connection pull requires a date range', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'db_name' => 'zw_mn_1_demo',
    ]);

    $this->actingAs($user)
        ->post(route('invoice-reconciliation.connections'), [
            'organization_id' => $organization->id,
            'include_zwing' => true,
            'include_erp' => false,
        ])
        ->assertSessionHasErrors(['date_from', 'date_to']);
});

test('invoice connection pull requires at least one side', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'db_name' => 'zw_mn_1_demo',
    ]);

    $this->actingAs($user)
        ->post(route('invoice-reconciliation.connections'), [
            'organization_id' => $organization->id,
            'date_from' => '2026-01-01',
            'date_to' => '2026-08-27',
            'include_zwing' => false,
            'include_erp' => false,
        ])
        ->assertSessionHasErrors('include_zwing');
});

test('show page includes connection date range', function () {
    $user = User::factory()->create();
    $session = InvoiceReconSession::factory()->for($user)->fromConnection()->create();

    $this->actingAs($user)
        ->get(route('invoice-reconciliation.show', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('invoice-reconciliation/show')
            ->where('session.source', 'connection')
            ->where('session.date_from', '2026-01-01')
            ->where('session.date_to', '2026-08-27'));
});
