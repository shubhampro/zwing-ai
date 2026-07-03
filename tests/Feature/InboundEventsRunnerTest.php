<?php

use App\Models\User;
use App\Services\InboundEventsRetryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('guests cannot access inbound events runner', function () {
    $this->get(route('inbound-events-runner.index'))
        ->assertRedirect('/login');
});

test('authenticated user can view inbound events runner page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('inbound-events-runner.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inbound-events-runner/index')
            ->has('queueNameList')
        );
});

test('guests cannot retry inbound events', function () {
    $this->postJson(route('inbound-events-runner.retry'), [
        'log_id' => 'abc123',
    ])->assertUnauthorized();
});

test('retry requires log_id', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('inbound-events-runner.retry'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('log_id');
});

test('retry proxies request to gozwing connect api', function () {
    $user = User::factory()->create();
    $logId = '507f1f77bcf86cd799439011';

    Http::fake([
        'connect.gozwing.com/inbound/retry' => Http::response(['queued' => true], 200),
    ]);

    $this->actingAs($user)
        ->postJson(route('inbound-events-runner.retry'), [
            'log_id' => $logId,
        ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'status' => 200,
            'body' => ['queued' => true],
        ]);

    Http::assertSent(function ($request) use ($logId) {
        return $request->url() === 'https://connect.gozwing.com/inbound/retry'
            && $request['log_id'] === $logId
            && $request['queue_name_list'] === InboundEventsRetryService::QUEUE_NAME_LIST;
    });
});

test('retry returns error when external api fails', function () {
    $user = User::factory()->create();

    Http::fake([
        'connect.gozwing.com/inbound/retry' => Http::response(['error' => 'Not found'], 404),
    ]);

    $this->actingAs($user)
        ->postJson(route('inbound-events-runner.retry'), [
            'log_id' => 'missing-id',
        ])
        ->assertStatus(502)
        ->assertJson([
            'success' => false,
            'status' => 404,
        ]);
});
