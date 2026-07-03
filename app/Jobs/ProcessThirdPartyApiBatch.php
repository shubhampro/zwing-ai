<?php

namespace App\Jobs;

use App\Models\ThirdPartyApiBatch;
use App\Models\ThirdPartyApiBatchItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Throwable;

#[Timeout(120)]
class ProcessThirdPartyApiBatch implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $batchId,
    ) {}

    public function handle(): void
    {
        $batch = ThirdPartyApiBatch::query()->findOrFail($this->batchId);

        $batch->update(['status' => 'processing']);

        $delayMs = max(0, (int) config('third_party.request_delay_ms'));

        ThirdPartyApiBatchItem::query()
            ->where('third_party_api_batch_id', $batch->id)
            ->where('status', 'pending')
            ->orderBy('id')
            ->pluck('id')
            ->values()
            ->each(function (int $itemId, int $index) use ($delayMs): void {
                $job = ProcessThirdPartyApiBatchItemJob::dispatch($itemId);

                if ($delayMs > 0 && $index > 0) {
                    $job->delay(now()->addMilliseconds($index * $delayMs));
                }
            });

        if (! $batch->items()->where('status', 'pending')->exists()) {
            $batch->refreshCounts();
            $batch->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        ThirdPartyApiBatch::whereKey($this->batchId)->update(['status' => 'failed']);
    }
}
