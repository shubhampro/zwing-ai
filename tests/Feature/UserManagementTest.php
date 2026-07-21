<?php

use App\Enums\Role;
use App\Models\Invite;
use App\Models\User;
use App\Support\Permissions;

use function Pest\Laravel\actingAs;

it('lists users for admins', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->operator()->create(['email' => 'ops@example.com']);

    actingAs($admin)
        ->get('/users')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('users/index')
            ->has('users')
            ->has('roles'));
});

it('updates a user role', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->operator()->create();

    actingAs($admin)
        ->put("/users/{$user->id}/role", ['role' => Role::Viewer->value])
        ->assertRedirect('/users');

    expect($user->fresh()->hasRole(Role::Viewer))->toBeTrue()
        ->and($user->fresh()->hasRole(Role::Operator))->toBeFalse();
});

it('prevents an admin from removing their own admin role', function () {
    $admin = User::factory()->admin()->create();

    actingAs($admin)
        ->put("/users/{$admin->id}/role", ['role' => Role::Operator->value])
        ->assertSessionHasErrors('role');

    expect($admin->fresh()->hasRole(Role::Admin))->toBeTrue();
});

it('creates an invite with a role from the UI', function () {
    $admin = User::factory()->admin()->create();

    actingAs($admin)
        ->post('/invites', [
            'email' => 'new@example.com',
            'role' => Role::Viewer->value,
            'days' => 3,
        ])
        ->assertRedirect('/invites');

    $invite = Invite::query()->first();

    expect($invite)->not->toBeNull()
        ->and($invite->email)->toBe('new@example.com')
        ->and($invite->role)->toBe(Role::Viewer->value)
        ->and($invite->invited_by)->toBe($admin->id)
        ->and($invite->expires_at)->not->toBeNull();
});

it('forbids operators from managing users', function () {
    $operator = User::factory()->operator()->create();

    actingAs($operator)->get('/users')->assertForbidden();
    actingAs($operator)->post('/invites', [
        'role' => Role::Operator->value,
    ])->assertForbidden();
});

it('soft deletes a user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->operator()->create();

    actingAs($admin)
        ->delete("/users/{$user->id}")
        ->assertRedirect('/users');

    expect(User::query()->find($user->id))->toBeNull()
        ->and(User::withTrashed()->find($user->id)?->trashed())->toBeTrue();
});

it('permanently deletes a user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->operator()->create();

    actingAs($admin)
        ->delete("/users/{$user->id}/force")
        ->assertRedirect('/users');

    expect(User::withTrashed()->find($user->id))->toBeNull();
});

it('restores a soft deleted user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->operator()->create();
    $user->delete();

    actingAs($admin)
        ->post("/users/{$user->id}/restore")
        ->assertRedirect('/users');

    expect(User::query()->find($user->id))->not->toBeNull()
        ->and(User::query()->find($user->id)?->trashed())->toBeFalse();
});

it('prevents deleting yourself from the users module', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->admin()->create(['email' => 'other-admin@example.com']);

    actingAs($admin)
        ->delete("/users/{$admin->id}")
        ->assertSessionHasErrors('user');

    expect(User::query()->find($admin->id))->not->toBeNull();
});

it('prevents deleting the last admin', function () {
    $admin = User::factory()->admin()->create();
    $operator = User::factory()->operator()->create();
    $operator->givePermissionTo(Permissions::UsersManage);

    actingAs($operator)
        ->delete("/users/{$admin->id}")
        ->assertSessionHasErrors('user');

    expect(User::query()->find($admin->id))->not->toBeNull();
});
