<?php

namespace App\Support\Database;

use App\Exceptions\WriteOperationOnReadOnlyRemoteConnectionException;
use Illuminate\Support\Facades\DB;

final class GuardsReadOnlySqlRemoteConnections
{
    public static function register(): void
    {
        if (! config('dynamic_database.read_only_guard_enabled')) {
            return;
        }

        /** @var list<string> $connections */
        $connections = config('dynamic_database.read_only_guarded_slugs', []);

        if ($connections === []) {
            return;
        }

        $readOnly = array_flip($connections);

        DB::listen(function ($query) use ($readOnly): void {
            if (! isset($readOnly[$query->connectionName])) {
                return;
            }

            $driver = DB::connection($query->connectionName)->getDriverName();

            if (! in_array($driver, ['mysql', 'pgsql'], true)) {
                return;
            }

            if (! WriteIntentSqlDetector::isWriteQuery($query->sql)) {
                return;
            }

            throw WriteOperationOnReadOnlyRemoteConnectionException::forConnection(
                $query->connectionName,
            );
        });
    }
}
