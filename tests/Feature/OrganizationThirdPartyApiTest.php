<?php

use App\Models\Organization;
use App\Models\OrganizationThirdPartyApi;
use App\Models\ThirdPartyApi;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->api = ThirdPartyApi::factory()->create([
        'path' => '/api/v1/orders',
    ]);
});

it('shows organization view with api apps', function () {
    actingAs($this->user)
        ->get(route('organizations.show', $this->organization))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organizations/show')
            ->has('apiApps', 1)
            ->where('apiApps.0.name', $this->api->name)
            ->where('apiApps.0.connection', null));
});

it('saves base url and token from organization view', function () {
    actingAs($this->user)
        ->post(route('organizations.api-connections.store', $this->organization), [
            'third_party_api_id' => $this->api->id,
            'base_url' => 'https://api.vendor-a.example.com',
            'auth_token' => 'secret-token',
            'is_active' => true,
        ])
        ->assertRedirect(route('organizations.show', $this->organization));

    $connection = OrganizationThirdPartyApi::query()->firstOrFail();

    expect($connection->auth_token)->toBe('secret-token')
        ->and($connection->endpointUrl())->toBe('https://api.vendor-a.example.com/api/v1/orders');

    assertDatabaseHas('organization_third_party_apis', [
        'organization_id' => $this->organization->id,
        'third_party_api_id' => $this->api->id,
    ]);
});

it('updates connection from organization view without changing token when blank', function () {
    $connection = OrganizationThirdPartyApi::factory()->create([
        'organization_id' => $this->organization->id,
        'third_party_api_id' => $this->api->id,
        'auth_token' => 'keep-me',
    ]);

    actingAs($this->user)
        ->put(route('organizations.api-connections.update', [$this->organization, $connection]), [
            'base_url' => 'https://api.vendor-b.example.com',
            'is_active' => true,
        ])
        ->assertRedirect(route('organizations.show', $this->organization));

    $connection->refresh();

    expect($connection->base_url)->toBe('https://api.vendor-b.example.com')
        ->and($connection->auth_token)->toBe('keep-me');
});

it('deletes connection from organization view', function () {
    $connection = OrganizationThirdPartyApi::factory()->create([
        'organization_id' => $this->organization->id,
        'third_party_api_id' => $this->api->id,
    ]);

    actingAs($this->user)
        ->delete(route('organizations.api-connections.destroy', [$this->organization, $connection]))
        ->assertRedirect(route('organizations.show', $this->organization));

    expect(OrganizationThirdPartyApi::find($connection->id))->toBeNull();
});
