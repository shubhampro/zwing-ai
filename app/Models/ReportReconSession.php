<?php

namespace App\Models;

use Database\Factories\ReportReconSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'name',
    'v_id',
    'organization_id',
    'date_from',
    'date_to',
    'invoice_row_count',
    'mop_row_count',
    'invoice_processed_rows',
    'mop_processed_rows',
    'invoice_skipped_rows',
    'mop_skipped_rows',
    'invoice_query_ms',
    'mop_query_ms',
    'status',
    'failure_reason',
    'reconciled_at',
])]
class ReportReconSession extends Model
{
    /** @use HasFactory<ReportReconSessionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
            'reconciled_at' => 'datetime',
            'invoice_query_ms' => 'integer',
            'mop_query_ms' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
