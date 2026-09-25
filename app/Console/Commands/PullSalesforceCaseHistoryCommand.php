<?php

namespace App\Console\Commands;

use App\Exceptions\SalesforceQueryException;
use App\Models\SfCase;
use App\Services\Salesforce\SalesforceCaseHistoryPuller;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\progress;

#[Signature('sf:pull-case-history {--dry-run : Query Salesforce without writing}')]
#[Description('Pull Group/Owner CaseHistory for local Zwing / Zwing-Tech cases')]
class PullSalesforceCaseHistoryCommand extends Command
{
    public function handle(SalesforceCaseHistoryPuller $puller): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $caseIdsBySfId = SfCase::query()->pluck('id', 'sf_id');

        if ($caseIdsBySfId->isEmpty()) {
            $this->warn('No local cases. Run php artisan sf:pull-cases first.');

            return self::SUCCESS;
        }

        $count = 0;

        try {
            progress(
                label: $dryRun ? 'Querying case group history' : 'Pulling case group history',
                steps: $caseIdsBySfId->chunk(SalesforceCaseHistoryPuller::CASE_ID_CHUNK_SIZE),
                callback: function ($chunk) use ($puller, $dryRun, &$count): void {
                    $rows = $puller->fetchChunk($chunk);
                    $count += count($rows);

                    if ($dryRun || $rows === []) {
                        return;
                    }

                    foreach (array_chunk($rows, SalesforceCaseHistoryPuller::PERSIST_CHUNK_SIZE) as $persistChunk) {
                        $puller->persistChunk($persistChunk);
                    }
                },
                hint: $caseIdsBySfId->count().' local cases',
            );
        } catch (SalesforceQueryException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info("Dry run: {$count} CaseHistory rows (not saved).");

            return self::SUCCESS;
        }

        $this->info("Pulled {$count} CaseHistory rows.");

        return self::SUCCESS;
    }
}
