<?php

namespace App\Models;

use App\Enums\DatabaseConnectionType;
use Database\Factories\OrganizationDatabaseConnectionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationDatabaseConnection extends Model
{
    /** @use HasFactory<OrganizationDatabaseConnectionFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'type',
        'database_name',
        'username',
        'password',
        'host',
        'port',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => DatabaseConnectionType::class,
            'database_name' => 'encrypted',
            'password' => 'encrypted',
            'port' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @param  Builder<OrganizationDatabaseConnection>  $query
     * @return Builder<OrganizationDatabaseConnection>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<OrganizationDatabaseConnection>  $query
     * @return Builder<OrganizationDatabaseConnection>
     */
    public function scopeOfType(Builder $query, DatabaseConnectionType $type): Builder
    {
        return $query->where('type', $type);
    }
}
