<?php

use App\Models\User;
use App\Services\RunRemoteSelectQuery;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected from query table page', function () {
    $this->get(route('query-table.index'))
        ->assertRedirect(route('login'));
});

test('guests cannot run remote query', function () {
    $this->postJson(route('query-table.run'), [
        'query' => 'SELECT 1',
        'bindings' => [],
    ])->assertUnauthorized();
});

test('authenticated users can view query table', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('query-table.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('query-table/index')
            ->has('savedQueries'));
});

test('run returns 422 when no active database context', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('query-table.run'), [
            'query' => 'SELECT 1 AS n',
            'bindings' => [],
        ])
        ->assertStatus(422)
        ->assertJsonFragment(['message' => 'No active remote database connection is selected.']);
});

test('run succeeds using mocked executor', function () {
    $user = User::factory()->create();

    $this->mock(RunRemoteSelectQuery::class, function ($mock): void {
        $mock->shouldReceive('__invoke')
            ->once()
            ->andReturn([
                'columns' => ['n'],
                'rows' => [['n' => 1]],
                'row_count' => 1,
                'truncated' => false,
            ]);
    });

    $this->actingAs($user)
        ->postJson(route('query-table.run'), [
            'query' => 'SELECT 1 AS n',
            'bindings' => [],
        ])
        ->assertOk()
        ->assertJsonPath('row_count', 1)
        ->assertJsonPath('columns', ['n']);
});

test('run validates binding keys', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('query-table.run'), [
            'query' => 'SELECT 1',
            'bindings' => ['bad-key' => 1],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['bindings']);
});
