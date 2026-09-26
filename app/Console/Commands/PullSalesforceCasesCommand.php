<?php

namespace App\Console\Commands;

use App\Exceptions\SalesforceQueryException;
use App\Services\Salesforce\SalesforceCasePuller;
use App\Support\IndiaDateTime;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;
use InvalidArgumentException;
use Symfony\Component\Console\Helper\ProgressBar;

#[Signature('sf:pull-cases {--from= : Inclusive start date IST (Y-m-d), default 2026-03-01} {--to= : Inclusive end date IST (Y-m-d), default today} {--dry-run : Query Salesforce without writing}')]
#[Description('Pull Salesforce case headers in an IST created-date range')]
class PullSalesforceCasesCommand extends Command
{
    public function handle(SalesforceCasePuller $puller): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $from = filled($this->option('from')) ? (string) $this->option('from') : null;
        $to = filled($this->option('to')) ? (string) $this->option('to') : null;

        try {
            [$start, $endExclusive] = $puller->range($from, $to);
            $endInclusive = $endExclusive->copy()->subDay();
            $rows = $this->fetchCases($puller, $from, $to, $start, $endInclusive);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (SalesforceQueryException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $count = count($rows);

        if ($dryRun) {
            $this->info('Dry run: '.Number::format($count).' Salesforce cases (not saved).');

            return self::SUCCESS;
        }

        if ($rows !== []) {
            $saved = 0;
            $this->line('Saving case headers');
            $bar = $this->progressBar($count, '0 / '.Number::format($count).' cases saved');

            foreach (array_chunk($rows, SalesforceCasePuller::CHUNK_SIZE) as $chunk) {
                $puller->persistChunk($chunk);
                $saved += count($chunk);
                $bar->setMessage(Number::format($saved).' / '.Number::format($count).' cases saved');
                $bar->advance(count($chunk));
            }

            $bar->finish();
            $this->newLine();
        }

        $this->info('Pulled '.Number::format($count).' Salesforce cases.');

        return self::SUCCESS;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchCases(
        SalesforceCasePuller $puller,
        ?string $from,
        ?string $to,
        Carbon $start,
        Carbon $endInclusive,
    ): array {
        $windows = $puller->windows($from, $to);
        $records = [];

        if ($windows !== []) {
            $this->line("Querying Salesforce cases {$start->toDateString()} to {$endInclusive->toDateString()} IST");
            $bar = $this->progressBar(count($windows), '0 cases fetched');

            foreach ($windows as $window) {
                $records = [...$records, ...$puller->queryWindow($window[0], $window[1])];
                $startDate = Carbon::parse($window[0])->timezone(IndiaDateTime::TIMEZONE)->toDateString();
                $endDate = Carbon::parse($window[1])->timezone(IndiaDateTime::TIMEZONE)->toDateString();
                $bar->setMessage("{$startDate} to {$endDate} · ".Number::format(count($records)).' cases fetched');
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        }

        return $puller->mapRecords($records);
    }

    private function progressBar(int $max, string $message): ProgressBar
    {
        $bar = $this->output->createProgressBar($max);
        $bar->setFormat(' %current%/%max% [%bar%] %message%');
        $bar->setMessage($message);
        $bar->start();

        return $bar;
    }
}
