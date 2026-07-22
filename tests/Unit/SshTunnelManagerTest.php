<?php

use App\Enums\DatabaseConnectionType;
use App\Services\SshTunnelManager;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    SshTunnelManager::$fake = false;
});

afterEach(function () {
    SshTunnelManager::$fake = true;
});

test('resolveKeyPath accepts filename inside ssh key directory', function () {
    $dir = sys_get_temp_dir().'/ssh_keys_'.bin2hex(random_bytes(4));
    mkdir($dir, 0700);
    $keyFile = $dir.'/id_ed25519_work';
    file_put_contents($keyFile, "-----BEGIN OPENSSH PRIVATE KEY-----\ntest\n-----END OPENSSH PRIVATE KEY-----\n");
    chmod($keyFile, 0600);

    $resolved = SshTunnelManager::resolveKeyPath('id_ed25519_work', $dir);

    expect($resolved)->toBe(realpath($keyFile));

    @unlink($keyFile);
    @rmdir($dir);
});

test('resolveKeyPath rejects keys outside ssh key directory', function () {
    $dir = sys_get_temp_dir().'/ssh_keys_'.bin2hex(random_bytes(4));
    mkdir($dir, 0700);

    $outside = sys_get_temp_dir().'/outside_key_'.bin2hex(random_bytes(4));
    file_put_contents($outside, "-----BEGIN OPENSSH PRIVATE KEY-----\ntest\n-----END OPENSSH PRIVATE KEY-----\n");
    chmod($outside, 0600);

    expect(fn () => SshTunnelManager::resolveKeyPath($outside, $dir))
        ->toThrow(RuntimeException::class, 'must live inside the SSH key directory');

    @unlink($outside);
    @rmdir($dir);
});

test('resolveKeyPath rejects missing key file', function () {
    $dir = sys_get_temp_dir().'/ssh_keys_'.bin2hex(random_bytes(4));
    mkdir($dir, 0700);

    expect(fn () => SshTunnelManager::resolveKeyPath('missing_key', $dir))
        ->toThrow(RuntimeException::class, 'not found');

    @rmdir($dir);
});

test('ensureMysqlOpen is a no-op when fake is enabled', function () {
    SshTunnelManager::$fake = true;

    Config::set('database.connections.mysql_ssh.tunnel', [
        'ssh_host' => '',
        'ssh_user' => '',
        'ssh_key' => '',
        'local_port' => 65530,
    ]);

    SshTunnelManager::ensureMysqlOpen();

    expect(true)->toBeTrue();
});

test('ensureMysqlOpen throws when tunnel not configured and port closed', function () {
    Config::set('database.connections.mysql_ssh.tunnel', [
        'ssh_host' => '',
        'ssh_user' => '',
        'ssh_key' => '',
        'local_port' => 65530,
    ]);

    expect(fn () => SshTunnelManager::ensureMysqlOpen())
        ->toThrow(RuntimeException::class, 'not configured');
});

test('localPortFor reuses base port for default remote endpoint', function () {
    Config::set('database.connections.pgsql_ssh.tunnel', [
        'local_port' => 5433,
        'remote_db_host' => '127.0.0.1',
        'remote_db_port' => 5432,
    ]);

    expect(SshTunnelManager::localPortFor(
        DatabaseConnectionType::Pgsql,
        '127.0.0.1',
        5432,
    ))->toBe(5433);
});

test('localPortFor assigns distinct ports for different remote hosts', function () {
    Config::set('database.connections.pgsql_ssh.tunnel', [
        'local_port' => 5433,
        'remote_db_host' => '127.0.0.1',
        'remote_db_port' => 5432,
    ]);

    $portA = SshTunnelManager::localPortFor(
        DatabaseConnectionType::Pgsql,
        'pgflex-a.postgres.database.azure.com',
        5432,
    );
    $portB = SshTunnelManager::localPortFor(
        DatabaseConnectionType::Pgsql,
        'pgflex-b.postgres.database.azure.com',
        5432,
    );

    expect($portA)->not->toBe(5433)
        ->and($portB)->not->toBe(5433)
        ->and($portA)->not->toBe($portB)
        ->and($portA)->toBeGreaterThanOrEqual(20000)
        ->and($portA)->toBeLessThan(30000);
});

test('ensureForRemote returns local port when fake', function () {
    SshTunnelManager::$fake = true;

    Config::set('database.connections.pgsql_ssh.tunnel', [
        'local_port' => 5433,
        'remote_db_host' => '127.0.0.1',
        'remote_db_port' => 5432,
    ]);

    $port = SshTunnelManager::ensureForRemote(
        DatabaseConnectionType::Pgsql,
        'pgflex-erpdb-prod-02.postgres.database.azure.com',
        5432,
    );

    expect($port)->toBe(SshTunnelManager::localPortFor(
        DatabaseConnectionType::Pgsql,
        'pgflex-erpdb-prod-02.postgres.database.azure.com',
        5432,
    ));
});
