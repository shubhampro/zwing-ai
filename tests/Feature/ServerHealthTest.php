<?php

use App\Enums\Role;
use App\Models\DbHealthCheck;
use App\Models\User;
use App\Services\DbHealth\DbHealthChecker;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    config([
        'server_health.targets' => ['sqlite'],
        'server_health.cache_ttl_seconds' => 60,
        'server_health.lock_seconds' => 30,
    ]);
    Cache::flush();
});

it('allows admins to view server health', function () {
    $admin = User::factory()->admin()->create();

    actingAs($admin)
        ->get(route('server-health.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('server-health/index')
            ->has('snapshot')
            ->has('history')
            ->where('can_refresh', true));
});

it('forbids operators and viewers from viewing server health', function () {
    $operator = User::factory()->operator()->create();
    $viewer = User::factory()->viewer()->create();

    actingAs($operator)->get(route('server-health.index'))->assertForbidden();
    actingAs($viewer)->get(route('server-health.index'))->assertForbidden();

    expect($operator->hasRole(Role::Admin))->toBeFalse()
        ->and($viewer->hasRole(Role::Admin))->toBeFalse();
});

it('forbids operators from refreshing server health', function () {
    $operator = User::factory()->operator()->create();

    actingAs($operator)
        ->post(route('server-health.refresh'))
        ->assertForbidden();
});

it('runs refresh and stores history for admins', function () {
    $admin = User::factory()->admin()->create();

    actingAs($admin)
        ->postJson(route('server-health.refresh'))
        ->assertStatus(202)
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('job_type', 'server_health_check');

    expect(DbHealthCheck::query()->count())->toBe(1)
        ->and(Cache::has(config('server_health.cache_key')))->toBeTrue();

    actingAs($admin)
        ->get(route('server-health.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('cache_fresh', true)
            ->where('snapshot.overall_status', DbHealthChecker::STATUS_OK));
});

it('skips refresh when cache is still fresh', function () {
    $admin = User::factory()->admin()->create();
    $checker = app(DbHealthChecker::class);
    $checker->run();

    expect(DbHealthCheck::query()->count())->toBe(1);

    actingAs($admin)
        ->postJson(route('server-health.refresh'))
        ->assertStatus(409)
        ->assertJsonPath('message', 'Cached snapshot still fresh. Wait for TTL before refreshing.');

    expect(DbHealthCheck::query()->count())->toBe(1);
});

it('skips refresh when lock is held', function () {
    $admin = User::factory()->admin()->create();
    $lock = Cache::lock(config('server_health.lock_key'), 30);
    expect($lock->get())->toBeTrue();

    try {
        actingAs($admin)
            ->postJson(route('server-health.refresh'))
            ->assertStatus(409)
            ->assertJsonPath('message', 'A health check is already running. Try again shortly.');
    } finally {
        $lock->release();
    }
});
