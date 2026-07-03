<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\ThirdPartyApi;
use App\Models\ThirdPartyApiBatch;
use App\Models\TransactionCheckerSession;

class DashboardOverviewService
{
    /**
     * @return array{
     *     organizations_count: int,
     *     third_party_apis_count: int,
     *     completed_batches_count: int,
     *     transaction_checker_runs_count: int
     * }
     */
    public function platformStatsForUser(int $userId): array
    {
        return [
            'organizations_count' => Organization::query()->count(),
            'third_party_apis_count' => ThirdPartyApi::query()->count(),
            'completed_batches_count' => ThirdPartyApiBatch::query()
                ->where('user_id', $userId)
                ->where('status', 'completed')
                ->count(),
            'transaction_checker_runs_count' => TransactionCheckerSession::query()
                ->where('user_id', $userId)
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestBatchSummaryForUser(int $userId): ?array
    {
        $batch = ThirdPartyApiBatch::query()
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->latest('completed_at')
            ->first([
                'id',
                'name',
                'row_count',
                'success_count',
                'failed_count',
                'skipped_count',
                'completed_at',
            ]);

        if ($batch === null) {
            return null;
        }

        $rowCount = (int) $batch->row_count;
        $successCount = (int) $batch->success_count;
        $failedCount = (int) $batch->failed_count;
        $skippedCount = (int) $batch->skipped_count;

        return [
            'id' => $batch->id,
            'name' => $batch->name,
            'row_count' => $rowCount,
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'skipped_count' => $skippedCount,
            'success_percent' => $rowCount > 0
                ? round(($successCount / $rowCount) * 100, 1)
                : 0.0,
            'completed_at' => $batch->completed_at?->toIso8601String(),
        ];
    }
}
