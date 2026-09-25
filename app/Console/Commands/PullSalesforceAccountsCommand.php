<?php

namespace App\Console\Commands;

use App\Exceptions\SalesforceQueryException;
use App\Services\Salesforce\SalesforceAccountPuller;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sf:pull-accounts {--dry-run : Query Salesforce without writing}')]
#[Description('Pull all Salesforce accounts into the local database')]
class PullSalesforceAccountsCommand extends Command
{
    public function handle(SalesforceAccountPuller $puller): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            $count = $puller->pull(dryRun: $dryRun);
        } catch (SalesforceQueryException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info("Dry run: {$count} Salesforce accounts (not saved).");

            return self::SUCCESS;
        }

        $this->info("Pulled {$count} Salesforce accounts.");

        return self::SUCCESS;
    }
}
