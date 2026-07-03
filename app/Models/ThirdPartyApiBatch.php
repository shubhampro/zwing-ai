<?php

namespace App\Models;

use Database\Factories\ThirdPartyApiBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'organization_third_party_api_id',
    'name',
    'file_name',
    'row_count',
    'processed_count',
    'success_count',
    'failed_count',
    'skipped_count',
    'defaults',
    'status',
    'completed_at',
])]
class ThirdPartyApiBatch extends Model
{
    /** @use HasFactory<ThirdPartyApiBatchFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'defaults' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organizationThirdPartyApi(): BelongsTo
    {
        return $this->belongsTo(OrganizationThirdPartyApi::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ThirdPartyApiBatchItem::class);
    }

    public function refreshCounts(): void
    {
        $this->update([
            'processed_count' => $this->items()->whereIn('status', ['success', 'failed', 'skipped'])->count(),
            'success_count' => $this->items()->where('status', 'success')->count(),
            'failed_count' => $this->items()->where('status', 'failed')->count(),
            'skipped_count' => $this->items()->where('status', 'skipped')->count(),
        ]);
    }
}
