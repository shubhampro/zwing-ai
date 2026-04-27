<?php

namespace App\Support\Database;

use InvalidArgumentException;

final class RemoteSelectQueryValidator
{
    /**
     * @throws InvalidArgumentException
     */
    public static function validate(string $sql): void
    {
        $trimmed = trim($sql);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Query cannot be empty.');
        }

        $withoutTrailingSemicolon = rtrim($trimmed, " \t\n\r\0\x0B;");
        if (str_contains($withoutTrailingSemicolon, ';')) {
            throw new InvalidArgumentException('Multiple SQL statements are not allowed.');
        }

        $normalized = WriteIntentSqlDetector::normalizeLeadingStatement($sql);
        if ($normalized === '') {
            throw new InvalidArgumentException('Query cannot be empty.');
        }

        if (! preg_match('/^(with|select)\b/iu', $normalized)) {
            throw new InvalidArgumentException('Only read-only SELECT queries (optionally starting with WITH) are allowed.');
        }

        if (WriteIntentSqlDetector::isWriteQuery($sql)) {
            throw new InvalidArgumentException('This query is not allowed on a remote connection.');
        }

        if (preg_match('/\binto\s+(outfile|dumpfile)\b/iu', $sql)) {
            throw new InvalidArgumentException('SELECT … INTO OUTFILE / DUMPFILE is not allowed.');
        }

        if (preg_match('/\bfor\s+update\b/iu', $sql)) {
            throw new InvalidArgumentException('FOR UPDATE is not allowed.');
        }
    }
}
