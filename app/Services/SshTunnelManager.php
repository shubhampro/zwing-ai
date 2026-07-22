<?php

namespace App\Services;

use App\Enums\DatabaseConnectionType;
use RuntimeException;

final class SshTunnelManager
{
    /**
     * Skip actually opening tunnels (set true in test environments).
     */
    public static bool $fake = false;

    /**
     * Ensure the MySQL SSH tunnel from config is open (lazy).
     *
     * Uses a private key from the SSH key directory (~/.ssh by default).
     * If the local port is already listening, this is a no-op.
     * If tunnel SSH settings are blank but the local port is up, assumes a
     * manually opened tunnel (backward compatible).
     */
    public static function ensureMysqlOpen(): void
    {
        if (self::$fake) {
            return;
        }

        /** @var array<string, mixed> $tunnel */
        $tunnel = config('database.connections.mysql_ssh.tunnel', []);
        $localPort = (int) ($tunnel['local_port'] ?? 3307);

        if (self::isPortListening($localPort)) {
            return;
        }

        $host = (string) ($tunnel['ssh_host'] ?? '');
        $user = (string) ($tunnel['ssh_user'] ?? '');
        $key = (string) ($tunnel['ssh_key'] ?? '');

        if ($host === '' || $user === '' || $key === '') {
            throw new RuntimeException(
                'MySQL SSH tunnel is not open and MYSQL_SSH_HOST / MYSQL_SSH_USER / MYSQL_SSH_KEY are not configured.',
            );
        }

        self::ensureOpen([
            'ssh_host' => $host,
            'ssh_port' => $tunnel['ssh_port'] ?? 22,
            'ssh_user' => $user,
            'ssh_key' => $key,
            'ssh_key_dir' => $tunnel['ssh_key_dir'] ?? null,
            'remote_db_host' => $tunnel['remote_db_host'] ?? '127.0.0.1',
            'remote_db_port' => $tunnel['remote_db_port'] ?? 3306,
            'local_port' => $localPort,
        ]);
    }

    /**
     * Ensure the Postgres SSH tunnel from config is open (lazy).
     */
    public static function ensurePgsqlOpen(): void
    {
        if (self::$fake) {
            return;
        }

        /** @var array<string, mixed> $tunnel */
        $tunnel = config('database.connections.pgsql_ssh.tunnel', []);
        $localPort = (int) ($tunnel['local_port'] ?? 5433);

        if (self::isPortListening($localPort)) {
            return;
        }

        $host = (string) ($tunnel['ssh_host'] ?? '');
        $user = (string) ($tunnel['ssh_user'] ?? '');
        $key = (string) ($tunnel['ssh_key'] ?? '');

        if ($host === '' || $user === '' || $key === '') {
            throw new RuntimeException(
                'Postgres SSH tunnel is not open and PGSQL_SSH_HOST / PGSQL_SSH_USER / PGSQL_SSH_KEY are not configured.',
            );
        }

        self::ensureOpen([
            'ssh_host' => $host,
            'ssh_port' => $tunnel['ssh_port'] ?? 22,
            'ssh_user' => $user,
            'ssh_key' => $key,
            'ssh_key_dir' => $tunnel['ssh_key_dir'] ?? null,
            'remote_db_host' => $tunnel['remote_db_host'] ?? '127.0.0.1',
            'remote_db_port' => $tunnel['remote_db_port'] ?? 5432,
            'local_port' => $localPort,
        ]);
    }

    public static function ensureForDatabaseType(DatabaseConnectionType $type): void
    {
        match ($type) {
            DatabaseConnectionType::Mysql => self::ensureMysqlOpen(),
            DatabaseConnectionType::Pgsql => self::ensurePgsqlOpen(),
        };
    }

