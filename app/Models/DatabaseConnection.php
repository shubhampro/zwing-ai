<?php

namespace App\Models;

use App\Enums\DatabaseAccessMode;
use App\Enums\DatabaseDriver;
use Database\Factories\DatabaseConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
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
    'password',
    'unix_socket',
    'charset',
    'collation',
    'search_path',
    'sslmode',
    'ssl_ca_path',
    'mongodb_dsn',
    'mongodb_authentication_database',
    'mongodb_read_preference',
    'extra_options',
])]
class DatabaseConnection extends Model
{
    /** @use HasFactory<DatabaseConnectionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'driver' => DatabaseDriver::class,
            'access_mode' => DatabaseAccessMode::class,
            'is_active' => 'boolean',
            'writes_enabled' => 'boolean',
            'enforce_read_only_sql_guard' => 'boolean',
            'password' => 'encrypted',
            'extra_options' => 'array',
        ];
    }

    /**
     * @return HasMany<DatabaseConnectionLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(DatabaseConnectionLog::class);
    }
}
