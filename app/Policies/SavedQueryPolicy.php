<?php

namespace App\Policies;

use App\Models\SavedQuery;
use App\Models\User;

class SavedQueryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SavedQuery $savedQuery): bool
    {
        return (int) $user->id === (int) $savedQuery->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, SavedQuery $savedQuery): bool
    {
        return (int) $user->id === (int) $savedQuery->user_id;
    }

    public function delete(User $user, SavedQuery $savedQuery): bool
    {
        return (int) $user->id === (int) $savedQuery->user_id;
    }

    public function restore(User $user, SavedQuery $savedQuery): bool
    {
        return false;
    }

    public function forceDelete(User $user, SavedQuery $savedQuery): bool
    {
        return false;
    }
}
