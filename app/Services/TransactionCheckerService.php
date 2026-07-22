<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\TransactionCheckerSession;
use App\Services\TransactionChecker\GrnChecker;
use App\Services\TransactionChecker\GrtChecker;
use App\Services\TransactionChecker\SstChecker;
use App\Services\TransactionChecker\TransactionCheckerInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TransactionCheckerService
{
    /** @var array<string, string> */
    public const CONNECTIONS = [
        'mysql_ssh' => 'MySQL (SSH)',
    ];

    /** @var array<string, class-string<TransactionCheckerInterface>> */
    private const CHECKERS = [
        'grn' => GrnChecker::class,
        'grt' => GrtChecker::class,
        'sst' => SstChecker::class,
    ];

    /**
     * @return list<string>
     */
    public function databasesForOrganization(int $orgId): array
    {
        $org = Organization::query()->findOrFail($orgId);

        SshTunnelManager::ensureMysqlOpen();

        $baseConfig = Config::get('database.connections.mysql_ssh');
        $baseConfig['database'] = '';
        Config::set('database.connections.mysql_ssh_nodatabase', $baseConfig);

        $pattern = "_{$org->vendor_id}_";

        return collect(DB::connection('mysql_ssh_nodatabase')->select('SHOW DATABASES'))
            ->map(fn (object $row) => array_values((array) $row)[0])
            ->filter(fn (string $name) => str_contains($name, $pattern))
            ->values()
            ->all();
    }

    /**
     * @return array{summary: array<string, int>, rows: array<int, array<string, mixed>>}
     */
    public function check(
        string $connection,
        string $transactionType,
        int $orgId,
        string $database,
        ?int $userId = null,
    ): array {
        if (! isset(self::CONNECTIONS[$connection])) {
            throw new InvalidArgumentException('Invalid connection.');
        }

        if (! isset(self::CHECKERS[$transactionType])) {
            throw new InvalidArgumentException('Invalid transaction type.');
        }

        if ($connection === 'mysql_ssh') {
            SshTunnelManager::ensureMysqlOpen();
        }

        $config = Config::get("database.connections.{$connection}");
        $config['database'] = $database;
        Config::set("database.connections.{$connection}_dynamic", $config);

        $db = DB::connection("{$connection}_dynamic");

        /** @var TransactionCheckerInterface $checker */
        $checker = new (self::CHECKERS[$transactionType])();

        $results = $checker->run($db);

        if ($userId !== null) {
            TransactionCheckerSession::query()->create([
                'user_id' => $userId,
                'org_id' => $orgId,
                'connection' => $connection,
                'transaction_type' => $transactionType,
                'database' => $database,
                'summary' => $results['summary'],
            ]);
        }

        return $results;
    }
}