    /**
     * Open (or reuse) an SSH tunnel to a remote DB host through the shared bastion.
     *
     * Returns the local port Laravel should connect to on 127.0.0.1.
     * Different remote host/port pairs get distinct local ports so tunnels do not clash.
     */
    public static function ensureForRemote(
        DatabaseConnectionType $type,
        string $remoteDbHost,
        int $remoteDbPort,
    ): int {
        $remoteDbHost = trim($remoteDbHost);

        if ($remoteDbHost === '') {
            throw new RuntimeException('Remote database host is required for SSH tunnel.');
        }

        $tunnel = self::bastionTunnelConfig($type);
        $localPort = self::localPortFor($type, $remoteDbHost, $remoteDbPort);

        if (self::$fake) {
            return $localPort;
        }

        $host = (string) ($tunnel['ssh_host'] ?? '');
        $user = (string) ($tunnel['ssh_user'] ?? '');
        $key = (string) ($tunnel['ssh_key'] ?? '');

        if ($host === '' || $user === '' || $key === '') {
            $prefix = $type === DatabaseConnectionType::Mysql ? 'MYSQL_SSH' : 'PGSQL_SSH';

            throw new RuntimeException(
                "{$type->value} SSH bastion is not configured. Set {$prefix}_HOST / {$prefix}_USER / {$prefix}_KEY.",
            );
        }

        self::ensureOpen([
            'ssh_host' => $host,
            'ssh_port' => $tunnel['ssh_port'] ?? 22,
            'ssh_user' => $user,
            'ssh_key' => $key,
            'ssh_key_dir' => $tunnel['ssh_key_dir'] ?? null,
            'remote_db_host' => $remoteDbHost,
            'remote_db_port' => $remoteDbPort,
            'local_port' => $localPort,
        ]);

        return $localPort;
    }

    /**
     * Stable local port for a type + remote DB endpoint.
     */
    public static function localPortFor(
        DatabaseConnectionType $type,
        string $remoteDbHost,
        int $remoteDbPort,
    ): int {
        $tunnel = self::bastionTunnelConfig($type);
        $basePort = (int) ($tunnel['local_port'] ?? match ($type) {
            DatabaseConnectionType::Mysql => 3307,
            DatabaseConnectionType::Pgsql => 5433,
        });
        $defaultRemoteHost = (string) ($tunnel['remote_db_host'] ?? '127.0.0.1');
        $defaultRemotePort = (int) ($tunnel['remote_db_port'] ?? match ($type) {
            DatabaseConnectionType::Mysql => 3306,
            DatabaseConnectionType::Pgsql => 5432,
        });

        if ($remoteDbHost === $defaultRemoteHost && $remoteDbPort === $defaultRemotePort) {
            return $basePort;
        }

        $hash = crc32($type->value.'|'.$remoteDbHost.'|'.$remoteDbPort);

        return 20000 + ($hash % 10000);
    }

    /**
     * @return array<string, mixed>
     */
    private static function bastionTunnelConfig(DatabaseConnectionType $type): array
    {
        /** @var array<string, mixed> $tunnel */
        $tunnel = match ($type) {
            DatabaseConnectionType::Mysql => config('database.connections.mysql_ssh.tunnel', []),
            DatabaseConnectionType::Pgsql => config('database.connections.pgsql_ssh.tunnel', []),
        };

        return is_array($tunnel) ? $tunnel : [];
    }

    /**
     * Ensure an SSH tunnel is open for the given tunnel config.
     *
     * Checks whether the local_port is already accepting connections before
     * attempting to spawn a new ssh process. The private key is read from
     * the SSH key directory (never pasted into the app).
     *
     * @param  array{
     *   ssh_host: string,
     *   ssh_port?: int|string|null,
     *   ssh_user: string,
     *   ssh_key: string,
     *   ssh_key_dir?: string|null,
     *   remote_db_host: string,
     *   remote_db_port: int|string,
     *   local_port: int|string
     * }  $tunnel
     */
    public static function ensureOpen(array $tunnel): void
    {
        if (self::$fake) {
            return;
        }

        $localPort = (int) $tunnel['local_port'];

        if (self::isPortListening($localPort)) {
            return;
        }

        $keyPath = self::resolveKeyPath(
            (string) $tunnel['ssh_key'],
            $tunnel['ssh_key_dir'] ?? null,
        );

        self::spawnTunnel($tunnel, $keyPath);
        self::waitForPort($localPort);
    }

