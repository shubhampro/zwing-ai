<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (Permissions::all() as $permission) {
            Permission::findOrCreate($permission);
        }

        $admin = SpatieRole::findOrCreate(Role::Admin->value);
        $admin->syncPermissions(Permissions::all());

        $operator = SpatieRole::findOrCreate(Role::Operator->value);
        $operator->syncPermissions(Permissions::operatorPermissions());

        $viewer = SpatieRole::findOrCreate(Role::Viewer->value);
        $viewer->syncPermissions(Permissions::viewPermissions());

        User::query()
            ->whereDoesntHave('roles')
            ->each(fn (User $user) => $user->assignRole(Role::Admin));
    }
}
