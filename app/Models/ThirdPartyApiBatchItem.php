<?php

namespace App\Models;

use Database\Factories\ThirdPartyApiBatchItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'third_party_api_batch_id',
    'payload',
    'status',
    'http_status',
    'response_body',
    'error_message',
    'processed_at',
])]
class ThirdPartyApiBatchItem extends Model
{
    /** @use HasFactory<ThirdPartyApiBatchItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ThirdPartyApiBatch::class, 'third_party_api_batch_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ThirdPartyApiBatchItemAttempt::class)->orderBy('attempt_number');
    }
}
