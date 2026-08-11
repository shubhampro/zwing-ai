<?php

use App\Enums\PayloadComposerSlotShape;
use App\Models\Organization;
use App\Models\PayloadComposer;
use App\Models\PayloadComposerSlot;
use App\Models\SavedSqlQuery;
use App\Models\User;
use App\Services\OrganizationDatabaseConnector;
use App\Services\PayloadComposerGenerator;

test('guests cannot access payload composers', function () {
    $this->get(route('payload-composers.index'))
        ->assertRedirect('/login');
});

test('authenticated user can view payload composers index', function () {
    $user = User::factory()->create();
    PayloadComposer::factory()->for($user)->create([
        'name' => 'Stock audit post',
    ]);

    $this->actingAs($user)
        ->get(route('payload-composers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('payload-composers/index')
            ->has('composers', 1)
            ->where('composers.0.name', 'Stock audit post')
        );
});

test('user can create a payload composer with slots', function () {
    $user = User::factory()->create();
    $query = SavedSqlQuery::factory()->for($user)->create([
        'title' => 'Audit items',
        'sql' => 'SELECT 1 as itemId WHERE code = :allocation_code',
    ]);

    $this->actingAs($user)
        ->post(route('payload-composers.store'), [
            'name' => 'Stock audit',
            'description' => 'Build stock audit payload',
            'scalars' => [
                ['key' => 'storeId', 'required' => true, 'default' => null],
                ['key' => 'referenceNo', 'required' => true, 'default' => null],
            ],
            'slots' => [
                [
                    'key' => 'stockAuditItems',
                    'saved_sql_query_id' => $query->id,
                    'shape' => PayloadComposerSlotShape::Array->value,
                    'sort_order' => 0,
                ],
            ],
        ])
        ->assertRedirect();

    $composer = PayloadComposer::query()->where('user_id', $user->id)->first();

    expect($composer)->not->toBeNull()
        ->and($composer->name)->toBe('Stock audit')
        ->and($composer->scalars)->toHaveCount(2)
        ->and($composer->slots)->toHaveCount(1)
        ->and($composer->slots->first()->key)->toBe('stockAuditItems');
});

test('user cannot attach another users saved sql query', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $query = SavedSqlQuery::factory()->for($other)->create();

    $this->actingAs($user)
        ->post(route('payload-composers.store'), [
            'name' => 'Bad composer',
            'scalars' => [],
            'slots' => [
                [
                    'key' => 'items',
                    'saved_sql_query_id' => $query->id,
                    'shape' => 'array',
                ],
            ],
        ])
        ->assertSessionHasErrors('slots.0.saved_sql_query_id');
});

test('user can generate nested payload from mocked mysql ssh rows', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'db_name' => 'zw_mn_245_quiosco_',
    ]);
    $query = SavedSqlQuery::factory()->for($user)->create([
        'sql' => 'SELECT stockpointId, itemId FROM items WHERE code = :allocation_code',
    ]);
    $composer = PayloadComposer::factory()->for($user)->create([
        'name' => 'Stock audit',
        'scalars' => [
            ['key' => 'storeId', 'required' => true, 'default' => null],
            ['key' => 'referenceNo', 'required' => true, 'default' => null],
        ],
    ]);
    PayloadComposerSlot::factory()->create([
        'payload_composer_id' => $composer->id,
        'key' => 'stockAuditItems',
        'saved_sql_query_id' => $query->id,
        'shape' => PayloadComposerSlotShape::Array,
    ]);

    $connector = Mockery::mock(OrganizationDatabaseConnector::class);
    $connector->shouldReceive('openMysqlSshDatabase')
        ->once()
        ->with('zw_mn_245_quiosco_')
        ->andReturn('mysql_ssh_test');
    $connector->shouldReceive('eachRow')
        ->once()
        ->withArgs(function (string $runtime, string $sql, array $bindings): bool {
            expect($runtime)->toBe('mysql_ssh_test')
                ->and($sql)->toContain('?')
                ->and($bindings)->toBe(['AUP0406202600012']);

            return true;
        })
        ->andReturnUsing(function (string $runtime, string $sql, array $bindings, callable $callback): void {
            $callback([
                'stockpointId' => '1',
                'itemId' => 'PB28131',
                'bookQty' => 15.0,
                'physicalQty' => 13.0,
                'differenceQty' => -2.0,
            ]);
        });
    $connector->shouldReceive('close')->once()->with('mysql_ssh_test');
    $this->app->instance(OrganizationDatabaseConnector::class, $connector);

    $response = $this->actingAs($user)
        ->postJson(route('payload-composers.generate', $composer), [
            'organization_id' => $organization->id,
            'scalars' => [
                'storeId' => '65',
                'referenceNo' => '1780554582665',
            ],
            'bindings' => [
                'allocation_code' => 'AUP0406202600012',
            ],
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('payload.storeId', '65')
        ->assertJsonPath('payload.referenceNo', '1780554582665')
        ->assertJsonPath('payload.stockAuditItems.0.itemId', 'PB28131')
        ->assertJsonPath('meta.row_counts.stockAuditItems', 1)
        ->assertJsonPath('meta.database', 'zw_mn_245_quiosco_');
});

