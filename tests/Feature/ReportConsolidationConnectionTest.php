<?php

use App\Enums\ExternalQueryJobType;
use App\Jobs\PullReportConsolidationFromConnectionJob;
use App\Models\ExternalQueryLog;
use App\Models\Organization;
use App\Models\ReportReconSession;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

test('create page includes organizations with a mysql database name', function () {
    $user = User::factory()->create();
    Organization::factory()->create([
        'vendor_id' => 321,
        'db_name' => 'zw_mn_321_demo',
    ]);
    Organization::factory()->create([
        'vendor_id' => 999,
        'db_name' => null,
    ]);

    $this->actingAs($user)
        ->get(route('report-consolidation.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('report-consolidation/create')
            ->has('organizations', 1)
            ->where('organizations.0.vendor_id', 321)
            ->where('organizations.0.has_db_name', true));
});

test('store creates a session and dispatches the zwing pull job', function () {
    Queue::fake();

    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'vendor_id' => 555,
        'db_name' => 'zw_mn_555_demo',
    ]);

    $this->actingAs($user)
        ->post(route('report-consolidation.store'), [
            'name' => 'Live invoice vs mop',
            'organization_id' => $organization->id,
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
        ])
        ->assertRedirect();

    $session = ReportReconSession::query()->where('user_id', $user->id)->firstOrFail();

    expect($session->name)->toBe('Live invoice vs mop')
        ->and($session->v_id)->toBe(555)
        ->and($session->organization_id)->toBe($organization->id)
        ->and($session->date_from?->toDateString())->toBe('2026-08-01')
        ->and($session->date_to?->toDateString())->toBe('2026-08-31')
        ->and($session->status)->toBe('pending');

    Queue::assertPushed(
        PullReportConsolidationFromConnectionJob::class,
        fn (PullReportConsolidationFromConnectionJob $job): bool => $job->sessionId === $session->id
            && $job->externalQueryLogId === ExternalQueryLog::query()
                ->where('job_type', ExternalQueryJobType::PullReportConsolidation)
                ->value('id'),
    );
});

test('store requires a date range', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'db_name' => 'zw_mn_1_demo',
    ]);

    $this->actingAs($user)
        ->post(route('report-consolidation.store'), [
            'organization_id' => $organization->id,
        ])
        ->assertSessionHasErrors(['date_from', 'date_to']);
});

test('store requires an organization with a mysql database name', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'db_name' => null,
    ]);

    $this->actingAs($user)
        ->post(route('report-consolidation.store'), [
            'organization_id' => $organization->id,
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
        ])
        ->assertSessionHasErrors('organization_id');
});

test('store auto-names the session when name is blank', function () {
    Queue::fake();

    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'name' => 'Demo Org',
        'db_name' => 'zw_mn_1_demo',
    ]);

    $this->actingAs($user)
        ->post(route('report-consolidation.store'), [
            'organization_id' => $organization->id,
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
        ])
        ->assertRedirect();

    $session = ReportReconSession::query()->where('user_id', $user->id)->firstOrFail();

    expect($session->name)->toStartWith('Demo Org · Invoice vs MOP · 2026-08-01 to 2026-08-31');
});
