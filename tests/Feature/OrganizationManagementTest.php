<?php

use App\Models\Organization;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\get;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('shows organization index', function () {
    Organization::factory()->create(['name' => 'Acme Corp', 'ba_code' => 'BA-001', 'vendor_id' => 100]);

    actingAs($this->user)
        ->get('/organizations')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organizations/index')
            ->has('organizations', 1)
        );
});

it('shows empty organization index', function () {
    actingAs($this->user)
        ->get('/organizations')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organizations/index')
            ->has('organizations', 0)
        );
});

it('shows create page', function () {
    actingAs($this->user)
        ->get('/organizations/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('organizations/create'));
});

it('creates an organization', function () {
    actingAs($this->user)
        ->post('/organizations', [
            'name' => 'Test Org',
            'ba_code' => 'BA-100',
            'vendor_id' => 999,
        ])
        ->assertRedirect('/organizations');

    assertDatabaseHas('organizations', ['ba_code' => 'BA-100', 'vendor_id' => 999]);
});

it('validates required fields on create', function () {
    actingAs($this->user)
        ->post('/organizations', [])
        ->assertSessionHasErrors(['name', 'ba_code', 'vendor_id']);
});

it('shows edit page', function () {
    $org = Organization::factory()->create();

    actingAs($this->user)
        ->get("/organizations/{$org->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('organizations/edit'));
});

it('updates an organization', function () {
    $org = Organization::factory()->create(['ba_code' => 'BA-OLD']);

    actingAs($this->user)
        ->put("/organizations/{$org->id}", [
            'name' => 'Updated Name',
            'ba_code' => 'BA-NEW',
            'vendor_id' => 555,
        ])
        ->assertRedirect('/organizations');

    assertDatabaseHas('organizations', ['id' => $org->id, 'ba_code' => 'BA-NEW', 'name' => 'Updated Name']);
});

it('deletes an organization', function () {
    $org = Organization::factory()->create();

    actingAs($this->user)
        ->delete("/organizations/{$org->id}")
        ->assertRedirect('/organizations');

    assertDatabaseMissing('organizations', ['id' => $org->id]);
});

it('redirects unauthenticated users', function () {
    get('/organizations')->assertRedirect('/login');
});
