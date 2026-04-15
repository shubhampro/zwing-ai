<?php

namespace App\Exceptions;

use RuntimeException;

final class WriteOperationOnReadOnlyRemoteConnectionException extends RuntimeException
{
    public static function forConnection(string $connectionName): self
    {
        return new self(
            "Write SQL blocked on connection [{$connectionName}]. This connection is registered as read in database_connections (or has the read-only SQL guard enabled). Use a write row (access_mode=write) and ResolvesRemoteWriteConnection::writeForGroup().",
        );
    }
}
