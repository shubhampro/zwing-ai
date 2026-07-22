<?php

use App\Enums\Role;
use App\Models\User;

it('admins can view the horizon dashboard', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/horizon')
        ->assertOk();
});

it('operators cannot view the horizon dashboard', function () {
    $operator = User::factory()->operator()->create();

    expect($operator->hasRole(Role::Admin))->toBeFalse();

    $this->actingAs($operator)
        ->get('/horizon')
        ->assertForbidden();
});

it('guests cannot view the horizon dashboard', function () {
    $this->get('/horizon')->assertForbidden();
});

it('configures horizon for a serial external-query supervisor', function () {
    expect(config('queue.connections.redis.driver'))->toBe('redis')
        ->and(config('queue.connections.redis.retry_after'))->toBeGreaterThan(60)
        ->and(config('horizon.defaults.supervisor-external-query.queue'))->toBe(['external-query'])
        ->and(config('horizon.defaults.supervisor-external-query.maxProcesses'))->toBe(1)
        ->and(config('horizon.defaults.supervisor-external-query.timeout'))->toBe(60)
        ->and(config('horizon.environments.production.supervisor-external-query.maxProcesses'))->toBe(1);
});
