<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'name',
    'v_id',
    'zwing_file_name',
    'erp_file_name',
    'zwing_row_count',
    'erp_row_count',
    'zwing_processed_rows',
    'erp_processed_rows',
    'zwing_skipped_rows',
    'erp_skipped_rows',
    'status',
    'reconciled_at',
])]
class StockReconSession extends Model
{
    protected function casts(): array
    {
        return [
            'reconciled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
