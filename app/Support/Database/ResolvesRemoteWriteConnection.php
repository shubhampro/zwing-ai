<?php

namespace App\Support\Database;

use App\Enums\DatabaseAccessMode;
use App\Exceptions\UnresolvedDynamicDatabaseConnectionException;
use App\Models\DatabaseConnection;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

final class ResolvesRemoteWriteConnection
{
    public static function readForGroup(string $connectionGroup): Connection
    {
        $definition = DatabaseConnection::query()
            ->where('connection_group', $connectionGroup)
            ->where('access_mode', DatabaseAccessMode::Read)
            ->where('is_active', true)
            ->first();

        if ($definition === null) {
            throw UnresolvedDynamicDatabaseConnectionException::missingRead($connectionGroup);
        }

        return DB::connection($definition->slug);
    }

    public static function writeForGroup(string $connectionGroup): Connection
    {
        $definition = DatabaseConnection::query()
            ->where('connection_group', $connectionGroup)
            ->where('access_mode', DatabaseAccessMode::Write)
            ->where('is_active', true)
            ->first();

        if ($definition === null) {
            throw UnresolvedDynamicDatabaseConnectionException::missingWrite($connectionGroup);
        }

        if (! $definition->writes_enabled) {
            throw UnresolvedDynamicDatabaseConnectionException::writesDisabled($connectionGroup);
        }

        return DB::connection($definition->slug);
    }
}
