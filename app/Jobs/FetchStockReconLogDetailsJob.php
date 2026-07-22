<?php

namespace App\Jobs;

use App\Enums\ExternalQueryStatus;
use App\Models\ExternalQueryLog;
use App\Models\StockReconSession;
use App\Services\StockReconLogDetailService;
use App\Support\ExternalQueryQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class FetchStockReconLogDetailsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(
        public readonly int $externalQueryLogId,
        public readonly int $sessionId,
        public readonly string $siteCode,
        public readonly string $icode,
        public readonly string $batchNo,
        public readonly string $sprefcode,
    ) {
        $this->onQueue(ExternalQueryQueue::NAME);
    }

    public function handle(StockReconLogDetailService $logDetailService): void
    {
        $log = ExternalQueryLog::query()->findOrFail($this->externalQueryLogId);
        $session = StockReconSession::query()->findOrFail($this->sessionId);

        $log->markProcessing();

        try {
            $result = $logDetailService->forSku(
                session: $session,
                siteCode: $this->siteCode,
                icode: $this->icode,
                batchNo: $this->batchNo,
                sprefcode: $this->sprefcode,
            );

            $log->markCompleted(
                result: $result,
                zwingQueryMs: $result['zwing_query_ms'] ?? null,
                erpQueryMs: $result['erp_query_ms'] ?? null,
            );
        } catch (Throwable $exception) {
            $log->markFailed($exception);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception === null) {
            return;
        }

        $log = ExternalQueryLog::query()->find($this->externalQueryLogId);

        if ($log !== null && $log->status !== ExternalQueryStatus::Failed) {
            $log->markFailed($exception);
        }
    }
}
