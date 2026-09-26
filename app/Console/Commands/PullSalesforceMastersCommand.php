<?php

namespace App\Console\Commands;

use App\Exceptions\SalesforceQueryException;
use App\Services\Salesforce\SalesforceAccountPuller;
use App\Services\Salesforce\SalesforceAgentPuller;
use App\Services\Salesforce\SalesforceCasePicklistPuller;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Prompts\Progress;

use function Laravel\Prompts\progress;

#[Signature('sf:pull-masters {--dry-run : Query Salesforce without writing}')]
#[Description('Pull all Salesforce master catalogs with per-table loading')]
class PullSalesforceMastersCommand extends Command
{
    public function handle(
        SalesforceAccountPuller $accounts,
        SalesforceCasePicklistPuller $picklists,
        SalesforceAgentPuller $agents,
    ): int {
        $dryRun = (bool) $this->option('dry-run');

        /** @var array<string, callable(): int> $jobs */
        $jobs = [
            'sf_accounts' => fn (): int => $accounts->pull(dryRun: $dryRun),
            'sf_products' => fn (): int => $picklists->pullProducts(dryRun: $dryRun),
            'sf_applications' => fn (): int => $picklists->pullApplications(dryRun: $dryRun),
            'sf_modules' => fn (): int => $picklists->pullModules(dryRun: $dryRun),
            'sf_sub_modules' => fn (): int => $picklists->pullSubModules(dryRun: $dryRun),
            'sf_types' => fn (): int => $picklists->pullTypes(dryRun: $dryRun),
            'sf_groups' => fn (): int => $picklists->pullGroups(dryRun: $dryRun),
            'sf_agents' => fn (): int => $agents->pull(dryRun: $dryRun),
        ];

        /** @var array<string, int> $counts */
        $counts = [];

        try {
            progress(
                label: 'Pulling Salesforce masters',
                steps: array_keys($jobs),
                callback: function (string $table, Progress $progress) use ($jobs, $dryRun, &$counts): void {
                    $progress->label($dryRun ? "Describing {$table}" : "Loading {$table}");
                    $progress->hint("{$table} in progress");

                    $counts[$table] = $jobs[$table]();

                    $progress->hint("{$table}: {$counts[$table]} rows");
                },
                hint: 'sf_accounts → sf_agents',
            );
        } catch (SalesforceQueryException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Table', 'Rows'],
            collect($counts)
                ->map(fn (int $count, string $table): array => [$table, (string) $count])
                ->values()
                ->all(),
        );

        $total = array_sum($counts);

        if ($dryRun) {
            $this->info("Dry run: {$total} Salesforce master rows (not saved).");

            return self::SUCCESS;
        }

        $this->info("Pulled {$total} Salesforce master rows.");

        return self::SUCCESS;
    }
}
