<?php

use App\Enums\DatabaseConnectionType;
use App\Models\OrganizationDatabaseConnection;
use App\Services\OrganizationDatabaseConnectionTester;
use App\Services\SshTunnelManager;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    SshTunnelManager::$fake = true;
});

it('reports success when select 1 works through runtime connection', function () {
    $connection = new OrganizationDatabaseConnection([
        'type' => DatabaseConnectionType::Pgsql,
        'database_name' => 'org_db',
        'username' => 'org_user',
        'password' => 'org_pass',
        'host' => '127.0.0.1',
        'port' => 5432,
    ]);
    $connection->id = 99;

    $dbConnection = Mockery::mock(Connection::class);
    $dbConnection->shouldReceive('select')
        ->once()
        ->with('select 1 as ok')
        ->andReturn([(object) ['ok' => 1]]);

    DB::shouldReceive('purge')->twice()->with('org_db_test_99');
    DB::shouldReceive('connection')->once()->with('org_db_test_99')->andReturn($dbConnection);

    $result = app(OrganizationDatabaseConnectionTester::class)->test($connection);

    expect($result['ok'])->toBeTrue()
        ->and($result['message'])->toContain('successful')
        ->and($result['latency_ms'])->toBeInt();
});

it('reports failure when database connection throws', function () {
    $connection = new OrganizationDatabaseConnection([
        'type' => DatabaseConnectionType::Mysql,
        'database_name' => 'org_db',
        'username' => 'org_user',
        'password' => 'bad',
        'host' => '127.0.0.1',
        'port' => 3306,
    ]);
    $connection->id = 7;

    $dbConnection = Mockery::mock(Connection::class);
    $dbConnection->shouldReceive('select')
        ->once()
        ->andThrow(new RuntimeException('Access denied'));

    DB::shouldReceive('purge')->twice()->with('org_db_test_7');
    DB::shouldReceive('connection')->once()->with('org_db_test_7')->andReturn($dbConnection);

    $result = app(OrganizationDatabaseConnectionTester::class)->test($connection);

    expect($result['ok'])->toBeFalse()
        ->and($result['message'])->toContain('Access denied');
});
