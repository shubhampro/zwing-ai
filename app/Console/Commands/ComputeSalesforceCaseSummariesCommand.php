<?php

namespace App\Console\Commands;

use App\Models\SfCase;
use App\Services\Salesforce\SalesforceCaseSummaryComputer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\progress;

#[Signature('sf:compute-case-summaries {--dry-run : Compute summaries without writing}')]
#[Description('Derive rule-based activity summaries for local Zwing / Zwing-Tech cases')]
class ComputeSalesforceCaseSummariesCommand extends Command
{
    public function handle(SalesforceCaseSummaryComputer $computer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $caseIds = SfCase::query()->orderBy('id')->pluck('id');

        if ($caseIds->isEmpty()) {
            $this->warn('No local cases. Run php artisan sf:pull-cases first.');

            return self::SUCCESS;
        }

        $count = 0;

        progress(
            label: $dryRun ? 'Computing case summaries' : 'Saving case summaries',
            steps: $caseIds->chunk(SalesforceCaseSummaryComputer::CASE_CHUNK_SIZE),
            callback: function ($chunk) use ($computer, $dryRun, &$count): void {
                $rows = $computer->computeChunk($chunk);
                $count += count($rows);

                if ($dryRun) {
                    return;
                }

                $computer->persistChunk($rows);
            },
            hint: $caseIds->count().' local cases',
        );

        if ($dryRun) {
            $this->info("Dry run: {$count} case summaries (not saved).");

            return self::SUCCESS;
        }

        $this->info("Computed {$count} case summaries.");

        return self::SUCCESS;
    }
}
