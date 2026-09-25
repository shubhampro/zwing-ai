<?php

namespace App\Models;

use Database\Factories\SfCaseGroupHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SfCaseGroupHistory extends Model
{
    /** @use HasFactory<SfCaseGroupHistoryFactory> */
    use HasFactory;

    protected $fillable = [
        'sf_id',
        'sf_case_id',
        'field',
        'old_value',
        'new_value',
        'changed_by',
        'changed_at',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(SfCase::class, 'sf_case_id');
    }
}
