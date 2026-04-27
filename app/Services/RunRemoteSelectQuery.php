<?php

namespace App\Services;

use App\Exceptions\NoActiveRemoteDatabaseContextException;
use App\Support\Database\ActiveRemoteDatabaseContext;
use App\Support\Database\RemoteSelectQueryValidator;
use InvalidArgumentException;

class RunRemoteSelectQuery
{
    public const int MAX_ROWS = 1000;

    /**
     * Run a validated SELECT (or WITH … SELECT) against the active remote SQL context.
     *
     * @param  array<string, mixed>  $bindings  Named PDO bindings (keys without leading colon).
     * @return array{columns: list<string>, rows: list<array<string, mixed>>, row_count: int, truncated: bool}
     *
     * @throws InvalidArgumentException
     * @throws NoActiveRemoteDatabaseContextException
     */
    public function __invoke(string $sql, array $bindings = []): array
    {
        $sql = trim($sql);
        RemoteSelectQueryValidator::validate($sql);

        $connection = ActiveRemoteDatabaseContext::connection();
        $driver = $connection->getDriverName();
        if (! in_array($driver, ['mysql', 'pgsql'], true)) {
            throw new InvalidArgumentException('The query runner only supports MySQL and PostgreSQL connections.');
        }

        $innerSql = rtrim($sql, " \t\n\r\0\x0B;");
        $wrapped = 'SELECT * FROM ('.$innerSql.') AS __query_runner_sub LIMIT '.(self::MAX_ROWS + 1);

        $rows = $connection->select($wrapped, $bindings);

        $truncated = count($rows) > self::MAX_ROWS;
        if ($truncated) {
            $rows = array_slice($rows, 0, self::MAX_ROWS);
        }

        $columns = [];
        if ($rows !== []) {
            /** @var object $first */
            $first = $rows[0];
            $columns = array_keys(get_object_vars($first));
        }

        /** @var list<array<string, mixed>> $asArrays */
        $asArrays = array_map(fn (object $row): array => (array) $row, $rows);

        return [
            'columns' => $columns,
            'rows' => $asArrays,
            'row_count' => count($asArrays),
            'truncated' => $truncated,
        ];
    }
}
