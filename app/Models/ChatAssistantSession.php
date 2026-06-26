<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatAssistantSession extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'messages',
        'context',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'messages' => 'array',
            'context' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
