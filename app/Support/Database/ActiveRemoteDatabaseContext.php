<?php

namespace App\Support\Database;

use App\Enums\DatabaseDriver;
use App\Exceptions\NoActiveRemoteDatabaseContextException;
use App\Models\DatabaseConnection;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ActiveRemoteDatabaseContext
{
    private const string SESSION_KEY = 'remote_database_context';

    public static function store(string $connectionSlug, ?string $databaseName): void
    {
        session([
            self::SESSION_KEY => [
                'slug' => $connectionSlug,
                'database' => $databaseName,
            ],
        ]);
    }

    public static function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public static function hasSelection(): bool
    {
        $slug = session(self::SESSION_KEY.'.slug');

        return is_string($slug) && $slug !== '';
    }

    /**
     * @return array{connection_slug: string, database: string|null, connection_label: string, driver: string}|null
     */
    public static function forInertiaShared(): ?array
    {
        $slug = session(self::SESSION_KEY.'.slug');
        if (! is_string($slug) || $slug === '') {
            return null;
        }

        $model = DatabaseConnection::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if ($model === null) {
            self::clear();

            return null;
        }

        return [
            'connection_slug' => $model->slug,
            'database' => session(self::SESSION_KEY.'.database'),
            'connection_label' => $model->label !== null && $model->label !== '' ? $model->label : $model->slug,
            'driver' => $model->driver->value,
        ];
    }

    /**
     * @throws NoActiveRemoteDatabaseContextException
     */
    public static function connection(): Connection
    {
        $slug = session(self::SESSION_KEY.'.slug');
        if (! is_string($slug) || $slug === '') {
            throw NoActiveRemoteDatabaseContextException::missing();
        }

        $definition = DatabaseConnection::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if ($definition === null) {
            self::clear();
            throw NoActiveRemoteDatabaseContextException::missing();
        }

        Gate::authorize('view', $definition);

        $connection = DB::connection($definition->slug);

        $databaseName = session(self::SESSION_KEY.'.database');
        if (is_string($databaseName) && $databaseName !== ''
            && $definition->driver === DatabaseDriver::Mysql) {
            if (! MysqlDatabaseIdentifier::isValid($databaseName)) {
                throw NoActiveRemoteDatabaseContextException::invalidDatabase();
            }

            $connection->statement('USE '.MysqlDatabaseIdentifier::quoteForUse($databaseName));
        }

        return $connection;
    }
}
