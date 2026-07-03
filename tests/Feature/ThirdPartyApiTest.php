<?php

use App\HttpMethod;
use App\Models\Organization;
use App\Models\ThirdPartyApi;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
});

it('lists reusable third party api templates', function () {
    ThirdPartyApi::factory()->create(['name' => 'Inventory Sync']);

    actingAs($this->user)
        ->get(route('third-party-apis.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('third-party-apis/index')
            ->has('apis', 1)
            ->where('apis.0.name', 'Inventory Sync'));
});

it('creates a reusable api template without org credentials', function () {
    actingAs($this->user)
        ->post(route('third-party-apis.store'), [
            'name' => 'Inventory Sync',
            'path' => '/api/v1/inventory/sync',
            'method' => HttpMethod::Post->value,
            'params' => [
                ['key' => 'sku', 'csv_column' => 'sku', 'required' => true],
            ],
            'auth_header_name' => 'Authorization',
            'is_active' => true,
        ])
        ->assertRedirect(route('third-party-apis.index'));

    $api = ThirdPartyApi::query()->firstOrFail();

    expect($api->path)->toBe('/api/v1/inventory/sync')
        ->and($api->method)->toBe(HttpMethod::Post);

    assertDatabaseHas('third_party_apis', [
        'name' => 'Inventory Sync',
        'path' => '/api/v1/inventory/sync',
    ]);
});

it('updates api template', function () {
    $api = ThirdPartyApi::factory()->create();

    actingAs($this->user)
        ->put(route('third-party-apis.update', $api), [
            'name' => 'Renamed API',
            'path' => '/api/updated',
            'method' => HttpMethod::Post->value,
            'params' => [['key' => 'foo', 'csv_column' => 'foo', 'required' => true]],
            'auth_header_name' => 'Authorization',
            'is_active' => true,
        ])
        ->assertRedirect(route('third-party-apis.edit', $api));

    expect($api->fresh()->name)->toBe('Renamed API');
});

it('deletes an api template', function () {
    $api = ThirdPartyApi::factory()->create();

    actingAs($this->user)
        ->delete(route('third-party-apis.destroy', $api))
        ->assertRedirect(route('third-party-apis.index'));

    expect(ThirdPartyApi::find($api->id))->toBeNull();
});
