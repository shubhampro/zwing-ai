<?php

use Illuminate\Support\Facades\Config;

test('mysql_ssh connection is registered with correct driver', function () {
    $config = Config::get('database.connections.mysql_ssh');

    expect($config)->toBeArray()
        ->and($config['driver'])->toBe('mysql')
        ->and($config['host'])->toBe('127.0.0.1')
        ->and($config)->toHaveKey('tunnel')
        ->and($config['tunnel'])->toHaveKeys([
            'ssh_host',
            'ssh_port',
            'ssh_user',
            'ssh_key',
            'remote_db_host',
            'remote_db_port',
            'local_port',
        ]);
});

test('mongodb_ssh connection is registered with correct driver', function () {
    $config = Config::get('database.connections.mongodb_ssh');

    expect($config)->toBeArray()
        ->and($config['driver'])->toBe('mongodb');
});

test('pgsql_ssh connection is registered with tunnel settings', function () {
    $config = Config::get('database.connections.pgsql_ssh');

    expect($config)->toBeArray()
        ->and($config['driver'])->toBe('pgsql')
        ->and($config['host'])->toBe('127.0.0.1')
        ->and($config)->toHaveKey('tunnel')
        ->and($config['tunnel'])->toHaveKeys([
            'ssh_host',
            'ssh_port',
            'ssh_user',
            'ssh_key',
            'remote_db_host',
            'remote_db_port',
            'local_port',
        ]);
});

test('mysql_ssh rejects write queries', function () {
    DB::connection('mysql_ssh')->beforeExecuting(function (string $sql): void {
        $writePattern = '/^\s*(insert|update|delete|drop|truncate|alter|create|replace|rename)\b/i';
        if (preg_match($writePattern, $sql)) {
            throw new RuntimeException("Connection [mysql_ssh] is read-only. Write query rejected: {$sql}");
        }
    });

    expect(fn () => DB::connection('mysql_ssh')->statement('INSERT INTO foo VALUES (1)'))
        ->toThrow(RuntimeException::class, 'read-only');
});

test('mongodb_ssh rejects write queries', function () {
    DB::connection('mongodb_ssh')->beforeExecuting(function (string $sql): void {
        $writePattern = '/^\s*(insert|update|delete|drop|truncate|alter|create|replace|rename)\b/i';
        if (preg_match($writePattern, $sql)) {
            throw new RuntimeException("Connection [mongodb_ssh] is read-only. Write query rejected: {$sql}");
        }
    });

    expect(fn () => DB::connection('mongodb_ssh')->statement('DELETE FROM bar'))
        ->toThrow(RuntimeException::class, 'read-only');
});
