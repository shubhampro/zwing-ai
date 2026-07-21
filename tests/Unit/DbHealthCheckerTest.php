<?php

use App\Services\DbHealth\DbHealthChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config([
        'server_health.targets' => ['sqlite'],
        'server_health.cache_ttl_seconds' => 60,
        'server_health.lock_seconds' => 30,
        'server_health.thresholds.threads_running_warn' => 50,
        'server_health.thresholds.threads_running_critical' => 100,
    ]);
    Cache::flush();
});

it('marks warn and critical from threads_running thresholds', function () {
    $checker = new DbHealthChecker;

    expect($checker->statusFromMeta(['threads_running' => 10]))->toBe(DbHealthChecker::STATUS_OK)
        ->and($checker->statusFromMeta(['threads_running' => 50]))->toBe(DbHealthChecker::STATUS_WARN)
        ->and($checker->statusFromMeta(['threads_running' => 100]))->toBe(DbHealthChecker::STATUS_CRITICAL)
        ->and($checker->statusFromMeta([], 'boom'))->toBe(DbHealthChecker::STATUS_DOWN);
});

it('picks worst overall status', function () {
    $checker = new DbHealthChecker;

    expect($checker->overallStatus([
        ['status' => DbHealthChecker::STATUS_OK],
        ['status' => DbHealthChecker::STATUS_WARN],
        ['status' => DbHealthChecker::STATUS_DOWN],
    ]))->toBe(DbHealthChecker::STATUS_DOWN);
});

it('marks unknown connection as down', function () {
    $checker = new DbHealthChecker;

    $result = $checker->checkTarget('does_not_exist');

    expect($result['status'])->toBe(DbHealthChecker::STATUS_DOWN)
        ->and($result['error'])->toContain('Unknown connection');
});

it('returns null when lock is held', function () {
    $lock = Cache::lock(config('server_health.lock_key'), 30);
    expect($lock->get())->toBeTrue();

    try {
        $checker = new DbHealthChecker;
        expect($checker->run())->toBeNull();
    } finally {
        $lock->release();
    }
});

it('checks sqlite target and caches snapshot', function () {
    $checker = new DbHealthChecker;

    $result = $checker->run();

    expect($result)->not->toBeNull()
        ->and($result['overall_status'])->toBe(DbHealthChecker::STATUS_OK)
        ->and($result['results'])->toHaveCount(1)
        ->and($result['results'][0]['key'])->toBe('sqlite')
        ->and($result['results'][0]['status'])->toBe(DbHealthChecker::STATUS_OK);

    expect($checker->snapshot())->not->toBeNull()
        ->and($checker->snapshot()['cached'])->toBeTrue();
});
