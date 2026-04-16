<?php

use App\Models\DatabaseConnection;
use App\Models\DatabaseConnectionLog;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to login when viewing connections', function () {
    $this->get(route('database-connections.index'))->assertRedirect(route('login'));
});

test('verified user can view connection index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('database-connections.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('database-connections/index'));
});

test('verified user can store a connection and an activity log is created', function () {
    $user = User::factory()->create();

    $payload = [
        'slug' => 'app_read',
        'connection_group' => 'app',
        'driver' => 'mysql',
        'access_mode' => 'read',
        'label' => null,
        'is_active' => true,
        'writes_enabled' => false,
        'enforce_read_only_sql_guard' => true,
        'url' => null,
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'test',
        'username' => 'u',
        'password' => 'super-secret-1',
        'unix_socket' => null,
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'search_path' => null,
        'sslmode' => null,
        'ssl_ca_path' => null,
        'mongodb_dsn' => null,
        'mongodb_authentication_database' => null,
        'mongodb_read_preference' => null,
        'extra_options' => null,
    ];

    $this->actingAs($user)
        ->post(route('database-connections.store'), $payload)
        ->assertRedirect();

    $connection = DatabaseConnection::query()->where('slug', 'app_read')->first();

    expect($connection)->not->toBeNull();
    expect(DatabaseConnectionLog::query()->where('action', 'created')->count())->toBe(1);
});

test('verified user can update a connection and an activity log is created', function () {
    $user = User::factory()->create();
    $connection = DatabaseConnection::factory()->create([
        'slug' => 't_read',
        'connection_group' => 't',
    ]);

    $payload = [
        'slug' => 't_read',
        'connection_group' => 't',
        'driver' => 'mysql',
        'access_mode' => 'read',
        'label' => 'Updated',
        'is_active' => true,
        'writes_enabled' => false,
        'enforce_read_only_sql_guard' => true,
        'url' => null,
        'host' => '10.0.0.1',
        'port' => 3307,
        'database' => 'db',
        'username' => 'ro',
        'password' => '',
        'unix_socket' => null,
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'search_path' => null,
        'sslmode' => null,
        'ssl_ca_path' => null,
        'mongodb_dsn' => null,
        'mongodb_authentication_database' => null,
        'mongodb_read_preference' => null,
        'extra_options' => null,
    ];

    $this->actingAs($user)
        ->put(route('database-connections.update', $connection), $payload)
        ->assertRedirect();

    expect(DatabaseConnectionLog::query()->where('action', 'updated')->count())->toBe(1);
    expect($connection->fresh()->label)->toBe('Updated');
});

test('test connection endpoint returns success for valid pgsql credentials', function () {
    $user = User::factory()->create();

    // Use TEST_PGSQL_* vars — phpunit does not override these.
    $payload = [
        'driver' => 'pgsql',
        'access_mode' => 'read',
        'host' => env('TEST_PGSQL_HOST', 'localhost'),
        'port' => (int) env('TEST_PGSQL_PORT', 5432),
        'database' => env('TEST_PGSQL_DATABASE', 'zwingai'),
        'username' => env('TEST_PGSQL_USERNAME', 'appuser'),
        'password' => env('TEST_PGSQL_PASSWORD', ''),
    ];

    $this->actingAs($user)
        ->postJson(route('database-connections.test'), $payload)
        ->assertOk()
        ->assertJson(['success' => true]);
});

test('test connection endpoint returns failure for bad credentials', function () {
    $user = User::factory()->create();

    $payload = [
        'driver' => 'pgsql',
        'access_mode' => 'read',
        'host' => '127.0.0.1',
        'port' => 5432,
        'database' => 'nonexistent_db_xyz',
        'username' => 'bad_user_xyz',
        'password' => 'definitely_wrong',
    ];

    $this->actingAs($user)
        ->postJson(route('database-connections.test'), $payload)
        ->assertOk()
        ->assertJson(['success' => false]);
});

test('test connection endpoint restores stored password for existing connection', function () {
    $user = User::factory()->create();
    $connection = DatabaseConnection::factory()->create([
        'driver' => 'pgsql',
        'host' => env('TEST_PGSQL_HOST', 'localhost'),
        'port' => (int) env('TEST_PGSQL_PORT', 5432),
        'database' => env('TEST_PGSQL_DATABASE', 'zwingai'),
        'username' => env('TEST_PGSQL_USERNAME', 'appuser'),
        'password' => env('TEST_PGSQL_PASSWORD', ''),
    ]);

    $payload = [
        'driver' => 'pgsql',
        'access_mode' => 'read',
        'host' => env('TEST_PGSQL_HOST', 'localhost'),
        'port' => (int) env('TEST_PGSQL_PORT', 5432),
        'database' => env('TEST_PGSQL_DATABASE', 'zwingai'),
        'username' => env('TEST_PGSQL_USERNAME', 'appuser'),
        'password' => '',
        'connection_id' => $connection->id,
    ];

    $this->actingAs($user)
        ->postJson(route('database-connections.test'), $payload)
        ->assertOk()
        ->assertJson(['success' => true]);
});

test('verified user can view activity logs page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('database-connections.activity-logs'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('database-connections/activity-logs'));
});
