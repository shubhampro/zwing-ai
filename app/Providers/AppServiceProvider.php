<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->enforceReadOnlyRemoteConnections();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(function (): Password {
            $rule = Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols();

            return app()->isProduction()
                ? $rule->uncompromised()
                : $rule;
        });
    }

    /**
     * Prevent any write queries on remote read-only connections.
     *
     * Both mysql_ssh and mongodb_ssh are remote read-only sources.
     * Throws a RuntimeException if INSERT/UPDATE/DELETE/DDL is attempted.
     */
    protected function enforceReadOnlyRemoteConnections(): void
    {
        $writePattern = '/^\s*(insert|update|delete|drop|truncate|alter|create|replace|rename)\b/i';

        foreach (['mysql_ssh', 'mongodb_ssh'] as $connection) {
            DB::connection($connection)->beforeExecuting(function (string $sql) use ($connection, $writePattern): void {
                if (preg_match($writePattern, $sql)) {
                    throw new \RuntimeException("Connection [{$connection}] is read-only. Write query rejected: {$sql}");
                }
            });
        }
    }
}
