<?php

use App\Enums\Role;
use App\Models\SfCase;
use App\Models\SfMbrReport;
use App\Models\User;
use App\Support\Permissions;
use Spatie\Permission\Models\Role as SpatieRole;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->withoutVite();
});

it('gives cst only salesforce permissions', function () {
    $cst = User::factory()->cst()->create();

    expect($cst->hasRole(Role::Cst))->toBeTrue()
        ->and($cst->can(Permissions::SfCasesView))->toBeTrue()
        ->and($cst->can(Permissions::SfMbrView))->toBeTrue()
        ->and($cst->can(Permissions::SfMbrManage))->toBeTrue()
        ->and($cst->can(Permissions::SfMbrDelete))->toBeTrue()
        ->and($cst->can(Permissions::OrganizationsView))->toBeFalse()
        ->and($cst->can(Permissions::UsersManage))->toBeFalse()
        ->and($cst->can(Permissions::StockReconView))->toBeFalse()
        ->and($cst->getAllPermissions()->pluck('name')->sort()->values()->all())
        ->toBe(collect(Permissions::cstPermissions())->sort()->values()->all());
});

it('allows cst to use salesforce pages and forbids other modules', function () {
    $cst = User::factory()->cst()->create();
    $report = SfMbrReport::factory()->create();
    $case = SfCase::factory()->create();

    actingAs($cst)->get(route('dashboard'))->assertOk();
    actingAs($cst)->get(route('sf-mbr.index'))->assertOk();
    actingAs($cst)->get(route('sf-mbr.create'))->assertOk();
    actingAs($cst)->get(route('sf-mbr.show', $report))->assertOk();
    actingAs($cst)->get(route('sf-mbr.summary', $report))->assertOk();
    actingAs($cst)->get(route('sf-mbr.details', $report))->assertOk();
    actingAs($cst)->get(route('sf-cases.index'))->assertOk();
    actingAs($cst)->get(route('sf-cases.show', $case))->assertOk();
    actingAs($cst)->get(route('organizations.index'))->assertForbidden();
    actingAs($cst)->get(route('users.index'))->assertForbidden();
    actingAs($cst)->get(route('stock-transaction-reconciliation.index'))->assertForbidden();
});

it('allows viewers to read salesforce but not create or delete mbr reports', function () {
    $viewer = User::factory()->viewer()->create();
    $report = SfMbrReport::factory()->create();

    actingAs($viewer)->get(route('sf-mbr.index'))->assertOk();
    actingAs($viewer)->get(route('sf-mbr.show', $report))->assertOk();
    actingAs($viewer)->get(route('sf-cases.index'))->assertOk();
    actingAs($viewer)->get(route('sf-mbr.create'))->assertForbidden();
    actingAs($viewer)->delete(route('sf-mbr.destroy', $report))->assertForbidden();
});

it('allows cst to delete an mbr report after viewing the summary', function () {
    $cst = User::factory()->cst()->create();
    $report = SfMbrReport::factory()->create();

    actingAs($cst)->get(route('sf-mbr.summary', $report))->assertOk();
    actingAs($cst)
        ->delete(route('sf-mbr.destroy', $report))
        ->assertRedirect(route('sf-mbr.index'));

    $this->assertModelMissing($report);
});

it('splits mbr manage and delete permissions', function () {
    $manageOnly = SpatieRole::create(['name' => 'mbr-manage', 'guard_name' => 'web']);
    $manageOnly->syncPermissions([Permissions::SfMbrView, Permissions::SfMbrManage]);
    $manager = User::factory()->create();
    $manager->syncRoles([$manageOnly]);

    $deleteOnly = SpatieRole::create(['name' => 'mbr-delete', 'guard_name' => 'web']);
    $deleteOnly->syncPermissions([Permissions::SfMbrView, Permissions::SfMbrDelete]);
    $deleter = User::factory()->create();
    $deleter->syncRoles([$deleteOnly]);

    $managed = SfMbrReport::factory()->create();
    $deleted = SfMbrReport::factory()->create();

    actingAs($manager)->get(route('sf-mbr.create'))->assertOk();
    actingAs($manager)->delete(route('sf-mbr.destroy', $managed))->assertForbidden();
    actingAs($deleter)->get(route('sf-mbr.create'))->assertForbidden();
    actingAs($deleter)->get(route('sf-mbr.summary', $deleted))->assertOk();
    actingAs($deleter)
        ->delete(route('sf-mbr.destroy', $deleted))
        ->assertRedirect(route('sf-mbr.index'));

    $this->assertModelExists($managed);
    $this->assertModelMissing($deleted);
});

it('forbids roles without salesforce permissions', function () {
    $role = SpatieRole::create(['name' => 'no-sf', 'guard_name' => 'web']);
    $role->syncPermissions([Permissions::OrganizationsView]);
    $user = User::factory()->create();
    $user->syncRoles([$role]);
    $report = SfMbrReport::factory()->create();

    actingAs($user)->get(route('sf-mbr.index'))->assertForbidden();
    actingAs($user)->get(route('sf-mbr.create'))->assertForbidden();
    actingAs($user)->get(route('sf-cases.index'))->assertForbidden();
    actingAs($user)->delete(route('sf-mbr.destroy', $report))->assertForbidden();
});
