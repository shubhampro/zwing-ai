<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can(Permissions::RolesManage), 403);

        $roles = SpatieRole::query()
            ->with('permissions:id,name')
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (SpatieRole $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions_count' => $role->permissions->count(),
                'users_count' => $role->users_count,
                'is_system' => Role::isSystem($role->name),
            ]);

        return Inertia::render('roles/index', [
            'roles' => $roles,
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->can(Permissions::RolesManage), 403);

        return Inertia::render('roles/form', [
            'role' => null,
            'permissionGroups' => Permissions::grouped(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = SpatieRole::create([
            'name' => $request->string('name')->toString(),
            'guard_name' => config('auth.defaults.guard', 'web'),
        ]);

        $role->syncPermissions($request->input('permissions', []));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Role created successfully.'),
        ]);

        return redirect()->route('roles.index');
    }

    public function edit(Request $request, SpatieRole $role): Response
    {
        abort_unless($request->user()?->can(Permissions::RolesManage), 403);

        $role->load('permissions:id,name');

        return Inertia::render('roles/form', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->values()->all(),
                'is_system' => Role::isSystem($role->name),
            ],
            'permissionGroups' => Permissions::grouped(),
        ]);
    }

    public function update(UpdateRoleRequest $request, SpatieRole $role): RedirectResponse
    {
        if (! Role::isSystem($role->name)) {
            $role->name = $request->string('name')->toString();
            $role->save();
        }

        $role->syncPermissions($request->input('permissions', []));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Role updated successfully.'),
        ]);

        return redirect()->route('roles.index');
    }

    public function destroy(Request $request, SpatieRole $role): RedirectResponse
    {
        abort_unless($request->user()?->can(Permissions::RolesManage), 403);

        if (Role::isSystem($role->name)) {
            return back()->withErrors([
                'role' => __('System roles cannot be deleted.'),
            ]);
        }

        if ($role->users()->exists()) {
            return back()->withErrors([
                'role' => __('Cannot delete a role that is still assigned to users.'),
            ]);
        }

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Role deleted successfully.'),
        ]);

        return redirect()->route('roles.index');
    }
}
