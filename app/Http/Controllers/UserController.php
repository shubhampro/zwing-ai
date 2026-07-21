<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\DestroyUserRequest;
use App\Http\Requests\ForceDestroyUserRequest;
use App\Http\Requests\RestoreUserRequest;
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
            ->withTrashed()
            ->with('roles:id,name')
            ->orderByRaw('deleted_at is null desc')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'created_at', 'deleted_at'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->getRoleNames()->first() ?? Role::Operator->value,
                'created_at' => $user->created_at?->toIso8601String(),
                'deleted_at' => $user->deleted_at?->toIso8601String(),
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

    public function destroy(DestroyUserRequest $request, User $user): RedirectResponse
    {
        if ($redirect = $this->guardUserDeletion($request, $user)) {
            return $redirect;
        }

        $user->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('User soft deleted successfully.'),
        ]);

        return redirect()->route('users.index');
    }

    public function forceDestroy(ForceDestroyUserRequest $request, int $user): RedirectResponse
    {
        $target = User::withTrashed()->findOrFail($user);

        if ($redirect = $this->guardUserDeletion($request, $target)) {
            return $redirect;
        }

        $target->forceDelete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('User permanently deleted.'),
        ]);

        return redirect()->route('users.index');
    }

    public function restore(RestoreUserRequest $request, int $user): RedirectResponse
    {
        $target = User::onlyTrashed()->findOrFail($user);

        $target->restore();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('User restored successfully.'),
        ]);

        return redirect()->route('users.index');
    }

    private function guardUserDeletion(Request $request, User $user): ?RedirectResponse
    {
        if ($request->user()?->is($user)) {
            return back()->withErrors([
                'user' => __('You cannot delete your own account from here.'),
            ]);
        }

        if (
            $user->hasRole(Role::Admin)
            && ! User::role(Role::Admin)->whereKeyNot($user->id)->exists()
        ) {
            return back()->withErrors([
                'user' => __('You cannot delete the last admin.'),
            ]);
        }

        return null;
    }
}
