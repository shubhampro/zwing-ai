<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Invite;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role as SpatieRole;

#[Signature('user:invite {email? : Optional email locked to this invite} {--days= : Optional expiry in days} {--role=operator : Role name to assign on register}')]
#[Description('Create a single-use invite registration link')]
class CreateUserInviteCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        $days = $this->option('days');
        $roleName = (string) $this->option('role');

        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error("Invalid email address [{$email}].");

            return self::FAILURE;
        }

        if ($days !== null && (! ctype_digit((string) $days) || (int) $days < 1)) {
            $this->error('The --days option must be a positive integer.');

            return self::FAILURE;
        }

        $roleExists = SpatieRole::query()->where('name', $roleName)->exists();

        if (! $roleExists) {
            $available = SpatieRole::query()->orderBy('name')->pluck('name')->implode(', ');
            $this->error('The --role option must be one of: '.($available !== '' ? $available : implode(', ', Role::values())).'.');

            return self::FAILURE;
        }

        $invite = Invite::query()->create([
            'token' => Invite::generateToken(),
            'email' => $email,
            'role' => $roleName,
            'expires_at' => $days !== null ? now()->addDays((int) $days) : null,
        ]);

        $this->info('Single-use invite created.');
        if ($invite->email !== null) {
            $this->line("Email locked to: {$invite->email}");
        }
        $this->line('Role: '.$invite->role);
        if ($invite->expires_at !== null) {
            $this->line('Expires at: '.$invite->expires_at->toDateTimeString());
        }
        $this->newLine();
        $this->line($invite->registrationUrl());

        return self::SUCCESS;
    }
}
