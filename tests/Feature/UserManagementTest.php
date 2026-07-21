<?php

use App\Enums\Role;
use App\Models\Invite;
use App\Models\User;

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
