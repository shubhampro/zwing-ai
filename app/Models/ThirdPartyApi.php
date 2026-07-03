<?php

namespace App\Models;

use App\HttpMethod;
use Database\Factories\ThirdPartyApiFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThirdPartyApi extends Model
{
    /** @use HasFactory<ThirdPartyApiFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'path',
        'method',
        'params',
        'auth_header_name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'method' => HttpMethod::class,
            'params' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function organizationConnections(): HasMany
    {
        return $this->hasMany(OrganizationThirdPartyApi::class);
    }

    public function endpointUrl(string $baseUrl): string
    {
        return rtrim($baseUrl, '/').'/'.ltrim($this->path, '/');
    }
}
