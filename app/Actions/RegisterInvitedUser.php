<?php

namespace App\Actions;

use App\Enums\Role;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterInvitedUser
{
    /**
     * @param  array{name: string, email: string, password: string}  $input
     */
    public function handle(Invite $invite, array $input): User
    {
        return DB::transaction(function () use ($invite, $input): User {
            /** @var Invite|null $lockedInvite */
            $lockedInvite = Invite::query()
                ->whereKey($invite->id)
                ->lockForUpdate()
                ->first();

            if ($lockedInvite === null || ! $lockedInvite->isValid()) {
                throw ValidationException::withMessages([
                    'email' => __('This invite link is no longer valid.'),
                ]);
            }

            if (filled($lockedInvite->email) && $input['email'] !== $lockedInvite->email) {
                throw ValidationException::withMessages([
                    'email' => __('You must register with the email address this invite was sent to.'),
                ]);
            }

            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $user->syncRoles([$lockedInvite->role ?? Role::Operator->value]);

            $lockedInvite->forceFill([
                'used_at' => now(),
                'used_by' => $user->id,
            ])->save();

            return $user;
        });
    }
}
