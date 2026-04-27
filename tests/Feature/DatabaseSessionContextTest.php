<?php

use App\Enums\DatabaseAccessMode;
use App\Enums\DatabaseDriver;
use App\Models\DatabaseConnection;
use App\Models\User;
use App\Services\ListRemoteMysqlDatabases;
use App\Support\Database\ActiveRemoteDatabaseContext;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot list remote databases', function () {
    $this->getJson(route('database-session-context.databases', ['connection_slug' => 'any']))
        ->assertUnauthorized();
});

test('guests cannot update database session context', function () {
    $this->putJson(route('database-session-context.update'), [
        'connection_slug' => 'any',
        'database' => null,
    ])->assertUnauthorized();
});

test('verified user can store active connection and database in session', function () {
    $user = User::factory()->create();

    $connection = DatabaseConnection::factory()->create([
        'slug' => 'ctx_read',
        'connection_group' => 'ctx',
        'driver' => DatabaseDriver::Mysql,
        'access_mode' => DatabaseAccessMode::Read,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->putJson(route('database-session-context.update'), [
            'connection_slug' => 'ctx_read',
            'database' => 'sales_db',
        ])
        ->assertOk()
        ->assertJson(['message' => 'Context updated.']);

    expect(session('remote_database_context.slug'))->toBe('ctx_read');
    expect(session('remote_database_context.database'))->toBe('sales_db');

    expect(ActiveRemoteDatabaseContext::forInertiaShared())->toMatchArray([
        'connection_slug' => 'ctx_read',
        'database' => 'sales_db',
        'driver' => 'mysql',
    ]);
});

test('list databases returns json for mysql connection using list service', function () {
    $user = User::factory()->create();

    DatabaseConnection::factory()->create([
        'slug' => 'list_mysql_test',
        'connection_group' => 'list_t',
        'driver' => DatabaseDriver::Mysql,
        'access_mode' => DatabaseAccessMode::Read,
        'is_active' => true,
    ]);

    $this->app->instance(ListRemoteMysqlDatabases::class, new class extends ListRemoteMysqlDatabases
    {
        /**
         * @return list<string>
         */
        public function __invoke(string $connectionSlug): array
        {
            return ['inventory', 'warehouse'];
        }
    });

    $this->actingAs($user)
        ->getJson(route('database-session-context.databases', ['connection_slug' => 'list_mysql_test']))
        ->assertOk()
        ->assertJson(['data' => ['inventory', 'warehouse']]);
});

test('list databases returns 422 for non mysql driver', function () {
    $user = User::factory()->create();

    DatabaseConnection::factory()->create([
        'slug' => 'pg_test',
        'connection_group' => 'pg_t',
        'driver' => DatabaseDriver::Pgsql,
        'access_mode' => DatabaseAccessMode::Read,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->getJson(route('database-session-context.databases', ['connection_slug' => 'pg_test']))
        ->assertStatus(422);
});

test('dashboard shared props include active database context after update', function () {
    $user = User::factory()->create();

    DatabaseConnection::factory()->create([
        'slug' => 'dash_read',
        'connection_group' => 'dash',
        'driver' => DatabaseDriver::Mysql,
        'access_mode' => DatabaseAccessMode::Read,
        'is_active' => true,
        'label' => 'Primary',
    ]);

    $this->actingAs($user)
        ->put(route('database-session-context.update'), [
            'connection_slug' => 'dash_read',
            'database' => null,
        ], ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('activeDatabaseContext.connection_slug', 'dash_read')
            ->where('activeDatabaseContext.database', null)
            ->where('activeDatabaseContext.connection_label', 'Primary'));
});
