<?php

namespace App\Console\Commands;

use App\Exceptions\SalesforceQueryException;
use App\Services\Salesforce\SalesforceCasePicklistPuller;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sf:pull-applications {--dry-run : Describe Salesforce without writing}')]
#[Description('Pull Case Application__c picklist values into the local database')]
class PullSalesforceApplicationsCommand extends Command
{
    public function handle(SalesforceCasePicklistPuller $puller): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            $count = $puller->pullApplications(dryRun: $dryRun);
        } catch (SalesforceQueryException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info("Dry run: {$count} Salesforce applications (not saved).");

            return self::SUCCESS;
        }

        $this->info("Pulled {$count} Salesforce applications.");

        return self::SUCCESS;
    }
}
