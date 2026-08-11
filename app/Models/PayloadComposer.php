<?php

namespace App\Models;

use Database\Factories\PayloadComposerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayloadComposer extends Model
{
    /** @use HasFactory<PayloadComposerFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'scalars',
    ];

    protected function casts(): array
    {
        return [
            'scalars' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(PayloadComposerSlot::class)->orderBy('sort_order')->orderBy('id');
    }
}
