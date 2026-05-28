<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'name',
    'v_id',
    'zwing_file_name',
    'erp_file_name',
    'zwing_log_file_name',
    'erp_log_file_name',
    'zwing_row_count',
    'erp_row_count',
    'zwing_log_row_count',
    'erp_log_row_count',
    'zwing_processed_rows',
    'erp_processed_rows',
    'zwing_skipped_rows',
    'erp_skipped_rows',
    'zwing_log_processed_rows',
    'erp_log_processed_rows',
    'zwing_log_skipped_rows',
    'erp_log_skipped_rows',
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

    public function zwingLogs(): HasMany
    {
        return $this->hasMany(StockReconZwingLog::class);
    }

    public function erpLogs(): HasMany
    {
        return $this->hasMany(StockReconErpLog::class);
    }
}
