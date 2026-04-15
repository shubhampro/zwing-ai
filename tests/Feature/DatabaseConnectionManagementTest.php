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
        'ssh_tunnel' => null,
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
        'ssh_tunnel' => null,
        'extra_options' => null,
    ];

    $this->actingAs($user)
        ->put(route('database-connections.update', $connection), $payload)
        ->assertRedirect();

    expect(DatabaseConnectionLog::query()->where('action', 'updated')->count())->toBe(1);
    expect($connection->fresh()->label)->toBe('Updated');
});

test('verified user can view activity logs page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('database-connections.activity-logs'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('database-connections/activity-logs'));
});
