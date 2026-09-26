<?php

namespace App\Jobs;

use App\Enums\SfMbrReportStatus;
use App\Models\SfMbrReport;
use App\Services\Salesforce\SfMbrAccountSegregator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SegregateSfMbrAccountsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(public readonly int $sfMbrReportId)
    {
        $this->afterCommit();
    }

    public function handle(SfMbrAccountSegregator $segregator): void
    {
        $report = SfMbrReport::query()->findOrFail($this->sfMbrReportId);

        $segregator->segregate($report);
    }

    public function failed(?Throwable $exception): void
    {
        SfMbrReport::query()->whereKey($this->sfMbrReportId)->update([
            'status' => SfMbrReportStatus::Failed,
            'failed_reason' => $exception?->getMessage(),
        ]);
    }
}
