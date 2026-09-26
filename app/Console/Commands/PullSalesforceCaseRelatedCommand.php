<?php

namespace App\Console\Commands;

use App\Exceptions\SalesforceQueryException;
use App\Models\SfCase;
use App\Services\Salesforce\SalesforceCaseActivityPuller;
use App\Services\Salesforce\SalesforceCaseHistoryPuller;
use App\Services\Salesforce\SalesforceCasePuller;
use App\Support\IndiaDateTime;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use InvalidArgumentException;
use Symfony\Component\Console\Helper\ProgressBar;

#[Signature('sf:pull-case-related {--from= : Inclusive start date IST (Y-m-d), default 2026-03-01} {--to= : Inclusive end date IST (Y-m-d), default today} {--dry-run : Query Salesforce without writing}')]
#[Description('Pull Salesforce cases and related history/activity with per-table loading')]
class PullSalesforceCaseRelatedCommand extends Command
{
    public function handle(
        SalesforceCasePuller $cases,
        SalesforceCaseHistoryPuller $history,
        SalesforceCaseActivityPuller $activity,
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $from = filled($this->option('from')) ? (string) $this->option('from') : null;
        $to = filled($this->option('to')) ? (string) $this->option('to') : null;

        try {
            [$start, $endExclusive] = $cases->range($from, $to);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $endInclusive = $endExclusive->copy()->subDay();

        try {
            $counts = [
                'sf_cases' => $this->pullCases($cases, $dryRun, $from, $to, $start, $endInclusive),
                'sf_case_group_histories' => $this->pullHistory($history, $dryRun),
                'sf_case_activities' => $this->pullActivity($activity, $dryRun),
            ];
        } catch (SalesforceQueryException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Table', 'Rows'],
            collect($counts)
                ->map(fn (int $count, string $table): array => [$table, Number::format($count)])
                ->values()
                ->all(),
        );

        $total = array_sum($counts);

        if ($dryRun) {
            $this->info('Dry run: '.Number::format($total).' Salesforce case-related rows (not saved).');

            return self::SUCCESS;
        }

        $this->info('Pulled '.Number::format($total).' Salesforce case-related rows.');

        return self::SUCCESS;
    }

    private function pullCases(
        SalesforceCasePuller $cases,
        bool $dryRun,
        ?string $from,
        ?string $to,
        Carbon $start,
        Carbon $endInclusive,
    ): int {
        $windows = $cases->windows($from, $to);
        $records = [];

        if ($windows !== []) {
            $this->line("Querying sf_cases {$start->toDateString()} to {$endInclusive->toDateString()} IST");
            $bar = $this->progressBar(count($windows), '0 cases fetched');

            foreach ($windows as $window) {
                $records = [...$records, ...$cases->queryWindow($window[0], $window[1])];
                $bar->setMessage($this->windowHint($window, count($records)));
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        }

        $rows = $cases->mapRecords($records);
        $count = count($rows);

        if (! $dryRun && $rows !== []) {
            $saved = 0;
            $this->line('Saving sf_cases');
            $bar = $this->progressBar(count($rows), '0 / '.Number::format($count).' cases saved');

            foreach (array_chunk($rows, SalesforceCasePuller::CHUNK_SIZE) as $chunk) {
                $cases->persistChunk($chunk);
                $saved += count($chunk);
                $bar->setMessage(Number::format($saved).' / '.Number::format($count).' cases saved');
                $bar->advance(count($chunk));
            }

            $bar->finish();
            $this->newLine();
        }

        $this->info('sf_cases: '.Number::format($count).' cases');

        return $count;
    }

    private function pullHistory(SalesforceCaseHistoryPuller $history, bool $dryRun): int
    {
        return $this->pullCaseChunks(
            table: 'sf_case_group_histories',
            noun: 'history rows',
            dryRun: $dryRun,
            fetchChunk: fn (Collection $chunk): array => $history->fetchChunk($chunk),
            persistChunk: fn (array $chunk): mixed => $history->persistChunk($chunk),
            persistChunkSize: SalesforceCaseHistoryPuller::PERSIST_CHUNK_SIZE,
            caseChunkSize: SalesforceCaseHistoryPuller::CASE_ID_CHUNK_SIZE,
        );
    }

    private function pullActivity(SalesforceCaseActivityPuller $activity, bool $dryRun): int
    {
        return $this->pullCaseChunks(
            table: 'sf_case_activities',
            noun: 'activity rows',
            dryRun: $dryRun,
            fetchChunk: fn (Collection $chunk): array => $activity->fetchChunk($chunk),
            persistChunk: fn (array $chunk): mixed => $activity->persistChunk($chunk),
            persistChunkSize: SalesforceCaseActivityPuller::PERSIST_CHUNK_SIZE,
            caseChunkSize: SalesforceCaseActivityPuller::CASE_ID_CHUNK_SIZE,
        );
    }

    /**
     * @param  callable(Collection<string, int|string>): list<array<string, mixed>>  $fetchChunk
     * @param  callable(list<array<string, mixed>>): mixed  $persistChunk
     */
    private function pullCaseChunks(
        string $table,
        string $noun,
        bool $dryRun,
        callable $fetchChunk,
        callable $persistChunk,
        int $persistChunkSize,
        int $caseChunkSize,
    ): int {
        $caseIdsBySfId = SfCase::query()->pluck('id', 'sf_id');

        if ($caseIdsBySfId->isEmpty()) {
            $this->info("{$table}: 0 {$noun} (no local cases)");

            return 0;
        }

        $totalCases = $caseIdsBySfId->count();
        $doneCases = 0;
        $count = 0;
        $this->line(($dryRun ? 'Querying ' : 'Pulling ').$table);
        $bar = $this->progressBar($totalCases, '0 '.$noun.' · 0 / '.Number::format($totalCases).' cases');

        foreach ($caseIdsBySfId->chunk($caseChunkSize) as $chunk) {
            $rows = $fetchChunk($chunk);
            $count += count($rows);
            $doneCases += $chunk->count();
            $bar->setMessage(
                Number::format($count).' '.$noun.' · '.Number::format($doneCases).' / '.Number::format($totalCases).' cases',
            );
            $bar->advance($chunk->count());

            if ($dryRun || $rows === []) {
                continue;
            }

            foreach (array_chunk($rows, $persistChunkSize) as $persistRows) {
                $persistChunk($persistRows);
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("{$table}: ".Number::format($count).' '.$noun);

        return $count;
    }

    private function progressBar(int $max, string $message): ProgressBar
    {
        $bar = $this->output->createProgressBar($max);
        $bar->setFormat(' %current%/%max% [%bar%] %message%');
        $bar->setMessage($message);
        $bar->start();

        return $bar;
    }

    /**
     * @param  array{0: string, 1: string}  $window
     */
    private function windowHint(array $window, int $fetched): string
    {
        $start = Carbon::parse($window[0])->timezone(IndiaDateTime::TIMEZONE)->toDateString();
        $end = Carbon::parse($window[1])->timezone(IndiaDateTime::TIMEZONE)->toDateString();

        return "{$start} to {$end} · ".Number::format($fetched).' cases fetched';
    }
}
