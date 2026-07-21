<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Support\Permissions;

class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::OrganizationsView);
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->can(Permissions::OrganizationsView);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::OrganizationsCreate);
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->can(Permissions::OrganizationsUpdate);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $user->can(Permissions::OrganizationsDelete);
    }

    public function attachZwing(User $user): bool
    {
        return $user->can(Permissions::OrganizationsAttachZwing);
    }
}
