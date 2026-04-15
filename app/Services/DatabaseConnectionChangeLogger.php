<?php

namespace App\Services;

use App\Models\DatabaseConnection;
use App\Models\DatabaseConnectionLog;
use App\Models\User;
use Illuminate\Http\Request;

final class DatabaseConnectionChangeLogger
{
    /**
     * @var list<string>
     */
    private const SNAPSHOT_KEYS = [
        'slug',
        'connection_group',
        'driver',
        'access_mode',
        'label',
        'is_active',
        'writes_enabled',
        'enforce_read_only_sql_guard',
        'url',
        'host',
        'port',
        'database',
        'username',
        'unix_socket',
        'charset',
        'collation',
        'search_path',
        'sslmode',
        'ssl_ca_path',
        'mongodb_dsn',
        'mongodb_authentication_database',
        'mongodb_read_preference',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function snapshot(DatabaseConnection $connection): array
    {
        $data = $connection->only(self::SNAPSHOT_KEYS);

        $password = $connection->getRawOriginal('password');
        if ($password !== null && $password !== '') {
            $data['password'] = '[REDACTED]';
        }

        return $data;
    }

    public static function logCreated(User $user, Request $request, DatabaseConnection $connection): void
    {
        DatabaseConnectionLog::query()->create([
            'database_connection_id' => $connection->id,
            'user_id' => $user->id,
            'action' => 'created',
            'connection_slug' => $connection->slug,
            'connection_group' => $connection->connection_group,
            'before' => null,
            'after' => self::snapshot($connection),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $before
     */
    public static function logUpdated(User $user, Request $request, DatabaseConnection $connection, array $before): void
    {
        DatabaseConnectionLog::query()->create([
            'database_connection_id' => $connection->id,
            'user_id' => $user->id,
            'action' => 'updated',
            'connection_slug' => $connection->slug,
            'connection_group' => $connection->connection_group,
            'before' => $before,
            'after' => self::snapshot($connection),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
