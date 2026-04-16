<?php

namespace App\Services;

use App\Enums\DatabaseAccessMode;
use App\Enums\DatabaseDriver;
use App\Models\DatabaseConnection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Pdo\Mysql;

final class DatabaseConnectionRegistrar
{
    public static function register(): void
    {
        if (! Schema::hasTable('database_connections')) {
            return;
        }

        /** @var list<string> $previous */
        $previous = config('dynamic_database.registered_slugs', []);

        $records = DatabaseConnection::query()
            ->where('is_active', true)
            ->get();

        /** @var array<string, array<string, mixed>> $connections */
        $connections = config('database.connections');

        foreach (array_diff($previous, $records->pluck('slug')->all()) as $removedSlug) {
            DB::purge($removedSlug);
            unset($connections[$removedSlug]);
        }

        $readGuardSlugs = [];

        foreach ($records as $record) {
            $connections[$record->slug] = self::toLaravelConnection($record);

            if ($record->enforce_read_only_sql_guard
                && $record->access_mode === DatabaseAccessMode::Read
                && ($record->driver === DatabaseDriver::Mysql || $record->driver === DatabaseDriver::Pgsql)
            ) {
                $readGuardSlugs[] = $record->slug;
            }
        }

        Config::set('database.connections', $connections);

        foreach ($records->pluck('slug')->all() as $slug) {
            DB::purge($slug);
        }

        Config::set('dynamic_database.registered_slugs', $records->pluck('slug')->all());
        Config::set('dynamic_database.read_only_guarded_slugs', $readGuardSlugs);
    }

    /**
     * @return array<string, mixed>
     */
    public static function toLaravelConnection(DatabaseConnection $c): array
    {
        return match ($c->driver) {
            DatabaseDriver::Mysql => self::mysql($c),
            DatabaseDriver::Pgsql => self::pgsql($c),
            DatabaseDriver::Mongodb => self::mongodb($c),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function mysql(DatabaseConnection $c): array
    {
        $options = extension_loaded('pdo_mysql') ? array_filter([
            (PHP_VERSION_ID >= 80500 ? Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA) => $c->ssl_ca_path,
        ]) : [];

        if (is_array($c->extra_options) && isset($c->extra_options['pdo']) && is_array($c->extra_options['pdo'])) {
            $options = array_replace($options, $c->extra_options['pdo']);
        }

        $host = $c->host ?: '127.0.0.1';
        $port = $c->port ?: 3306;

        return [
            'driver' => 'mysql',
            'url' => $c->url,
            'host' => $host,
            'port' => $port,
            'database' => $c->database ?? '',
            'username' => $c->username ?? '',
            'password' => $c->password ?? '',
            'unix_socket' => $c->unix_socket ?? '',
            'charset' => $c->charset ?? 'utf8mb4',
            'collation' => $c->collation ?? 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => $options,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function pgsql(DatabaseConnection $c): array
    {
        $host = $c->host ?: '127.0.0.1';
        $port = $c->port ?: 5432;

        return [
            'driver' => 'pgsql',
            'url' => $c->url,
            'host' => $host,
            'port' => $port,
            'database' => $c->database ?? '',
            'username' => $c->username ?? '',
            'password' => $c->password ?? '',
            'charset' => $c->charset ?? 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => $c->search_path ?? 'public',
            'sslmode' => $c->sslmode ?? 'prefer',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function mongodb(DatabaseConnection $c): array
    {
        $defaultReadPreference = $c->access_mode === DatabaseAccessMode::Read
            ? 'secondaryPreferred'
            : 'primary';

        $options = [
            'database' => $c->mongodb_authentication_database ?? 'admin',
            'readPreference' => $c->mongodb_read_preference ?? $defaultReadPreference,
        ];

        if (is_array($c->extra_options) && isset($c->extra_options['mongo']) && is_array($c->extra_options['mongo'])) {
            $options = array_replace($options, $c->extra_options['mongo']);
        }

        $host = $c->host ?: '127.0.0.1';
        $port = $c->port ?: 27017;

        return [
            'driver' => 'mongodb',
            'dsn' => $c->mongodb_dsn,
            'host' => $host,
            'port' => $port,
            'database' => $c->database ?? '',
            'username' => $c->username,
            'password' => $c->password,
            'options' => $options,
            'name' => $c->slug,
        ];
    }
}
