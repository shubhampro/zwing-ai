<?php

namespace App\Jobs;

use App\Models\ThirdPartyApiBatch;
use App\Services\ThirdParty\ThirdPartyApiPayloadBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;
use Throwable;

class ParseThirdPartyApiBatchCsv implements ShouldQueue
{
    use Queueable;

    private const CHUNK_SIZE = 500;

    public function __construct(
        public readonly int $batchId,
        public readonly string $csvPath,
    ) {}

    public function handle(): void
    {
        $batch = ThirdPartyApiBatch::query()
            ->with('organizationThirdPartyApi.thirdPartyApi')
            ->findOrFail($this->batchId);

        $batch->update(['status' => 'processing']);

        $api = $batch->organizationThirdPartyApi->thirdPartyApi;
        $builder = new ThirdPartyApiPayloadBuilder($api);
        $defaults = $batch->defaults ?? [];

        $csv = Reader::createFromPath($this->csvPath, 'r');
        $csv->setHeaderOffset(0);

        $normalizedHeaders = array_map(
            fn (string $header) => strtolower(trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $header) ?? $header)),
            $csv->getHeader(),
        );

        $now = now()->toDateTimeString();
        $chunk = [];
        $skipped = 0;
        $rowCount = 0;

        foreach ($csv->getRecords($normalizedHeaders) as $record) {
            $rowCount++;

            $payload = $builder->fromCsvRow($record, $defaults);

            if ($payload === null) {
                $skipped++;

                continue;
            }

            $chunk[] = [
                'third_party_api_batch_id' => $batch->id,
                'payload' => json_encode($payload),
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($chunk) >= self::CHUNK_SIZE) {
                DB::table('third_party_api_batch_items')->insert($chunk);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            DB::table('third_party_api_batch_items')->insert($chunk);
        }

        $batch->update([
            'row_count' => $rowCount,
            'skipped_count' => $skipped,
        ]);

        ProcessThirdPartyApiBatch::dispatch($batch->id);
    }

    public function failed(Throwable $exception): void
    {
        ThirdPartyApiBatch::whereKey($this->batchId)->update(['status' => 'failed']);
    }
}
