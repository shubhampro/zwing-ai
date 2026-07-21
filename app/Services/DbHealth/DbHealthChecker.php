<?php

namespace App\Services\DbHealth;

use App\Models\DbHealthCheck;
use App\Services\SshTunnelManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class DbHealthChecker
{
    public const STATUS_OK = 'ok';

    public const STATUS_WARN = 'warn';

    public const STATUS_CRITICAL = 'critical';

    public const STATUS_DOWN = 'down';

    /**
     * @return array{
     *     overall_status: string,
     *     ran_at: string,
     *     results: list<array<string, mixed>>,
     *     cached: bool,
     *     locked: bool
     * }|null
     */
    public function snapshot(): ?array
    {
        /** @var array{overall_status: string, ran_at: string, results: list<array<string, mixed>>}|null $cached */
        $cached = Cache::get(config('server_health.cache_key'));

        if ($cached === null) {
            return null;
        }

        return [
            ...$cached,
            'cached' => true,
            'locked' => Cache::has(config('server_health.lock_key').':held'),
        ];
    }

    /**
     * Run checks when the lock can be acquired. Returns null when another run holds the lock.
     *
     * @return array{
     *     overall_status: string,
     *     ran_at: string,
     *     results: list<array<string, mixed>>,
     *     cached: bool,
     *     locked: bool
     * }|null
     */
    public function run(): ?array
    {
        $lock = Cache::lock(
            config('server_health.lock_key'),
            (int) config('server_health.lock_seconds'),
        );

        if (! $lock->get()) {
            return null;
        }

        Cache::put(
            config('server_health.lock_key').':held',
            true,
            (int) config('server_health.lock_seconds'),
        );

        try {
            $results = [];

            foreach ($this->targets() as $target) {
                $results[] = $this->checkTarget($target);
            }

            $overall = $this->overallStatus($results);
            $ranAt = now();

            $payload = [
                'overall_status' => $overall,
                'ran_at' => $ranAt->toIso8601String(),
                'results' => $results,
            ];

            Cache::put(
                config('server_health.cache_key'),
                $payload,
                (int) config('server_health.cache_ttl_seconds'),
            );

            DbHealthCheck::query()->create([
                'ran_at' => $ranAt,
                'overall_status' => $overall,
                'results' => $results,
            ]);

            return [
                ...$payload,
                'cached' => false,
                'locked' => false,
            ];
        } finally {
            Cache::forget(config('server_health.lock_key').':held');
            $lock->release();
        }
    }

    /**
     * @return list<string>
     */
    public function targets(): array
    {
        /** @var list<string> $targets */
        $targets = config('server_health.targets', []);

        return array_values(array_map(
            fn (string $target): string => $target === 'default'
                ? (string) config('database.default')
                : $target,
            $targets,
        ));
    }

    /**
     * @return array{
     *     key: string,
     *     status: string,
     *     latency_ms: int|null,
     *     meta: array<string, mixed>,
     *     error: string|null
     * }
     */
    public function checkTarget(string $connection): array
    {
        $started = hrtime(true);

        try {
            $driver = (string) config("database.connections.{$connection}.driver", '');

            if ($driver === '') {
                return $this->result($connection, self::STATUS_DOWN, null, [], "Unknown connection [{$connection}].");
            }

            if ($connection === 'mysql_ssh') {
                SshTunnelManager::ensureMysqlOpen();
            }

            $this->applyTimeouts($connection);

            if ($driver === 'mongodb') {
                return $this->checkMongo($connection, $started);
            }

            return $this->checkSql($connection, $started);
        } catch (Throwable $e) {
            return $this->result(
                $connection,
                self::STATUS_DOWN,
                $this->latencyMs($started),
                [],
                $e->getMessage(),
            );
        } finally {
            if ($connection !== config('database.default')) {
                DB::purge($connection);
            }
        }
    }

    /**
     * @param  array{threads_running?: int|null}  $meta
     */
    public function statusFromMeta(array $meta, ?string $error = null): string
    {
        if ($error !== null && $error !== '') {
            return self::STATUS_DOWN;
        }

        $threads = $meta['threads_running'] ?? null;

        if (! is_int($threads)) {
            return self::STATUS_OK;
        }

        $critical = (int) config('server_health.thresholds.threads_running_critical');
        $warn = (int) config('server_health.thresholds.threads_running_warn');

        if ($threads >= $critical) {
            return self::STATUS_CRITICAL;
        }

        if ($threads >= $warn) {
            return self::STATUS_WARN;
        }

        return self::STATUS_OK;
    }

    /**
     * @param  list<array{status: string}>  $results
     */
    public function overallStatus(array $results): string
    {
        $priority = [
            self::STATUS_DOWN => 4,
            self::STATUS_CRITICAL => 3,
            self::STATUS_WARN => 2,
            self::STATUS_OK => 1,
        ];

        $worst = self::STATUS_OK;

        foreach ($results as $result) {
            $status = $result['status'] ?? self::STATUS_DOWN;

            if (($priority[$status] ?? 0) > ($priority[$worst] ?? 0)) {
                $worst = $status;
            }
        }

        return $worst;
    }

    /**
     * @return array{
     *     key: string,
     *     status: string,
     *     latency_ms: int|null,
     *     meta: array<string, mixed>,
     *     error: string|null
     * }
     */
    private function checkSql(string $connection, int $started): array
    {
        DB::connection($connection)->select('select 1 as ok');

        $version = null;
        $meta = [];

        try {
            $versionRow = DB::connection($connection)->selectOne('select version() as version');
            $version = is_object($versionRow) ? (string) ($versionRow->version ?? '') : null;
        } catch (Throwable) {
            $version = null;
        }

        $driver = (string) config("database.connections.{$connection}.driver");

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $meta = $this->mysqlStatusMeta($connection);
        }

        $meta['version'] = $version;

        $latency = $this->latencyMs($started);
        $status = $this->statusFromMeta($meta);

        return $this->result($connection, $status, $latency, $meta, null);
    }

    /**
     * @return array<string, int|null>
     */
    private function mysqlStatusMeta(string $connection): array
    {
        $rows = DB::connection($connection)->select(
            "show global status where Variable_name in ('Threads_running', 'Threads_connected', 'Max_used_connections', 'Questions')"
        );

        $map = [];

        foreach ($rows as $row) {
            $name = strtolower((string) ($row->Variable_name ?? $row->variable_name ?? ''));
            $value = (int) ($row->Value ?? $row->value ?? 0);
            $map[$name] = $value;
        }

        return [
            'threads_running' => $map['threads_running'] ?? null,
            'threads_connected' => $map['threads_connected'] ?? null,
            'max_used_connections' => $map['max_used_connections'] ?? null,
            'questions' => $map['questions'] ?? null,
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     status: string,
     *     latency_ms: int|null,
     *     meta: array<string, mixed>,
     *     error: string|null
     * }
     */
    private function checkMongo(string $connection, int $started): array
    {
        $db = DB::connection($connection);
        $client = $db->getMongoClient();
        $client->selectDatabase('admin')->command(['ping' => 1]);

        $meta = [
            'version' => null,
            'threads_running' => null,
            'threads_connected' => null,
            'max_used_connections' => null,
            'questions' => null,
        ];

        try {
            $buildInfo = $client->selectDatabase('admin')->command(['buildInfo' => 1])->toArray();
            $meta['version'] = $buildInfo[0]->version ?? null;
        } catch (Throwable) {
            // ping alone is enough for connectivity
        }

        return $this->result(
            $connection,
            self::STATUS_OK,
            $this->latencyMs($started),
            $meta,
            null,
        );
    }

    private function applyTimeouts(string $connection): void
    {
        $connectTimeout = (int) config('server_health.connect_timeout');
        $driver = (string) config("database.connections.{$connection}.driver");

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            config([
                "database.connections.{$connection}.options" => [
                    ...(array) config("database.connections.{$connection}.options", []),
                    \PDO::ATTR_TIMEOUT => $connectTimeout,
                ],
            ]);
        }

        if ($driver === 'pgsql') {
            config([
                "database.connections.{$connection}.connect_timeout" => $connectTimeout,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{
     *     key: string,
     *     status: string,
     *     latency_ms: int|null,
     *     meta: array<string, mixed>,
     *     error: string|null
     * }
     */
    private function result(
        string $key,
        string $status,
        ?int $latencyMs,
        array $meta,
        ?string $error,
    ): array {
        return [
            'key' => $key,
            'status' => $status,
            'latency_ms' => $latencyMs,
            'meta' => $meta,
            'error' => $error,
        ];
    }

    private function latencyMs(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
