<?php

namespace App\Console\Commands;

use App\Exceptions\SalesforceQueryException;
use App\Services\Salesforce\SalesforceAgentPuller;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sf:pull-agents {--dry-run : Query Salesforce without writing}')]
#[Description('Pull Salesforce Users referenced as Case Agent__c since 1 Mar 2026')]
class PullSalesforceAgentsCommand extends Command
{
    public function handle(SalesforceAgentPuller $puller): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            $count = $puller->pull(dryRun: $dryRun);
        } catch (SalesforceQueryException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info("Dry run: {$count} Salesforce agents (not saved).");

            return self::SUCCESS;
        }

        $this->info("Pulled {$count} Salesforce agents.");

        return self::SUCCESS;
    }
}
