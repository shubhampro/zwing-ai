<?php

namespace App\Console\Commands;

use App\Exceptions\SalesforceQueryException;
use App\Services\Salesforce\SalesforceCasePuller;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\progress;
use function Laravel\Prompts\spin;

#[Signature('sf:pull-cases {--dry-run : Query Salesforce without writing}')]
#[Description('Pull Salesforce case headers (Product Zwing or Group Zwing-Tech)')]
class PullSalesforceCasesCommand extends Command
{
    public function handle(SalesforceCasePuller $puller): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            $rows = spin(
                callback: fn (): array => $puller->fetch(),
                message: 'Querying Zwing / Zwing-Tech cases from Salesforce...',
            );
        } catch (SalesforceQueryException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $count = count($rows);

        if ($dryRun) {
            $this->info("Dry run: {$count} Salesforce cases (not saved).");

            return self::SUCCESS;
        }

        if ($rows !== []) {
            progress(
                label: 'Saving case headers',
                steps: array_chunk($rows, SalesforceCasePuller::CHUNK_SIZE),
                callback: function (array $chunk) use ($puller): void {
                    $puller->persistChunk($chunk);
                },
                hint: "{$count} cases",
            );
        }

        $this->info("Pulled {$count} Salesforce cases.");

        return self::SUCCESS;
    }
}
