<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    protected $fillable = ['name', 'ba_code', 'vendor_id', 'db_name'];

    protected function casts(): array
    {
        return [
            'db_name' => 'encrypted',
        ];
    }

    public function organizationConnections(): HasMany
    {
        return $this->hasMany(OrganizationThirdPartyApi::class);
    }

    public function databaseConnections(): HasMany
    {
        return $this->hasMany(OrganizationDatabaseConnection::class);
    }
}
