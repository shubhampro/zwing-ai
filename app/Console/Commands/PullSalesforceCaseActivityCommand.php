<?php

namespace App\Console\Commands;

use App\Exceptions\SalesforceQueryException;
use App\Models\SfCase;
use App\Services\Salesforce\SalesforceCaseActivityPuller;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\progress;

#[Signature('sf:pull-case-activity {--dry-run : Query Salesforce without writing}')]
#[Description('Pull CaseComment, CaseFeed, and EmailMessage for local Zwing / Zwing-Tech cases')]
class PullSalesforceCaseActivityCommand extends Command
{
    public function handle(SalesforceCaseActivityPuller $puller): int
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
                label: $dryRun ? 'Querying case activity' : 'Pulling case activity',
                steps: $caseIdsBySfId->chunk(SalesforceCaseActivityPuller::CASE_ID_CHUNK_SIZE),
                callback: function ($chunk) use ($puller, $dryRun, &$count): void {
                    $rows = $puller->fetchChunk($chunk);
                    $count += count($rows);

                    if ($dryRun || $rows === []) {
                        return;
                    }

                    foreach (array_chunk($rows, SalesforceCaseActivityPuller::PERSIST_CHUNK_SIZE) as $persistChunk) {
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
            $this->info("Dry run: {$count} activity rows (not saved).");

            return self::SUCCESS;
        }

        $this->info("Pulled {$count} activity rows.");

        return self::SUCCESS;
    }
}
