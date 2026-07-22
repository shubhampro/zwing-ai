<?php

use App\Enums\ExternalQueryJobType;
use App\Enums\ExternalQueryStatus;
use App\Enums\Role;
use App\Models\ExternalQueryLog;
use App\Models\StockReconSession;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('admins can view external query logs index', function () {
    $admin = User::factory()->admin()->create();
    $session = StockReconSession::query()->create([
        'user_id' => $admin->id,
        'name' => 'Admin session',
        'v_id' => 1,
        'source' => 'connection',
        'status' => 'completed',
    ]);

    ExternalQueryLog::factory()->forSession($session)->create([
        'job_type' => ExternalQueryJobType::SyncRow,
        'status' => ExternalQueryStatus::Completed,
        'zwing_query_ms' => 120,
        'erp_query_ms' => 340,
        'context' => [
            'site_code' => '702',
            'icode' => 'MCV1454',
            'sprefcode' => '1',
        ],
    ]);

    $this->actingAs($admin)
        ->get(route('external-query-logs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('external-query-logs/index')
            ->has('logs', 1)
            ->where('logs.0.job_type', 'sync_row')
            ->where('logs.0.status', 'completed')
            ->where('logs.0.zwing_query_ms', 120)
            ->where('logs.0.erp_query_ms', 340)
            ->where('logs.0.session.id', $session->id));
});

it('operators cannot view external query logs index', function () {
    $operator = User::factory()->operator()->create();

    expect($operator->hasRole(Role::Admin))->toBeFalse();

    $this->actingAs($operator)
        ->get(route('external-query-logs.index'))
        ->assertForbidden();
});

it('admins can filter external query logs by job type and status', function () {
    $admin = User::factory()->admin()->create();

    ExternalQueryLog::factory()->create([
        'user_id' => $admin->id,
        'job_type' => ExternalQueryJobType::PullStock,
        'status' => ExternalQueryStatus::Completed,
    ]);
    ExternalQueryLog::factory()->create([
        'user_id' => $admin->id,
        'job_type' => ExternalQueryJobType::SyncRow,
        'status' => ExternalQueryStatus::Failed,
        'failure_reason' => 'timeout',
    ]);

    $this->actingAs($admin)
        ->get(route('external-query-logs.index', [
            'job_type' => 'sync_row',
            'status' => 'failed',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('external-query-logs/index')
            ->has('logs', 1)
            ->where('logs.0.job_type', 'sync_row')
            ->where('logs.0.status', 'failed')
            ->where('filters.job_type', 'sync_row')
            ->where('filters.status', 'failed'));
});
