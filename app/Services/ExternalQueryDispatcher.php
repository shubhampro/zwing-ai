<?php

namespace App\Services;

use App\Enums\ExternalQueryJobType;
use App\Enums\ExternalQueryStatus;
use App\Jobs\RunExternalQueryJob;
use App\Models\ExternalQueryLog;
use App\Models\User;
use Throwable;

class ExternalQueryDispatcher
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function dispatch(
        ExternalQueryJobType $jobType,
        ?User $user = null,
        array $context = [],
        ?int $stockReconSessionId = null,
    ): ExternalQueryLog {
        $log = ExternalQueryLog::query()->create([
            'user_id' => $user?->id,
            'stock_recon_session_id' => $stockReconSessionId,
            'job_type' => $jobType,
            'status' => ExternalQueryStatus::Pending,
            'context' => $context,
        ]);

        try {
            RunExternalQueryJob::dispatch($log->id);
        } catch (Throwable) {
            // Sync queue may rethrow after markFailed; poll payload carries status.
        }

        return $log->fresh() ?? $log;
    }
}
