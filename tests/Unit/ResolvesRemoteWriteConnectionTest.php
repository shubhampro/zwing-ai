<?php

use App\Enums\DatabaseAccessMode;
use App\Enums\DatabaseDriver;
use App\Exceptions\UnresolvedDynamicDatabaseConnectionException;
use App\Models\DatabaseConnection;
use App\Services\DatabaseConnectionRegistrar;
use App\Support\Database\ResolvesRemoteWriteConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('write for group throws when writes disabled on schema row', function () {
    DatabaseConnection::factory()->write()->create([
        'connection_group' => 'crm',
        'slug' => 'crm_write',
        'driver' => DatabaseDriver::Mysql,
    ]);

    DatabaseConnectionRegistrar::register();

    expect(fn () => ResolvesRemoteWriteConnection::writeForGroup('crm'))
        ->toThrow(UnresolvedDynamicDatabaseConnectionException::class);
});

test('read for group throws when no read row exists', function () {
    DatabaseConnection::factory()->write()->create([
        'connection_group' => 'crm',
        'slug' => 'crm_write',
        'driver' => DatabaseDriver::Mysql,
    ]);

    DatabaseConnectionRegistrar::register();

    expect(fn () => ResolvesRemoteWriteConnection::readForGroup('crm'))
        ->toThrow(UnresolvedDynamicDatabaseConnectionException::class);
});

test('read for group resolves mysql driver from config', function () {
    DatabaseConnection::factory()->create([
        'connection_group' => 'crm',
        'slug' => 'crm_read',
        'access_mode' => DatabaseAccessMode::Read,
        'driver' => DatabaseDriver::Mysql,
    ]);

    DatabaseConnectionRegistrar::register();

    expect(ResolvesRemoteWriteConnection::readForGroup('crm')->getDriverName())->toBe('mysql');
});
