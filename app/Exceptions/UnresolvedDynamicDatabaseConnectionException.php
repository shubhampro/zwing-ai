<?php

namespace App\Exceptions;

use RuntimeException;

final class UnresolvedDynamicDatabaseConnectionException extends RuntimeException
{
    public static function missingRead(string $connectionGroup): self
    {
        return new self(
            "No active read connection found in database_connections for group [{$connectionGroup}].",
        );
    }

    public static function missingWrite(string $connectionGroup): self
    {
        return new self(
            "No active write connection found in database_connections for group [{$connectionGroup}].",
        );
    }

    public static function writesDisabled(string $connectionGroup): self
    {
        return new self(
            "Write connection for group [{$connectionGroup}] has writes_enabled=false. Enable it on the database_connections row before calling writeForGroup().",
        );
    }
}
