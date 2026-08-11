<?php

namespace App\Models;

use App\Enums\PayloadComposerSlotShape;
use Database\Factories\PayloadComposerSlotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayloadComposerSlot extends Model
{
    /** @use HasFactory<PayloadComposerSlotFactory> */
    use HasFactory;

    protected $fillable = [
        'payload_composer_id',
        'key',
        'saved_sql_query_id',
        'shape',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'shape' => PayloadComposerSlotShape::class,
            'sort_order' => 'integer',
        ];
    }

    public function composer(): BelongsTo
    {
        return $this->belongsTo(PayloadComposer::class, 'payload_composer_id');
    }

    public function savedSqlQuery(): BelongsTo
    {
        return $this->belongsTo(SavedSqlQuery::class);
    }
}
