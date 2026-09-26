<?php

namespace App\Console\Commands;

use App\Exceptions\SalesforceQueryException;
use App\Services\Salesforce\SalesforceCasePicklistPuller;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sf:pull-products {--dry-run : Describe Salesforce without writing}')]
#[Description('Pull Case Product__c picklist values into the local database')]
class PullSalesforceProductsCommand extends Command
{
    public function handle(SalesforceCasePicklistPuller $puller): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            $count = $puller->pullProducts(dryRun: $dryRun);
        } catch (SalesforceQueryException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info("Dry run: {$count} Salesforce products (not saved).");

            return self::SUCCESS;
        }

        $this->info("Pulled {$count} Salesforce products.");

        return self::SUCCESS;
    }
}
