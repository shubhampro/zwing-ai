<?php

use App\Enums\DatabaseAccessMode;
use App\Enums\DatabaseDriver;
use App\Models\DatabaseConnection;
use App\Services\DatabaseConnectionRegistrar;
use Tests\TestCase;

uses(TestCase::class);

test('maps stored row to laravel mysql connection array', function () {
    $row = DatabaseConnection::factory()->make([
        'slug' => 'warehouse_read',
        'connection_group' => 'warehouse',
        'driver' => DatabaseDriver::Mysql,
        'access_mode' => DatabaseAccessMode::Read,
        'password' => null,
        'host' => '10.0.0.5',
        'port' => 3307,
        'database' => 'reporting',
        'username' => 'ro',
    ]);

    $config = DatabaseConnectionRegistrar::toLaravelConnection($row);

    expect($config['driver'])->toBe('mysql')
        ->and($config['host'])->toBe('10.0.0.5')
        ->and($config['port'])->toBe(3307)
        ->and($config['database'])->toBe('reporting')
        ->and($config['username'])->toBe('ro');
});

test('mongodb read row defaults read preference to secondaryPreferred', function () {
    $row = DatabaseConnection::factory()->make([
        'slug' => 'events_read',
        'connection_group' => 'events',
        'driver' => DatabaseDriver::Mongodb,
        'access_mode' => DatabaseAccessMode::Read,
        'password' => null,
        'mongodb_read_preference' => null,
    ]);

    $config = DatabaseConnectionRegistrar::toLaravelConnection($row);

    expect($config['driver'])->toBe('mongodb')
        ->and($config['options']['readPreference'])->toBe('secondaryPreferred');
});
