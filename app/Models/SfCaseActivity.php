<?php

namespace App\Models;

use Database\Factories\SfCaseActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SfCaseActivity extends Model
{
    public const SOURCE_COMMENT = 'comment';

    public const SOURCE_FEED = 'feed';

    public const SOURCE_EMAIL = 'email';

    /** @use HasFactory<SfCaseActivityFactory> */
    use HasFactory;

    protected $fillable = [
        'sf_id',
        'sf_case_id',
        'source',
        'type',
        'subject',
        'body',
        'author_name',
        'is_incoming',
        'occurred_at',
        'synced_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_incoming' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_incoming' => 'boolean',
            'occurred_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(SfCase::class, 'sf_case_id');
    }
}
