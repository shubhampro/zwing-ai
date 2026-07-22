<?php

use App\Enums\DatabaseConnectionType;
use App\Models\OrganizationDatabaseConnection;
use App\Services\OrganizationDatabaseConnector;
use App\Services\SshTunnelManager;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    SshTunnelManager::$fake = true;
});

it('opens a runtime connection config through ssh tunnel', function () {
    $connection = new OrganizationDatabaseConnection([
        'type' => DatabaseConnectionType::Pgsql,
        'database_name' => 'org_db',
        'username' => 'org_user',
        'password' => 'secret',
        'host' => 'pg.example.com',
        'port' => 5432,
    ]);
    $connection->id = 12;

    $connector = app(OrganizationDatabaseConnector::class);
    $runtimeName = $connector->open($connection, 'org_db_test_open');

    expect($runtimeName)->toBe('org_db_test_open')
        ->and(Config::get('database.connections.org_db_test_open.host'))->toBe('127.0.0.1')
        ->and(Config::get('database.connections.org_db_test_open.database'))->toBe('org_db')
        ->and(Config::get('database.connections.org_db_test_open.username'))->toBe('org_user')
        ->and(Config::get('database.connections.org_db_test_open.password'))->toBe('secret')
        ->and(Config::get('database.connections.org_db_test_open.port'))->toBeInt()
        ->and(Config::get('database.connections.org_db_test_open.search_path'))->toBe('main, public, ginview');

    $connector->close($runtimeName);

    expect(Config::get('database.connections.org_db_test_open'))->toBeNull();
});

it('opens shared mysql_ssh with an org database name', function () {
    Config::set('database.connections.mysql_ssh.database', 'shared_default');
    Config::set('database.connections.mysql_ssh.username', 'ssh_user');
    Config::set('database.connections.mysql_ssh.password', 'ssh_pass');

    $connector = app(OrganizationDatabaseConnector::class);
    $runtimeName = $connector->openMysqlSshDatabase('zw_mn_99_demo', 'mysql_ssh_test_open');

    expect($runtimeName)->toBe('mysql_ssh_test_open')
        ->and(Config::get('database.connections.mysql_ssh_test_open.database'))->toBe('zw_mn_99_demo')
        ->and(Config::get('database.connections.mysql_ssh_test_open.username'))->toBe('ssh_user')
        ->and(Config::get('database.connections.mysql_ssh_test_open.password'))->toBe('ssh_pass')
        ->and(Config::get('database.connections.mysql_ssh_test_open'))->not->toHaveKey('tunnel');

    $connector->close($runtimeName);

    expect(Config::get('database.connections.mysql_ssh_test_open'))->toBeNull();
});
