<?php

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use App\Support\Permissions;

use function Pest\Laravel\actingAs;

it('allows viewers to view organizations but not create or attach', function () {
    $viewer = User::factory()->viewer()->create();

    actingAs($viewer)->get('/organizations')->assertOk();
    actingAs($viewer)->get('/organizations/create')->assertForbidden();
    actingAs($viewer)
        ->post('/organizations', [
            'name' => 'Nope',
            'ba_code' => 'NOPE',
            'vendor_id' => 1,
        ])
        ->assertForbidden();
    actingAs($viewer)
        ->post('/organizations/attach-zwing-vendor', ['vendor_id' => 1])
        ->assertForbidden();
});

it('allows operators to manage organizations but not users or invites', function () {
    $operator = User::factory()->operator()->create();

    actingAs($operator)->get('/organizations')->assertOk();
    actingAs($operator)->get('/organizations/create')->assertOk();
    actingAs($operator)->get('/users')->assertForbidden();
    actingAs($operator)->get('/invites')->assertForbidden();
});

it('allows admins to manage users and invites', function () {
    $admin = User::factory()->admin()->create();

    actingAs($admin)->get('/users')->assertOk();
    actingAs($admin)->get('/invites')->assertOk();
});

it('blocks viewers from mutating tools', function () {
    $viewer = User::factory()->viewer()->create();

    actingAs($viewer)->get('/sql-queries')->assertOk();
    actingAs($viewer)->post('/sql-queries', [])->assertForbidden();
    actingAs($viewer)->get('/outbound-sync')->assertOk();
    actingAs($viewer)->post('/outbound-sync/fetch', [])->assertForbidden();
});

it('assigns expected permission sets to roles', function () {
    $admin = User::factory()->admin()->create();
    $operator = User::factory()->operator()->create();
    $viewer = User::factory()->viewer()->create();

    expect($admin->can(Permissions::UsersManage))->toBeTrue()
        ->and($admin->can(Permissions::OrganizationsDelete))->toBeTrue()
        ->and($operator->can(Permissions::OrganizationsCreate))->toBeTrue()
        ->and($operator->can(Permissions::UsersManage))->toBeFalse()
        ->and($viewer->can(Permissions::OrganizationsView))->toBeTrue()
        ->and($viewer->can(Permissions::OrganizationsCreate))->toBeFalse()
        ->and($viewer->hasRole(Role::Viewer))->toBeTrue();
});

it('forbids deleting organizations without permission', function () {
    $viewer = User::factory()->viewer()->create();
    $organization = Organization::factory()->create();

    actingAs($viewer)
        ->delete("/organizations/{$organization->id}")
        ->assertForbidden();

    expect(Organization::query()->whereKey($organization->id)->exists())->toBeTrue();
});