    /**
     * Resolve a key name/path to an absolute file under the SSH key directory.
     */
    public static function resolveKeyPath(string $key, ?string $keyDir = null): string
    {
        $key = trim($key);

        if ($key === '') {
            throw new RuntimeException('SSH key path is empty.');
        }

        $sshDir = self::resolveSshKeyDir($keyDir);

        if (str_starts_with($key, '~/')) {
            $home = self::homeDirectory();
            $key = $home.substr($key, 1);
        }

        if (! str_starts_with($key, DIRECTORY_SEPARATOR)) {
            $key = $sshDir.DIRECTORY_SEPARATOR.basename($key);
        }

        $realKey = realpath($key);
        $realDir = realpath($sshDir);

        if ($realDir === false) {
            throw new RuntimeException("SSH key directory does not exist: {$sshDir}");
        }

        if ($realKey === false || ! is_file($realKey)) {
            throw new RuntimeException("SSH private key not found: {$key}");
        }

        $prefix = $realDir.DIRECTORY_SEPARATOR;

        if (! str_starts_with($realKey, $prefix) && $realKey !== $realDir) {
            throw new RuntimeException('SSH private key must live inside the SSH key directory.');
        }

        if (! is_readable($realKey)) {
            throw new RuntimeException("SSH private key is not readable: {$realKey}");
        }

        return $realKey;
    }

    private static function resolveSshKeyDir(?string $keyDir): string
    {
        if (is_string($keyDir) && $keyDir !== '') {
            if (str_starts_with($keyDir, '~/')) {
                return self::homeDirectory().substr($keyDir, 1);
            }

            return rtrim($keyDir, DIRECTORY_SEPARATOR);
        }

        return self::homeDirectory().DIRECTORY_SEPARATOR.'.ssh';
    }

    private static function homeDirectory(): string
    {
        $home = $_SERVER['HOME'] ?? getenv('HOME') ?: null;

        if (! is_string($home) || $home === '') {
            throw new RuntimeException('Unable to determine home directory for SSH keys.');
        }

        return rtrim($home, DIRECTORY_SEPARATOR);
    }

    private static function isPortListening(int $port): bool
    {
        $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1.0);

        if ($socket !== false) {
            fclose($socket);

            return true;
        }

        return false;
    }

    /**
     * Spawn the SSH tunnel process and throw immediately if SSH reports an error.
     *
     * With -f the foreground process exits after a successful fork to background
     * (exit code 0) or exits with a non-zero code on any failure (bad key,
     * unreachable host, port-forwarding refused, etc.). We wait for the foreground
     * to exit, then read its stderr so the actual SSH error message is available.
     *
     * @param  array<string, mixed>  $tunnel
     */
    private static function spawnTunnel(array $tunnel, string $keyPath): void
    {
        $sshPort = (int) ($tunnel['ssh_port'] ?? 22);
        $localPort = (int) $tunnel['local_port'];
        $remoteHost = (string) $tunnel['remote_db_host'];
        $remotePort = (int) $tunnel['remote_db_port'];
        $sshUser = (string) $tunnel['ssh_user'];
        $sshHost = (string) $tunnel['ssh_host'];

        $args = [
            'ssh',
            '-f', '-N',
            '-L', "{$localPort}:{$remoteHost}:{$remotePort}",
            '-p', (string) $sshPort,
            '-i', $keyPath,
            '-o', 'IdentitiesOnly=yes',
            '-o', 'StrictHostKeyChecking=no',
            '-o', 'BatchMode=yes',
            '-o', 'ConnectTimeout=10',
            '-o', 'ExitOnForwardFailure=yes',
            '-o', 'ServerAliveInterval=30',
            '-o', 'ServerAliveCountMax=3',
            "{$sshUser}@{$sshHost}",
        ];

        $process = proc_open(
            $args,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        if ($process === false) {
            throw new RuntimeException('Failed to spawn SSH tunnel process.');
        }

        // stdin / stdout not needed.
        fclose($pipes[0]);
        fclose($pipes[1]);

        // Read stderr before proc_close() — proc_close destroys all pipe resources.
        stream_set_blocking($pipes[2], false);
        $stderr = trim((string) stream_get_contents($pipes[2]));
        fclose($pipes[2]);

        // Wait for the foreground SSH process to exit.
        // With -f it exits quickly: code 0 after forking, non-zero on failure.
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new RuntimeException($stderr !== '' ? $stderr : "SSH process exited with code {$exitCode}.");
        }
    }

    /**
     * Poll until the local port is accepting connections or we give up.
     */
    private static function waitForPort(int $port, int $maxAttempts = 10): void
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            if (self::isPortListening($port)) {
                return;
            }
            usleep(300_000); // 300 ms
        }

        throw new RuntimeException(
            "SSH tunnel did not bind to local port {$port} within the expected time.",
        );
    }
}
