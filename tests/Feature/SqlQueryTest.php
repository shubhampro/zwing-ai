<?php

use App\Models\SavedSqlQuery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

test('guests cannot access sql queries page', function () {
    $this->get(route('sql-queries.index'))
        ->assertRedirect('/login');
});

test('authenticated user can view sql queries page', function () {
    $user = User::factory()->create();
    SavedSqlQuery::factory()->for($user)->create([
        'title' => 'GRN check',
        'sql' => 'SELECT 1;',
    ]);

    $this->actingAs($user)
        ->get(route('sql-queries.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sql-queries/index')
            ->has('queries', 1)
            ->where('queries.0.title', 'GRN check')
            ->has('schemaTables')
        );
});

test('user can save a new sql query', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sql-queries.store'), [
            'title' => 'Pending GRNs',
            'description' => 'Find pending GRNs',
            'sql' => 'SELECT * FROM grn WHERE status = "pending";',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Query saved.');

    $this->assertDatabaseHas('saved_sql_queries', [
        'user_id' => $user->id,
        'title' => 'Pending GRNs',
        'description' => 'Find pending GRNs',
    ]);
});

test('user can update their own sql query', function () {
    $user = User::factory()->create();
    $query = SavedSqlQuery::factory()->for($user)->create([
        'title' => 'Old title',
        'sql' => 'SELECT 1;',
    ]);

    $this->actingAs($user)
        ->put(route('sql-queries.update', $query), [
            'title' => 'Updated title',
            'description' => 'Updated notes',
            'sql' => 'SELECT 2;',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Query updated.');

    expect($query->fresh())
        ->title->toBe('Updated title')
        ->description->toBe('Updated notes')
        ->sql->toBe('SELECT 2;');
});

test('user cannot update another users sql query', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $query = SavedSqlQuery::factory()->for($owner)->create();

    $this->actingAs($other)
        ->put(route('sql-queries.update', $query), [
            'title' => 'Hacked',
            'description' => null,
            'sql' => 'SELECT 1;',
        ])
        ->assertForbidden();
});

test('user can delete their own sql query', function () {
    $user = User::factory()->create();
    $query = SavedSqlQuery::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('sql-queries.destroy', $query))
        ->assertRedirect()
        ->assertSessionHas('success', 'Query deleted.');

    $this->assertModelMissing($query);
});

test('user can export their sql query as a file', function () {
    $user = User::factory()->create();
    $query = SavedSqlQuery::factory()->for($user)->create([
        'title' => 'GRN Export',
        'description' => 'Export test',
        'sql' => 'SELECT * FROM grn;',
    ]);

    $this->actingAs($user)
        ->get(route('sql-queries.export', $query))
        ->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename=grn-export.sql')
        ->assertSee('-- Export test')
        ->assertSee('SELECT * FROM grn;');
});

test('user can import sql from uploaded file', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent(
        'query.sql',
        "SELECT id FROM grt_headers WHERE status = 'open';",
    );

    $this->actingAs($user)
        ->postJson(route('sql-queries.import'), [
            'file' => $file,
        ])
        ->assertSuccessful()
        ->assertJson([
            'success' => true,
            'sql' => "SELECT id FROM grt_headers WHERE status = 'open';",
        ]);
});

test('store requires valid fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sql-queries.store'), [])
        ->assertSessionHasErrors(['title', 'sql']);
});
