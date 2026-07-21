<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\UpdateUserRoleRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role as SpatieRole;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        abort_if($request->user() === null, 403);

        $users = User::query()
            ->with('roles:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'created_at'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->getRoleNames()->first() ?? Role::Operator->value,
                'created_at' => $user->created_at?->toIso8601String(),
            ]);

        return Inertia::render('users/index', [
            'users' => $users,
            'roles' => SpatieRole::query()->orderBy('name')->pluck('name')->values()->all(),
        ]);
    }

    public function updateRole(UpdateUserRoleRequest $request, User $user): RedirectResponse
    {
        $role = $request->string('role')->toString();

        if ($request->user()?->is($user) && $role !== Role::Admin->value) {
            return back()->withErrors([
                'role' => __('You cannot remove your own admin role.'),
            ]);
        }

        $user->syncRoles([$role]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('User role updated successfully.'),
        ]);

        return redirect()->route('users.index');
    }
}
