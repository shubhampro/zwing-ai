<?php

namespace App\Services;

use RuntimeException;

final class SshTunnelManager
{
    /**
     * Skip actually opening tunnels (set true in test environments).
     */
    public static bool $fake = false;

    /**
     * Ensure an SSH tunnel is open for the given tunnel config.
     *
     * Checks whether the local_port is already accepting connections before
     * attempting to spawn a new ssh process. The private key is written to a
     * temporary file (mode 0600) and deleted immediately after the process starts.
     *
     * @param  array{
     *   ssh_host: string,
     *   ssh_port?: int|string|null,
     *   ssh_user: string,
     *   ssh_private_key: string,
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

        $keyPath = self::writeKeyFile($tunnel['ssh_private_key']);

        try {
            self::spawnTunnel($tunnel, $keyPath);
            self::waitForPort($localPort);
        } finally {
            @unlink($keyPath);
        }
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

    private static function writeKeyFile(string $privateKey): string
    {
        $path = sys_get_temp_dir().'/ssh_key_'.bin2hex(random_bytes(8));
        file_put_contents($path, $privateKey);
        chmod($path, 0600);

        return $path;
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
            '-o', 'StrictHostKeyChecking=no',
            '-o', 'BatchMode=yes',
            '-o', 'ConnectTimeout=10',
            '-o', 'ExitOnForwardFailure=yes',
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
