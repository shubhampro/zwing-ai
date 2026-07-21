<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;

#[Signature('user:reset-two-factor {email : The email address of the user}')]
#[Description('Reset two-factor authentication for a user so they can re-enroll')]
class ResetUserTwoFactorCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DisableTwoFactorAuthentication $disable): int
    {
        $email = (string) $this->argument('email');

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("No user found with email [{$email}].");

            return self::FAILURE;
        }

        if (! $user->hasEnabledTwoFactorAuthentication() && $user->two_factor_secret === null) {
            $this->info("User [{$email}] has no two-factor authentication to reset.");

            return self::SUCCESS;
        }

        $disable($user);

        $this->info("Two-factor authentication reset for [{$email}]. User must set up 2FA again on next login.");

        return self::SUCCESS;
    }
}
