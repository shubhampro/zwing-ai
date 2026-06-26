<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.zwing_to_erp.base_url' => 'https://connect.gozwing.com',
        'services.zwing_to_erp.username' => 'global_user',
        'services.zwing_to_erp.password' => 'Global@12345$',
    ]);
});

test('outbound unsync index page loads for authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('outbound-unsync.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('outbound-unsync/index')
            ->has('organizations')
            ->has('defaultStartDate')
            ->has('defaultEndDate')
            ->where('defaultPartnerCode', 'VT2345RTZW87')
            ->where('apiConfigured', true)
        );
});

test('outbound unsync index redirects unauthenticated user', function () {
    $this->get(route('outbound-unsync.index'))->assertRedirect('/login');
});

test('outbound unsync check returns normalized api response', function () {
    $user = User::factory()->create();

    Http::fake([
        'connect.gozwing.com/log/unsynclist' => Http::response([
            'success' => true,
            'result' => [
                [
                    'name' => 'grn',
                    'trans' => 10,
                    'val' => 7,
                    'failCnt' => 3,
                    'needToSync' => ['GR001A0426000001'],
                    'eventMiss' => [],
                    'pending' => 0,
                    'successSync' => 7,
                ],
                [
                    'name' => 'invoice',
                    'trans' => 5,
                    'val' => 5,
                    'failCnt' => 0,
                    'needToSync' => [],
                    'eventMiss' => ['INV001'],
                    'pending' => 5,
                    'successSync' => 0,
                ],
            ],
            'stats' => [
                'totalSync' => 15,
                'total_sync' => 66.67,
                'remain_sync' => 5,
                'sucessSync' => 10,
                'pending' => 5,
            ],
        ]),
    ]);

    $this->actingAs($user)
        ->postJson(route('outbound-unsync.check'), [
            'v_id' => 266,
            'partner_code' => 'VT2345RTZW87',
            'start_date' => '2026-05-10T00:00',
            'end_date' => '2026-06-09T23:59',
        ])
        ->assertOk()
        ->assertJsonPath('filters.v_id', 266)
        ->assertJsonPath('filters.partner_code', 'VT2345RTZW87')
        ->assertJsonPath('stats.total_sync', 15)
        ->assertJsonPath('stats.success_sync', 10)
        ->assertJsonPath('result.0.name', 'grn')
        ->assertJsonPath('result.0.status', 'failed')
        ->assertJsonPath('result.0.need_to_sync.0', 'GR001A0426000001')
        ->assertJsonPath('result.1.status', 'pending')
        ->assertJsonPath('result.1.event_miss.0', 'INV001');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://connect.gozwing.com/log/unsynclist'
            && $request['v_id'] === 266
            && $request['partner_code'] === 'VT2345RTZW87'
            && str_starts_with($request['startDate'], '2026-05-10')
            && str_starts_with($request['endDate'], '2026-06-09')
            && ! isset($request['event_name']);
    });
});

test('outbound unsync check filters by event name', function () {
    $user = User::factory()->create();

    Http::fake([
        'connect.gozwing.com/log/unsynclist' => Http::response([
            'success' => true,
            'result' => [
                [
                    'name' => 'grn',
                    'trans' => 10,
                    'val' => 7,
                    'failCnt' => 0,
                    'needToSync' => [],
                    'eventMiss' => [],
                    'pending' => 3,
                    'successSync' => 7,
                ],
                [
                    'name' => 'invoice',
                    'trans' => 5,
                    'val' => 0,
                    'failCnt' => 0,
                    'needToSync' => [],
                    'eventMiss' => ['INV001'],
                    'pending' => 5,
                    'successSync' => 0,
                ],
            ],
            'stats' => [
                'totalSync' => 15,
                'total_sync' => 66.67,
                'remain_sync' => 8,
                'sucessSync' => 7,
                'pending' => 8,
            ],
        ]),
    ]);

    $this->actingAs($user)
        ->postJson(route('outbound-unsync.check'), [
            'v_id' => 245,
            'partner_code' => 'VT2345RTZW87',
            'start_date' => '2026-06-01T05:30',
            'end_date' => '2026-06-05T05:29',
            'event_name' => 'invoice',
        ])
        ->assertOk()
        ->assertJsonPath('filters.event_name', 'invoice')
        ->assertJsonCount(1, 'result')
        ->assertJsonPath('result.0.name', 'invoice')
        ->assertJsonPath('stats.total_sync', 5)
        ->assertJsonPath('stats.pending', 5);

    Http::assertSent(fn ($request) => $request['event_name'] === 'invoice');
});

test('outbound unsync check validates request', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('outbound-unsync.check'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['v_id', 'partner_code', 'start_date', 'end_date']);
});

test('outbound unsync check returns 503 when api is not configured', function () {
    config([
        'services.zwing_to_erp.base_url' => '',
        'services.zwing_to_erp.username' => '',
        'services.zwing_to_erp.password' => '',
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('outbound-unsync.check'), [
            'v_id' => 245,
            'partner_code' => 'VT2345RTZW87',
            'start_date' => '2026-05-10T00:00',
            'end_date' => '2026-06-09T23:59',
        ])
        ->assertStatus(503)
        ->assertJsonPath('message', fn ($message) => str_contains($message, 'not configured'));
});

test('outbound unsync check returns 502 when api fails', function () {
    $user = User::factory()->create();

    Http::fake([
        'connect.gozwing.com/log/unsynclist' => Http::response(['message' => 'Unauthorized'], 401),
    ]);

    $this->actingAs($user)
        ->postJson(route('outbound-unsync.check'), [
            'v_id' => 245,
            'partner_code' => 'VT2345RTZW87',
            'start_date' => '2026-05-10T00:00',
            'end_date' => '2026-06-09T23:59',
        ])
        ->assertStatus(502)
        ->assertJsonPath('message', fn ($message) => str_contains($message, 'failed'));
});
