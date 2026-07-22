<?php

namespace App\Models;

use App\Enums\ExternalQueryJobType;
use App\Enums\ExternalQueryStatus;
use Database\Factories\ExternalQueryLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

#[Fillable([
    'user_id',
    'stock_recon_session_id',
    'job_type',
    'status',
    'context',
    'result',
    'zwing_query_ms',
    'erp_query_ms',
    'failure_reason',
    'started_at',
    'finished_at',
])]
class ExternalQueryLog extends Model
{
    /** @use HasFactory<ExternalQueryLogFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'job_type' => ExternalQueryJobType::class,
            'status' => ExternalQueryStatus::class,
            'context' => 'array',
            'result' => 'array',
            'zwing_query_ms' => 'integer',
            'erp_query_ms' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stockReconSession(): BelongsTo
    {
        return $this->belongsTo(StockReconSession::class);
    }

    public function markProcessing(): void
    {
        $this->update([
            'status' => ExternalQueryStatus::Processing,
            'started_at' => now(),
            'failure_reason' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $result
     */
    public function markCompleted(?array $result = null, ?int $zwingQueryMs = null, ?int $erpQueryMs = null): void
    {
        $this->update([
            'status' => ExternalQueryStatus::Completed,
            'result' => $result,
            'zwing_query_ms' => $zwingQueryMs,
            'erp_query_ms' => $erpQueryMs,
            'failure_reason' => null,
            'finished_at' => now(),
            'started_at' => $this->started_at ?? now(),
        ]);
    }

    public function markFailed(Throwable $exception): void
    {
        $message = $exception->getMessage();

        if ($exception instanceof ValidationException) {
            $message = collect($exception->errors())->flatten()->first() ?: $message;
        }

        $message = preg_replace('/Database:\s*[^,]*/i', 'Database: [hidden]', (string) $message) ?? (string) $message;

        $this->update([
            'status' => ExternalQueryStatus::Failed,
            'failure_reason' => Str::limit($message, 2000),
            'finished_at' => now(),
            'started_at' => $this->started_at ?? now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPollPayload(): array
    {
        return [
            'id' => $this->id,
            'job_type' => $this->job_type->value,
            'status' => $this->status->value,
            'context' => $this->context,
            'result' => $this->result,
            'zwing_query_ms' => $this->zwing_query_ms,
            'erp_query_ms' => $this->erp_query_ms,
            'failure_reason' => $this->failure_reason,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
        ];
    }
}