test('generate rejects missing required scalar', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'db_name' => 'zw_test',
    ]);
    $query = SavedSqlQuery::factory()->for($user)->create([
        'sql' => 'SELECT 1 as itemId',
    ]);
    $composer = PayloadComposer::factory()->for($user)->create([
        'scalars' => [
            ['key' => 'storeId', 'required' => true, 'default' => null],
        ],
    ]);
    PayloadComposerSlot::factory()->create([
        'payload_composer_id' => $composer->id,
        'key' => 'items',
        'saved_sql_query_id' => $query->id,
    ]);

    $connector = Mockery::mock(OrganizationDatabaseConnector::class);
    $connector->shouldReceive('openMysqlSshDatabase')->never();
    $this->app->instance(OrganizationDatabaseConnector::class, $connector);

    $this->actingAs($user)
        ->postJson(route('payload-composers.generate', $composer), [
            'organization_id' => $organization->id,
            'scalars' => [],
            'bindings' => [],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('scalars.storeId');
});

test('generate rejects unsafe sql', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'db_name' => 'zw_test',
    ]);
    $query = SavedSqlQuery::factory()->for($user)->create([
        'sql' => 'DELETE FROM items',
    ]);
    $composer = PayloadComposer::factory()->for($user)->create([
        'scalars' => [],
    ]);
    PayloadComposerSlot::factory()->create([
        'payload_composer_id' => $composer->id,
        'key' => 'items',
        'saved_sql_query_id' => $query->id,
    ]);

    $connector = Mockery::mock(OrganizationDatabaseConnector::class);
    $connector->shouldReceive('openMysqlSshDatabase')->once()->andReturn('mysql_ssh_test');
    $connector->shouldReceive('close')->once()->with('mysql_ssh_test');
    $connector->shouldReceive('eachRow')->never();
    $this->app->instance(OrganizationDatabaseConnector::class, $connector);

    $this->actingAs($user)
        ->postJson(route('payload-composers.generate', $composer), [
            'organization_id' => $organization->id,
            'scalars' => [],
            'bindings' => [],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('slots.items');
});

test('user can open edit page for own composer', function () {
    $user = User::factory()->create();
    $query = SavedSqlQuery::factory()->for($user)->create();
    $composer = PayloadComposer::factory()->for($user)->create();
    PayloadComposerSlot::factory()->create([
        'payload_composer_id' => $composer->id,
        'key' => 'items',
        'saved_sql_query_id' => $query->id,
    ]);

    $this->actingAs($user)
        ->get(route('payload-composers.edit', $composer))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('payload-composers/edit')
            ->where('composer.id', $composer->id)
            ->has('savedQueries')
            ->has('slotShapes')
        );
});

test('user cannot view another users composer', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $composer = PayloadComposer::factory()->for($owner)->create();

    $this->actingAs($other)
        ->get(route('payload-composers.show', $composer))
        ->assertForbidden();
});

