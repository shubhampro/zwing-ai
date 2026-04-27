<?php

namespace App\Support\Database;

use Illuminate\Database\QueryException;
use PDOException;
use Throwable;

final class RemoteQueryUserMessage
{
    /**
     * Human-readable, user-facing text from a failed remote query (no host, no bound values).
     */
    public static function fromQueryException(QueryException $exception): string
    {
        $info = $exception->errorInfo;
        if (is_array($info)) {
            $driverMessage = $info[2] ?? null;
            if (is_string($driverMessage) && $driverMessage !== '') {
                return self::formatWithCodes($driverMessage, $info[0] ?? null, $info[1] ?? null);
            }
        }

        $previous = $exception->getPrevious();
        if ($previous instanceof PDOException) {
            $pdoInfo = $previous->errorInfo ?? null;
            if (is_array($pdoInfo)) {
                $driverMessage = $pdoInfo[2] ?? null;
                if (is_string($driverMessage) && $driverMessage !== '') {
                    return self::formatWithCodes($driverMessage, $pdoInfo[0] ?? null, $pdoInfo[1] ?? null);
                }
            }
        }

        if ($previous instanceof Throwable) {
            $raw = $previous->getMessage();
            if ($raw !== '' && ! str_contains($raw, 'SQL:')) {
                return $raw;
            }
        }

        return 'The database could not run this query. Check syntax, tables, and parameter names.';
    }

    private static function formatWithCodes(string $driverMessage, mixed $sqlState, mixed $driverCode): string
    {
        $driverMessage = trim($driverMessage);
        $suffixParts = [];
        if (is_string($sqlState) && $sqlState !== '') {
            $suffixParts[] = $sqlState;
        }
        if ($driverCode !== null && $driverCode !== '') {
            $suffixParts[] = (string) $driverCode;
        }

        if ($suffixParts === []) {
            return $driverMessage;
        }

        return $driverMessage.' ['.implode(' / ', $suffixParts).']';
    }
}
