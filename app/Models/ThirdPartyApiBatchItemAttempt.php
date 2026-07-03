<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThirdPartyApiBatchItemAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'third_party_api_batch_item_id',
        'attempt_number',
        'request_method',
        'request_url',
        'request_headers',
        'request_body',
        'http_status',
        'response_body',
        'error_message',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'request_headers' => 'array',
            'request_body' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ThirdPartyApiBatchItem::class, 'third_party_api_batch_item_id');
    }
}
