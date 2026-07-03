<?php

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

afterEach(function () {
    Carbon::setTestNow();
});

test('guests cannot access outbound sync page', function () {
    $this->get(route('outbound-sync.index'))
        ->assertRedirect('/login');
});

test('authenticated user can view outbound sync page', function () {
    Carbon::setTestNow('2026-07-03 12:00:00');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('outbound-sync.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('outbound-sync/index')
            ->has('defaultFilters')
            ->where('defaultFilters.v_id', 266)
            ->where('defaultFilters.partner_code', 'VT2345RTZW87')
            ->where('defaultFilters.start_date', '2026-06-26')
            ->where('defaultFilters.end_date', '2026-07-03')
        );
});

test('guests cannot fetch outbound sync data', function () {
    $this->postJson(route('outbound-sync.fetch'), [
        'v_id' => 266,
        'start_date' => '2026-05-10',
        'end_date' => '2026-06-09',
        'partner_code' => 'VT2345RTZW87',
    ])->assertUnauthorized();
});

test('fetch requires valid filters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('outbound-sync.fetch'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['v_id', 'start_date', 'end_date', 'partner_code']);
});

test('fetch proxies request to gozwing connect api', function () {
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
            ],
            'stats' => [
                'totalSync' => 10,
                'total_sync' => 70.0,
                'remain_sync' => 3,
                'sucessSync' => 7,
                'pending' => 0,
            ],
        ], 200),
    ]);

    $this->actingAs($user)
        ->postJson(route('outbound-sync.fetch'), [
            'v_id' => 266,
            'start_date' => '2026-05-10',
            'end_date' => '2026-06-09',
            'partner_code' => 'VT2345RTZW87',
        ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'stats' => [
                'totalSync' => 10,
                'total_sync' => 70.0,
                'remain_sync' => 3,
                'sucessSync' => 7,
                'pending' => 0,
            ],
        ])
        ->assertJsonPath('result.0.name', 'grn');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://connect.gozwing.com/log/unsynclist'
            && $request['v_id'] === 266
            && $request['startDate'] === '2026-05-10T00:00:00.000Z'
            && $request['endDate'] === '2026-06-09T23:59:00.000Z'
            && $request['partner_code'] === 'VT2345RTZW87'
            && $request->hasHeader('Authorization');
    });
});

test('fetch returns error when external api fails', function () {
    $user = User::factory()->create();

    Http::fake([
        'connect.gozwing.com/log/unsynclist' => Http::response(['error' => 'Unauthorized'], 401),
    ]);

    $this->actingAs($user)
        ->postJson(route('outbound-sync.fetch'), [
            'v_id' => 266,
            'start_date' => '2026-05-10',
            'end_date' => '2026-06-09',
            'partner_code' => 'VT2345RTZW87',
        ])
        ->assertStatus(502)
        ->assertJson([
            'success' => false,
            'status' => 401,
        ]);
});
