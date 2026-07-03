<?php

namespace App\Models;

use Database\Factories\OrganizationThirdPartyApiFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationThirdPartyApi extends Model
{
    /** @use HasFactory<OrganizationThirdPartyApiFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'third_party_api_id',
        'base_url',
        'auth_token',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'auth_token' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function thirdPartyApi(): BelongsTo
    {
        return $this->belongsTo(ThirdPartyApi::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ThirdPartyApiBatch::class);
    }

    public function isConfigured(): bool
    {
        return trim($this->base_url) !== '' && trim((string) $this->auth_token) !== '';
    }

    public function endpointUrl(): string
    {
        return $this->thirdPartyApi->endpointUrl($this->base_url);
    }
}
