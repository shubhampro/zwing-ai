<?php

namespace App\Support\Database;

use InvalidArgumentException;

final class MysqlDatabaseIdentifier
{
    private const int MAX_LENGTH = 64;

    /**
     * @return non-empty-string
     */
    public static function quoteForUse(string $name): string
    {
        if (! self::isValid($name)) {
            throw new InvalidArgumentException('Invalid MySQL database identifier.');
        }

        return '`'.str_replace('`', '``', $name).'`';
    }

    public static function isValid(string $name): bool
    {
        if ($name === '') {
            return false;
        }

        if (strlen($name) > self::MAX_LENGTH) {
            return false;
        }

        return preg_match('/^[a-zA-Z0-9_-]+$/', $name) === 1;
    }
}
