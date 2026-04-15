<?php

namespace App\Policies;

use App\Models\DatabaseConnection;
use App\Models\User;

class DatabaseConnectionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DatabaseConnection $databaseConnection): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, DatabaseConnection $databaseConnection): bool
    {
        return true;
    }

    public function delete(User $user, DatabaseConnection $databaseConnection): bool
    {
        return false;
    }

    public function restore(User $user, DatabaseConnection $databaseConnection): bool
    {
        return false;
    }

    public function forceDelete(User $user, DatabaseConnection $databaseConnection): bool
    {
        return false;
    }

    public function viewActivityLogs(User $user): bool
    {
        return true;
    }
}
