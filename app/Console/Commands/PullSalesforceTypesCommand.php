<?php

namespace App\Console\Commands;

use App\Exceptions\SalesforceQueryException;
use App\Services\Salesforce\SalesforceCasePicklistPuller;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sf:pull-types {--dry-run : Describe Salesforce without writing}')]
#[Description('Pull Case Type picklist values into the local database')]
class PullSalesforceTypesCommand extends Command
{
    public function handle(SalesforceCasePicklistPuller $puller): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            $count = $puller->pullTypes(dryRun: $dryRun);
        } catch (SalesforceQueryException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info("Dry run: {$count} Salesforce types (not saved).");

            return self::SUCCESS;
        }

        $this->info("Pulled {$count} Salesforce types.");

        return self::SUCCESS;
    }
}
