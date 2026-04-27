<?php

namespace App\Exceptions;

use RuntimeException;

final class NoActiveRemoteDatabaseContextException extends RuntimeException
{
    public static function missing(): self
    {
        return new self('No active remote database connection is selected.');
    }

    public static function invalidDatabase(): self
    {
        return new self('The selected database name is not valid for this connection.');
    }
}
