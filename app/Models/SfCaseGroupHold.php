<?php

namespace App\Models;

use Database\Factories\SfCaseGroupHoldFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SfCaseGroupHold extends Model
{
    /** @use HasFactory<SfCaseGroupHoldFactory> */
    use HasFactory;

    protected $fillable = [
        'sf_case_id',
        'group_name',
        'started_at',
        'ended_at',
        'held_minutes',
        'is_open',
        'computed_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_open' => false,
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'held_minutes' => 'integer',
            'is_open' => 'boolean',
            'computed_at' => 'datetime',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(SfCase::class, 'sf_case_id');
    }
}
