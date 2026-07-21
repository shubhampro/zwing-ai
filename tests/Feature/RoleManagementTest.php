<?php

use App\Enums\Role;
use App\Models\Invite;
use App\Models\User;
use App\Support\Permissions;
use Spatie\Permission\Models\Role as SpatieRole;

use function Pest\Laravel\actingAs;

it('forbids viewers and operators from managing roles', function () {
    $viewer = User::factory()->viewer()->create();
    $operator = User::factory()->operator()->create();

    actingAs($viewer)->get('/roles')->assertForbidden();
    actingAs($operator)->get('/roles')->assertForbidden();
    actingAs($operator)->post('/roles', [
        'name' => 'auditor',
        'permissions' => [Permissions::OrganizationsView],
    ])->assertForbidden();
});

it('lists roles for admins', function () {
    $admin = User::factory()->admin()->create();

    actingAs($admin)
        ->get('/roles')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('roles/index')
            ->has('roles'));
});

it('creates a role with selected permissions', function () {
    $admin = User::factory()->admin()->create();

    actingAs($admin)
        ->post('/roles', [
            'name' => 'auditor',
            'permissions' => [
                Permissions::OrganizationsView,
                Permissions::SqlQueriesView,
            ],
        ])
        ->assertRedirect('/roles');

    $role = SpatieRole::query()->where('name', 'auditor')->first();

    expect($role)->not->toBeNull()
        ->and($role->hasPermissionTo(Permissions::OrganizationsView))->toBeTrue()
        ->and($role->hasPermissionTo(Permissions::SqlQueriesView))->toBeTrue()
        ->and($role->hasPermissionTo(Permissions::UsersManage))->toBeFalse();
});

it('updates a role permission matrix', function () {
    $admin = User::factory()->admin()->create();
    $role = SpatieRole::create(['name' => 'auditor', 'guard_name' => 'web']);
    $role->syncPermissions([Permissions::OrganizationsView]);

    actingAs($admin)
        ->put("/roles/{$role->id}", [
            'name' => 'auditor',
            'permissions' => [Permissions::OrganizationsView, Permissions::OrganizationsCreate],
        ])
        ->assertRedirect('/roles');

    expect($role->fresh()->hasPermissionTo(Permissions::OrganizationsCreate))->toBeTrue();
});

it('cannot delete system roles', function () {
    $admin = User::factory()->admin()->create();
    $systemRole = SpatieRole::findByName(Role::Admin->value);

    actingAs($admin)
        ->delete("/roles/{$systemRole->id}")
        ->assertRedirect()
        ->assertSessionHasErrors('role');

    expect(SpatieRole::query()->where('name', Role::Admin->value)->exists())->toBeTrue();
});

it('cannot strip critical permissions from admin', function () {
    $admin = User::factory()->admin()->create();
    $systemRole = SpatieRole::findByName(Role::Admin->value);

    actingAs($admin)
        ->put("/roles/{$systemRole->id}", [
            'name' => Role::Admin->value,
            'permissions' => [Permissions::OrganizationsView],
        ])
        ->assertSessionHasErrors('permissions');

    expect($systemRole->fresh()->hasPermissionTo(Permissions::UsersManage))->toBeTrue()
        ->and($systemRole->fresh()->hasPermissionTo(Permissions::RolesManage))->toBeTrue();
});

it('cannot rename system roles', function () {
    $admin = User::factory()->admin()->create();
    $systemRole = SpatieRole::findByName(Role::Operator->value);

    actingAs($admin)
        ->put("/roles/{$systemRole->id}", [
            'name' => 'ops-renamed',
            'permissions' => [Permissions::OrganizationsView],
        ])
        ->assertSessionHasErrors('name');

    expect($systemRole->fresh()->name)->toBe(Role::Operator->value);
});

it('assigns a custom role to a user and invite', function () {
    $admin = User::factory()->admin()->create();
    $role = SpatieRole::create(['name' => 'auditor', 'guard_name' => 'web']);
    $role->syncPermissions([Permissions::OrganizationsView]);
    $user = User::factory()->operator()->create();

    actingAs($admin)
        ->put("/users/{$user->id}/role", ['role' => 'auditor'])
        ->assertRedirect('/users');

    expect($user->fresh()->hasRole('auditor'))->toBeTrue();

    actingAs($admin)
        ->post('/invites', [
            'email' => 'auditor@example.com',
            'role' => 'auditor',
        ])
        ->assertRedirect('/invites');

    expect(Invite::query()->first()?->role)->toBe('auditor');
});

it('deletes an unused custom role', function () {
    $admin = User::factory()->admin()->create();
    $role = SpatieRole::create(['name' => 'temp-role', 'guard_name' => 'web']);

    actingAs($admin)
        ->delete("/roles/{$role->id}")
        ->assertRedirect('/roles');

    expect(SpatieRole::query()->where('name', 'temp-role')->exists())->toBeFalse();
});
