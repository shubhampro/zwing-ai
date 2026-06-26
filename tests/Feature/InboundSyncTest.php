<?php

use App\Models\User;
use App\Services\ErpToZwing\InboundSyncQueryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'database.connections.mongodb_ssh.database' => 'zwing_connect',
        'inbound_sync.collection' => 'inbound_apis',
    ]);
});

test('inbound sync index page loads for authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('inbound-sync.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inbound-sync/index')
            ->has('organizations')
            ->has('defaultStartDate')
            ->has('defaultEndDate')
            ->where('mongoConfigured', true)
        );
});

test('inbound sync index redirects unauthenticated user', function () {
    $this->get(route('inbound-sync.index'))->assertRedirect('/login');
});

test('inbound sync check returns normalized response', function () {
    $user = User::factory()->create();

    $this->mock(InboundSyncQueryService::class, function ($mock): void {
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('fetch')->once()->andReturn([
            'result' => [
                [
                    'name' => 'snd.invoice.added',
                    'trans' => 10,
                    'val' => 7,
                    'fail_cnt' => 3,
                    'pending' => 0,
                    'success_sync' => 7,
                    'need_to_sync' => ['69df49200e8f0c6f726e7ca0'],
                    'event_miss' => [],
                    'failed_details' => [
                        [
                            'id' => '69df49200e8f0c6f726e7ca0',
                            'client_event_unique_code' => '99455',
                            'request' => ['event_name' => 'snd.invoice.added'],
                            'response' => 'producer: topic-partition error',
                        ],
                    ],
                    'pending_details' => [],
                    'need_to_sync_count' => 3,
                    'event_miss_count' => 0,
                ],
                [
                    'name' => 'item.updated',
                    'trans' => 5,
                    'val' => 0,
                    'fail_cnt' => 0,
                    'pending' => 5,
                    'success_sync' => 0,
                    'need_to_sync' => [],
                    'event_miss' => ['69df49200e8f0c6f726e7cb1'],
                    'failed_details' => [],
                    'pending_details' => [
                        [
                            'id' => '69df49200e8f0c6f726e7cb1',
                            'client_event_unique_code' => '88124',
                            'request' => ['event_name' => 'item.updated'],
                            'response' => null,
                        ],
                    ],
                    'need_to_sync_count' => 0,
                    'event_miss_count' => 5,
                ],
            ],
            'stats' => [
                'total_sync' => 15,
                'success_sync' => 7,
                'pending' => 5,
                'remain_sync' => 8,
                'sync_percentage' => 46.67,
            ],
        ]);
    });

    $this->actingAs($user)
        ->postJson(route('inbound-sync.check'), [
            'v_id' => 287,
            'client_id' => 'V12345RTZW87',
            'start_date' => '2026-05-10T00:00',
            'end_date' => '2026-06-09T23:59',
        ])
        ->assertOk()
        ->assertJsonPath('filters.v_id', 287)
        ->assertJsonPath('filters.client_id', 'V12345RTZW87')
        ->assertJsonPath('stats.total_sync', 15)
        ->assertJsonPath('stats.success_sync', 7)
        ->assertJsonPath('result.0.name', 'snd.invoice.added')
        ->assertJsonPath('result.0.status', 'failed')
        ->assertJsonPath('result.0.need_to_sync.0', '69df49200e8f0c6f726e7ca0')
        ->assertJsonPath('result.0.failed_details.0.id', '69df49200e8f0c6f726e7ca0')
        ->assertJsonPath('result.0.failed_details.0.response', 'producer: topic-partition error')
        ->assertJsonPath('result.1.status', 'pending')
        ->assertJsonPath('result.1.event_miss.0', '69df49200e8f0c6f726e7cb1');
});

test('inbound sync check filters by client event name and unique code', function () {
    $user = User::factory()->create();

    $this->mock(InboundSyncQueryService::class, function ($mock): void {
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('fetch')
            ->once()
            ->with(
                287,
                Mockery::type(Carbon::class),
                Mockery::type(Carbon::class),
                null,
                'snd.invoice.added',
                '99776',
            )
            ->andReturn([
                'result' => [
                    [
                        'name' => 'snd.invoice.added',
                        'trans' => 1,
                        'val' => 1,
                        'fail_cnt' => 0,
                        'pending' => 0,
                        'success_sync' => 1,
                        'need_to_sync' => [],
                        'event_miss' => [],
                        'need_to_sync_count' => 0,
                        'event_miss_count' => 0,
                    ],
                ],
                'stats' => [
                    'total_sync' => 1,
                    'success_sync' => 1,
                    'pending' => 0,
                    'remain_sync' => 0,
                    'sync_percentage' => 100.0,
                ],
            ]);
    });

    $this->actingAs($user)
        ->postJson(route('inbound-sync.check'), [
            'v_id' => 287,
            'start_date' => '2026-06-01T05:30',
            'end_date' => '2026-06-05T05:29',
            'client_event_name' => 'snd.invoice.added',
            'client_event_unique_code' => '99776',
        ])
        ->assertOk()
        ->assertJsonPath('filters.client_event_name', 'snd.invoice.added')
        ->assertJsonPath('filters.client_event_unique_code', '99776')
        ->assertJsonCount(1, 'result')
        ->assertJsonPath('result.0.name', 'snd.invoice.added')
        ->assertJsonPath('stats.total_sync', 1)
        ->assertJsonPath('stats.success_sync', 1);
});

test('inbound sync check validates request', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('inbound-sync.check'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['v_id', 'start_date', 'end_date']);
});

test('inbound sync check returns 503 when mongodb is not configured', function () {
    config(['database.connections.mongodb_ssh.database' => '']);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('inbound-sync.check'), [
            'v_id' => 287,
            'start_date' => '2026-05-10T00:00',
            'end_date' => '2026-06-09T23:59',
        ])
        ->assertStatus(503)
        ->assertJsonPath('message', fn ($message) => str_contains($message, 'not configured'));
});

test('inbound sync check returns 502 when query fails', function () {
    $user = User::factory()->create();

    $this->mock(InboundSyncQueryService::class, function ($mock): void {
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('fetch')->once()->andThrow(
            new RuntimeException('MongoDB inbound sync query failed: connection refused')
        );
    });

    $this->actingAs($user)
        ->postJson(route('inbound-sync.check'), [
            'v_id' => 287,
            'start_date' => '2026-05-10T00:00',
            'end_date' => '2026-06-09T23:59',
        ])
        ->assertStatus(502)
        ->assertJsonPath('message', fn ($message) => str_contains($message, 'failed'));
});

test('inbound sync index shows mongo not configured when database missing', function () {
    config(['database.connections.mongodb_ssh.database' => '']);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('inbound-sync.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('mongoConfigured', false));
});
