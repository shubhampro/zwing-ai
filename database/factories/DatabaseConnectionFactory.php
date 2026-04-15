<?php

namespace Database\Factories;

use App\Enums\DatabaseAccessMode;
use App\Enums\DatabaseDriver;
use App\Models\DatabaseConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DatabaseConnection>
 */
class DatabaseConnectionFactory extends Factory
{
    protected $model = DatabaseConnection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $group = fake()->unique()->slug(2);

        return [
            'slug' => $group.'_read',
            'connection_group' => $group,
            'driver' => DatabaseDriver::Mysql,
            'access_mode' => DatabaseAccessMode::Read,
            'label' => null,
            'is_active' => true,
            'writes_enabled' => false,
            'enforce_read_only_sql_guard' => true,
            'url' => null,
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'laravel',
            'username' => 'readonly',
            'password' => 'secret',
            'unix_socket' => null,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'search_path' => null,
            'sslmode' => null,
            'ssl_ca_path' => null,
            'mongodb_dsn' => null,
            'mongodb_authentication_database' => null,
            'mongodb_read_preference' => null,
            'ssh_tunnel' => null,
            'extra_options' => null,
        ];
    }

    public function write(): static
    {
        return $this->state(function (array $attributes) {
            $group = $attributes['connection_group'];

            return [
                'slug' => $group.'_write',
                'access_mode' => DatabaseAccessMode::Write,
                'writes_enabled' => false,
                'enforce_read_only_sql_guard' => false,
                'username' => 'writer',
            ];
        });
    }

    public function writesPermitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'writes_enabled' => true,
        ]);
    }
}
