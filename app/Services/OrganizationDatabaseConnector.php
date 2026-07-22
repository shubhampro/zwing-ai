<?php

namespace App\Services;

use App\Enums\DatabaseConnectionType;
use App\Models\OrganizationDatabaseConnection;
use App\Support\DatabaseHost;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

class OrganizationDatabaseConnector
{
    /**
     * Open a runtime Laravel DB connection through the SSH bastion.
     *
     * Caller must call close() when finished.
     */
    public function open(OrganizationDatabaseConnection $connection, ?string $runtimeName = null): string
    {
        $runtimeName ??= 'org_db_'.$connection->id.'_'.uniqid();

        [$remoteHost, $remotePort] = $this->remoteEndpoint($connection);
        $localPort = SshTunnelManager::ensureForRemote(
            $connection->type,
            $remoteHost,
            $remotePort,
        );

        Config::set(
            "database.connections.{$runtimeName}",
            $this->buildConfig($connection, $localPort),
        );
        DB::purge($runtimeName);

        return $runtimeName;
    }

    /**
     * Open the shared mysql_ssh tunnel/login with an org-specific database name.
     *
     * Caller must call close() when finished.
     */
    public function openMysqlSshDatabase(string $database, ?string $runtimeName = null): string
    {
        $database = trim($database);

        if ($database === '') {
            throw new RuntimeException('MySQL database name is required.');
        }

        SshTunnelManager::ensureMysqlOpen();

        $runtimeName ??= 'mysql_ssh_'.uniqid();

        /** @var array<string, mixed> $config */
        $config = config('database.connections.mysql_ssh', []);
        $config = is_array($config) ? $config : [];
        unset($config['tunnel']);
        $config['database'] = $database;
        $config['options'] = array_replace(
            is_array($config['options'] ?? null) ? $config['options'] : [],
            [PDO::ATTR_TIMEOUT => 5],
        );

        Config::set("database.connections.{$runtimeName}", $config);
        DB::purge($runtimeName);

        return $runtimeName;
    }

    public function connection(string $runtimeName): Connection
    {
        return DB::connection($runtimeName);
    }

    public function close(string $runtimeName): void
    {
        DB::purge($runtimeName);

        /** @var array<string, mixed> $connections */
        $connections = config('database.connections', []);
        unset($connections[$runtimeName]);
        Config::set('database.connections', $connections);
    }

    /**
     * Stream query results row-by-row via PDO (avoids loading full result set).
     *
     * @param  list<mixed>  $bindings
     * @param  callable(array<string, mixed>): void  $callback
     */
    public function eachRow(string $runtimeName, string $sql, array $bindings, callable $callback): void
    {
        $pdo = $this->connection($runtimeName)->getPdo();
        $statement = $pdo->prepare($sql);

        if ($statement === false) {
            throw new RuntimeException('Failed to prepare database query.');
        }

        $statement->execute($bindings);

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            /** @var array<string, mixed> $row */
            $callback($row);
        }
    }

    /**
     * @return array{0: string, 1: int}
     */
    public function remoteEndpoint(OrganizationDatabaseConnection $connection): array
    {
        $host = DatabaseHost::normalize($connection->host);

        if ($host === null) {
            $host = match ($connection->type) {
                DatabaseConnectionType::Mysql => (string) config(
                    'database.connections.mysql_ssh.tunnel.remote_db_host',
                    '127.0.0.1',
                ),
                DatabaseConnectionType::Pgsql => (string) config(
                    'database.connections.pgsql_ssh.tunnel.remote_db_host',
                    '127.0.0.1',
                ),
            };
        }

        if ($host === '') {
            throw new RuntimeException(
                'Remote database host is missing. Set host on the organization connection.',
            );
        }

        $port = $connection->port ?? match ($connection->type) {
            DatabaseConnectionType::Mysql => (int) config(
                'database.connections.mysql_ssh.tunnel.remote_db_port',
                3306,
            ),
            DatabaseConnectionType::Pgsql => (int) config(
                'database.connections.pgsql_ssh.tunnel.remote_db_port',
                5432,
            ),
        };

        return [$host, $port];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildConfig(OrganizationDatabaseConnection $connection, int $localPort): array
    {
        $base = match ($connection->type) {
            DatabaseConnectionType::Mysql => config('database.connections.mysql_ssh', []),
            DatabaseConnectionType::Pgsql => config('database.connections.pgsql_ssh', []),
        };

        /** @var array<string, mixed> $config */
        $config = is_array($base) ? $base : [];
        unset($config['tunnel']);

        $config['host'] = '127.0.0.1';
        $config['port'] = $localPort;
        $config['database'] = (string) $connection->database_name;
        $config['username'] = (string) $connection->username;
        $config['password'] = (string) $connection->password;
        $config['options'] = array_replace(
            is_array($config['options'] ?? null) ? $config['options'] : [],
            [PDO::ATTR_TIMEOUT => 5],
        );

        // Org ERP DBs keep inventory tables in `main`, views in `ginview`.
        // Keep plain `invitem` resolvable while ginview views stay qualified.
        if ($connection->type === DatabaseConnectionType::Pgsql) {
            $config['search_path'] = 'main, public, ginview';
        }

        return $config;
    }
}
