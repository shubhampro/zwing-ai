<?php

use App\Models\SavedQuery;
use App\Models\User;

test('guests cannot save a query', function () {
    $this->postJson(route('query-table.saved-queries.store'), [
        'name' => 'Test',
        'sql' => 'SELECT 1',
        'bindings' => [],
    ])->assertUnauthorized();
});

test('user can save and list saved queries on query table', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('query-table.saved-queries.store'), [
            'name' => 'Stock join',
            'sql' => 'SELECT * FROM items WHERE id = :id',
            'bindings' => ['id' => 1],
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Stock join')
        ->assertJsonPath('data.sql', 'SELECT * FROM items WHERE id = :id');

    expect(SavedQuery::query()->count())->toBe(1);

    $this->actingAs($user)
        ->get(route('query-table.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('savedQueries', 1)
            ->where('savedQueries.0.name', 'Stock join'));
});

test('user can update their saved query', function () {
    $user = User::factory()->create();
    $saved = SavedQuery::factory()->for($user)->create([
        'name' => 'Original',
        'sql' => 'SELECT 1',
    ]);

    $this->actingAs($user)
        ->putJson(route('query-table.saved-queries.update', $saved), [
            'name' => 'Renamed',
            'sql' => 'SELECT 2',
            'bindings' => [],
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed');

    expect($saved->fresh()->name)->toBe('Renamed');
});

test('user cannot update another users saved query', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $saved = SavedQuery::factory()->for($owner)->create();

    $this->actingAs($other)
        ->putJson(route('query-table.saved-queries.update', $saved), [
            'name' => 'Hacked',
            'sql' => 'SELECT 3',
            'bindings' => [],
        ])
        ->assertNotFound();
});

test('user can delete their saved query', function () {
    $user = User::factory()->create();
    $saved = SavedQuery::factory()->for($user)->create();

    $this->actingAs($user)
        ->deleteJson(route('query-table.saved-queries.destroy', $saved))
        ->assertNoContent();

    expect(SavedQuery::query()->count())->toBe(0);
});

test('user cannot delete another users saved query', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $saved = SavedQuery::factory()->for($owner)->create();

    $this->actingAs($other)
        ->deleteJson(route('query-table.saved-queries.destroy', $saved))
        ->assertNotFound();

    expect(SavedQuery::query()->count())->toBe(1);
});
