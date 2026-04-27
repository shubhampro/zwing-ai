<?php

namespace App\Support\Database;

final class WriteIntentSqlDetector
{
    /**
     * Strip leading SQL comments/whitespace so the first keyword can be inspected.
     */
    public static function normalizeLeadingStatement(string $sql): string
    {
        return self::stripLeadingCommentsAndWhitespace($sql);
    }

    /**
     * Whether a SQL string appears to be a mutating statement.
     *
     * This is a best-effort guard for PDO-based connections; prefer database
     * users with read-only grants for real enforcement.
     */
    public static function isWriteQuery(string $sql): bool
    {
        $normalized = self::stripLeadingCommentsAndWhitespace($sql);

        if ($normalized === '') {
            return false;
        }

        return (bool) preg_match(
            '/^(insert|update|delete|replace|truncate|drop|create|alter|grant|revoke|merge|copy|call)\b/i',
            $normalized
        );
    }

    private static function stripLeadingCommentsAndWhitespace(string $sql): string
    {
        $sql = trim($sql);

        while ($sql !== '') {
            if (str_starts_with($sql, '/*')) {
                $end = strpos($sql, '*/');
                if ($end === false) {
                    return '';
                }
                $sql = trim(substr($sql, $end + 2));

                continue;
            }

            if (str_starts_with($sql, '--')) {
                $newline = strpos($sql, "\n");
                if ($newline === false) {
                    return '';
                }
                $sql = trim(substr($sql, $newline + 1));

                continue;
            }

            if (str_starts_with($sql, '#')) {
                $newline = strpos($sql, "\n");
                if ($newline === false) {
                    return '';
                }
                $sql = trim(substr($sql, $newline + 1));

                continue;
            }

            break;
        }

        return $sql;
    }
}
