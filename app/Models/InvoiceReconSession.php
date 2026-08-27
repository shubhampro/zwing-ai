<?php

namespace App\Models;

use Database\Factories\InvoiceReconSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'name',
    'v_id',
    'source',
    'organization_id',
    'pgsql_connection_id',
    'date_from',
    'date_to',
    'zwing_file_name',
    'erp_file_name',
    'zwing_row_count',
    'erp_row_count',
    'zwing_processed_rows',
    'erp_processed_rows',
    'zwing_skipped_rows',
    'erp_skipped_rows',
    'zwing_query_ms',
    'erp_query_ms',
    'status',
    'failure_reason',
    'reconciled_at',
])]
class InvoiceReconSession extends Model
{
    /** @use HasFactory<InvoiceReconSessionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
            'reconciled_at' => 'datetime',
            'zwing_query_ms' => 'integer',
            'erp_query_ms' => 'integer',
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

    public function pgsqlConnection(): BelongsTo
    {
        return $this->belongsTo(OrganizationDatabaseConnection::class, 'pgsql_connection_id');
    }
}
