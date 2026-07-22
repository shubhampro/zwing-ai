<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\OrganizationDatabaseConnection;
use App\Models\User;

class OrganizationDatabaseConnectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function view(User $user, OrganizationDatabaseConnection $organizationDatabaseConnection): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function update(User $user, OrganizationDatabaseConnection $organizationDatabaseConnection): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function delete(User $user, OrganizationDatabaseConnection $organizationDatabaseConnection): bool
    {
        return $user->hasRole(Role::Admin);
    }
}
