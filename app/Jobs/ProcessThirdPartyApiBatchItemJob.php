<?php

namespace App\Jobs;

use App\Models\ThirdPartyApiBatch;
use App\Models\ThirdPartyApiBatchItem;
use App\Services\ThirdParty\ProcessThirdPartyApiBatchItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Throwable;

#[Timeout(120)]
class ProcessThirdPartyApiBatchItemJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $itemId,
    ) {}

    public function handle(ProcessThirdPartyApiBatchItem $processor): void
    {
        $item = ThirdPartyApiBatchItem::query()
            ->with(['batch.organizationThirdPartyApi.thirdPartyApi'])
            ->findOrFail($this->itemId);

        if ($item->status !== 'pending') {
            return;
        }

        $batch = $item->batch;
        $processor->process($item, $batch->organizationThirdPartyApi);

        $batch->refreshCounts();
        $this->markBatchCompletedIfFinished($batch);
    }

    public function failed(?Throwable $exception): void
    {
        $item = ThirdPartyApiBatchItem::query()->find($this->itemId);

        if ($item === null) {
            return;
        }

        $batch = $item->batch;
        $batch->refreshCounts();
        $this->markBatchCompletedIfFinished($batch);
    }

    private function markBatchCompletedIfFinished(ThirdPartyApiBatch $batch): void
    {
        $hasPending = $batch->items()->where('status', 'pending')->exists();

        if ($hasPending) {
            return;
        }

        $batch->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }
}
