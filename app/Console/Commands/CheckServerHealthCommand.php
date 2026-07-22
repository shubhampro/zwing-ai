<?php

namespace App\Console\Commands;

use App\Enums\ExternalQueryJobType;
use App\Services\DbHealth\DbHealthChecker;
use App\Services\ExternalQueryDispatcher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('server-health:check')]
#[Description('Queue lightweight DB health checks on external-query')]
class CheckServerHealthCommand extends Command
{
    public function handle(DbHealthChecker $checker, ExternalQueryDispatcher $dispatcher): int
    {
        $existing = $checker->snapshot();

        if ($existing !== null) {
            $this->info('Cached snapshot still fresh; skipping check.');

            return self::SUCCESS;
        }

        if (cache()->has(config('server_health.lock_key').':held')) {
            $this->warn('Another health check holds the lock; skipped.');

            return self::SUCCESS;
        }

        $log = $dispatcher->dispatch(
            jobType: ExternalQueryJobType::ServerHealthCheck,
            user: null,
        );

        $this->info("Health check queued on external-query (log #{$log->id}).");

        return self::SUCCESS;
    }
}