test('object slot with empty key merges row into payload root like scalars', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'db_name' => 'zw_test',
    ]);
    $headerQuery = SavedSqlQuery::factory()->for($user)->create([
        'sql' => 'SELECT storeId, planName FROM header WHERE code = :allocation_code LIMIT 1',
    ]);
    $itemsQuery = SavedSqlQuery::factory()->for($user)->create([
        'sql' => 'SELECT itemId FROM items WHERE code = :allocation_code',
    ]);
    $composer = PayloadComposer::factory()->for($user)->create([
        'scalars' => [
            ['key' => 'referenceNo', 'required' => true, 'default' => null],
        ],
    ]);
    PayloadComposerSlot::factory()->create([
        'payload_composer_id' => $composer->id,
        'key' => null,
        'saved_sql_query_id' => $headerQuery->id,
        'shape' => PayloadComposerSlotShape::Object,
        'sort_order' => 0,
    ]);
    PayloadComposerSlot::factory()->create([
        'payload_composer_id' => $composer->id,
        'key' => 'stockAuditItems',
        'saved_sql_query_id' => $itemsQuery->id,
        'shape' => PayloadComposerSlotShape::Array,
        'sort_order' => 1,
    ]);

    $connector = Mockery::mock(OrganizationDatabaseConnector::class);
    $connector->shouldReceive('openMysqlSshDatabase')->once()->andReturn('mysql_ssh_test');
    $connector->shouldReceive('eachRow')
        ->twice()
        ->andReturnUsing(function (string $runtime, string $sql, array $bindings, callable $callback): void {
            if (str_contains($sql, 'header')) {
                $callback([
                    'storeId' => '65',
                    'planName' => 'pathanamthitta stock audit',
                ]);

                return;
            }

            $callback(['itemId' => 'PB28131']);
        });
    $connector->shouldReceive('close')->once()->with('mysql_ssh_test');
    $this->app->instance(OrganizationDatabaseConnector::class, $connector);

    $this->actingAs($user)
        ->postJson(route('payload-composers.generate', $composer), [
            'organization_id' => $organization->id,
            'scalars' => [
                'referenceNo' => '1780554582665',
            ],
            'bindings' => [
                'allocation_code' => 'AUP0406202600012',
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('payload.referenceNo', '1780554582665')
        ->assertJsonPath('payload.storeId', '65')
        ->assertJsonPath('payload.planName', 'pathanamthitta stock audit')
        ->assertJsonPath('payload.stockAuditItems.0.itemId', 'PB28131');
});

test('generate respects configured max rows per slot', function () {
    config(['payload-composer.max_rows_per_slot' => 2]);

    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'db_name' => 'zw_test',
    ]);
    $query = SavedSqlQuery::factory()->for($user)->create([
        'sql' => 'SELECT itemId FROM items',
    ]);
    $composer = PayloadComposer::factory()->for($user)->create([
        'scalars' => [],
    ]);
    PayloadComposerSlot::factory()->create([
        'payload_composer_id' => $composer->id,
        'key' => 'stockAuditItems',
        'saved_sql_query_id' => $query->id,
        'shape' => PayloadComposerSlotShape::Array,
    ]);

    $connector = Mockery::mock(OrganizationDatabaseConnector::class);
    $connector->shouldReceive('openMysqlSshDatabase')->once()->andReturn('mysql_ssh_test');
    $connector->shouldReceive('eachRow')
        ->once()
        ->andReturnUsing(function (string $runtime, string $sql, array $bindings, callable $callback): void {
            $callback(['itemId' => '1']);
            $callback(['itemId' => '2']);
            $callback(['itemId' => '3']);
        });
    $connector->shouldReceive('close')->once()->with('mysql_ssh_test');
    $this->app->instance(OrganizationDatabaseConnector::class, $connector);

    $this->actingAs($user)
        ->postJson(route('payload-composers.generate', $composer), [
            'organization_id' => $organization->id,
            'scalars' => [],
            'bindings' => [],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('slots.stockAuditItems');
});

test('empty-key object slot with zero rows does not fail generate', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'db_name' => 'zw_test',
    ]);
    $headerQuery = SavedSqlQuery::factory()->for($user)->create([
        'sql' => 'SELECT storeId FROM header WHERE code = :allocation_code LIMIT 1',
    ]);
    $composer = PayloadComposer::factory()->for($user)->create([
        'scalars' => [
            ['key' => 'referenceNo', 'required' => true, 'default' => null],
        ],
    ]);
    PayloadComposerSlot::factory()->create([
        'payload_composer_id' => $composer->id,
        'key' => null,
        'saved_sql_query_id' => $headerQuery->id,
        'shape' => PayloadComposerSlotShape::Object,
    ]);

    $connector = Mockery::mock(OrganizationDatabaseConnector::class);
    $connector->shouldReceive('openMysqlSshDatabase')->once()->andReturn('mysql_ssh_test');
    $connector->shouldReceive('eachRow')->once()->andReturnUsing(function (): void {
        // zero rows
    });
    $connector->shouldReceive('close')->once()->with('mysql_ssh_test');
    $this->app->instance(OrganizationDatabaseConnector::class, $connector);

    $this->actingAs($user)
        ->postJson(route('payload-composers.generate', $composer), [
            'organization_id' => $organization->id,
            'scalars' => [
                'referenceNo' => '1780554582665',
            ],
            'bindings' => [
                'allocation_code' => 'MISSING',
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('payload.referenceNo', '1780554582665')
        ->assertJsonMissingPath('payload.storeId');
});

test('generator extracts named bindings and converts to positional', function () {
    $generator = app(PayloadComposerGenerator::class);

    expect($generator->extractBindingNames(
        'SELECT * FROM t WHERE a = :foo AND b = :bar AND cast = 1::int',
    ))->toBe(['foo', 'bar']);

    [$sql, $bindings] = $generator->toPositional(
        'SELECT * FROM t WHERE a = :foo AND b = :bar',
        ['foo' => 'x', 'bar' => 2],
    );

    expect($sql)->toBe('SELECT * FROM t WHERE a = ? AND b = ?')
        ->and($bindings)->toBe(['x', 2]);
});
