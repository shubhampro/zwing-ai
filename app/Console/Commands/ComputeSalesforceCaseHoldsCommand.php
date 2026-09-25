<?php

namespace App\Console\Commands;

use App\Models\SfCase;
use App\Services\Salesforce\SalesforceCaseHoldComputer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\progress;

#[Signature('sf:compute-case-holds {--dry-run : Compute holds without writing}')]
#[Description('Derive group hold stints from local CaseHistory Group__c hops')]
class ComputeSalesforceCaseHoldsCommand extends Command
{
    public function handle(SalesforceCaseHoldComputer $computer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $caseIds = SfCase::query()->orderBy('id')->pluck('id');

        if ($caseIds->isEmpty()) {
            $this->warn('No local cases. Run php artisan sf:pull-cases first.');

            return self::SUCCESS;
        }

        if (! $dryRun) {
            $computer->wipe();
        }

        $count = 0;

        progress(
            label: $dryRun ? 'Computing case group holds' : 'Saving case group holds',
            steps: $caseIds->chunk(SalesforceCaseHoldComputer::CASE_CHUNK_SIZE),
            callback: function ($chunk) use ($computer, $dryRun, &$count): void {
                $rows = $computer->computeChunk($chunk);
                $count += count($rows);

                if ($dryRun) {
                    return;
                }

                foreach (array_chunk($rows, SalesforceCaseHoldComputer::PERSIST_CHUNK_SIZE) as $persistChunk) {
                    $computer->persistChunk($persistChunk);
                }

                $computer->persistResolutions($chunk, $rows);
            },
            hint: $caseIds->count().' local cases',
        );

        if ($dryRun) {
            $this->info("Dry run: {$count} group holds (not saved).");

            return self::SUCCESS;
        }

        $this->info("Computed {$count} group holds.");

        return self::SUCCESS;
    }
}
