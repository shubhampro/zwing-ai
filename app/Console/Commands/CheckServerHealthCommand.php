<?php

namespace App\Console\Commands;

use App\Services\DbHealth\DbHealthChecker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('server-health:check')]
#[Description('Run lightweight DB health checks and cache the snapshot')]
class CheckServerHealthCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DbHealthChecker $checker): int
    {
        $existing = $checker->snapshot();

        if ($existing !== null) {
            $this->info('Cached snapshot still fresh; skipping check.');

            return self::SUCCESS;
        }

        $result = $checker->run();

        if ($result === null) {
            $this->warn('Another health check holds the lock; skipped.');

            return self::SUCCESS;
        }

        $this->info("Health check complete: {$result['overall_status']}");

        return self::SUCCESS;
    }
}
