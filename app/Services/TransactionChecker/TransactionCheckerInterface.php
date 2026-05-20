<?php

namespace App\Services\TransactionChecker;

use Illuminate\Database\ConnectionInterface;

interface TransactionCheckerInterface
{
    /**
     * Run the check against the given database connection.
     *
     * @return array{
     *     summary: array{total: int, matched: int, mismatch: int, missing_stock: int},
     *     rows: array<int, array<string, mixed>>
     * }
     */
    public function run(ConnectionInterface $db): array;
}
