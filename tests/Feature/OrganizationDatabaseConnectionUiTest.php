<?php

use App\Enums\DatabaseConnectionType;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\OrganizationDatabaseConnection;
use App\Models\User;
use App\Services\OrganizationDatabaseConnectionTester;
use App\Support\Permissions;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
});

it('shows database connections page only to admins', function () {
    $admin = User::factory()->admin()->create();
    $operator = User::factory()->operator()->create();

    OrganizationDatabaseConnection::factory()->pgsql()->create([
        'organization_id' => $this->organization->id,
        'database_name' => 'secret_db',
        'username' => 'db_user',
    ]);

    actingAs($admin)
        ->get(route('organizations.database-connections.index', $this->organization))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organizations/database-connections')
            ->has('databaseConnections', 1)
            ->where('databaseConnections.0.username', 'db_user')
            ->missing('databaseConnections.0.database_name')
            ->missing('databaseConnections.0.password'));

    actingAs($operator)
        ->get(route('organizations.database-connections.index', $this->organization))
        ->assertForbidden();
});

it('shows database connections link on organization page only for admins', function () {
    $admin = User::factory()->admin()->create();
    $operator = User::factory()->operator()->create();

    actingAs($admin)
        ->get(route('organizations.show', $this->organization))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canManageDatabaseConnections', true));

    actingAs($operator)
        ->get(route('organizations.show', $this->organization))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canManageDatabaseConnections', false)
            ->missing('databaseConnections'));
});

it('allows admins to create update and delete database connections', function () {
    $admin = User::factory()->admin()->create();

    actingAs($admin)
        ->post(route('organizations.database-connections.store', $this->organization), [
            'type' => DatabaseConnectionType::Pgsql->value,
            'database_name' => 'org_pg',
            'username' => 'pg_user',
            'password' => 'pg-secret',
            'host' => 'pgflex-erpdb-prod-02.postgres.database.azure.com',
            'port' => 5432,
            'is_active' => true,
        ])
        ->assertRedirect(route('organizations.database-connections.index', $this->organization));

    $connection = OrganizationDatabaseConnection::query()->firstOrFail();

    expect($connection->database_name)->toBe('org_pg')
        ->and($connection->password)->toBe('pg-secret')
        ->and($connection->type)->toBe(DatabaseConnectionType::Pgsql);

    actingAs($admin)
        ->put(route('organizations.database-connections.update', [$this->organization, $connection]), [
            'type' => DatabaseConnectionType::Pgsql->value,
            'database_name' => '',
            'username' => 'pg_user_2',
            'password' => '',
            'host' => 'pgflex-erpdb-prod-02.postgres.database.azure.com',
            'port' => 5432,
            'is_active' => false,
        ])
        ->assertRedirect(route('organizations.database-connections.index', $this->organization));

    $connection->refresh();

    expect($connection->database_name)->toBe('org_pg')
        ->and($connection->username)->toBe('pg_user_2')
        ->and($connection->password)->toBe('pg-secret')
        ->and($connection->host)->toBe('pgflex-erpdb-prod-02.postgres.database.azure.com')
        ->and($connection->is_active)->toBeFalse();

    actingAs($admin)
        ->delete(route('organizations.database-connections.destroy', [$this->organization, $connection]))
        ->assertRedirect(route('organizations.database-connections.index', $this->organization));

    assertDatabaseMissing('organization_database_connections', [
        'id' => $connection->id,
    ]);
});

it('forbids operators and viewers from mutating database connections', function (User $user) {
    $connection = OrganizationDatabaseConnection::factory()->pgsql()->create([
        'organization_id' => $this->organization->id,
    ]);

    actingAs($user)
        ->post(route('organizations.database-connections.store', $this->organization), [
            'type' => DatabaseConnectionType::Mysql->value,
            'database_name' => 'blocked',
            'username' => 'blocked',
            'password' => 'blocked',
        ])
        ->assertForbidden();

    actingAs($user)
        ->put(route('organizations.database-connections.update', [$this->organization, $connection]), [
            'type' => DatabaseConnectionType::Pgsql->value,
            'database_name' => 'blocked',
            'username' => 'blocked',
        ])
        ->assertForbidden();

    actingAs($user)
        ->delete(route('organizations.database-connections.destroy', [$this->organization, $connection]))
        ->assertForbidden();

    assertDatabaseHas('organization_database_connections', [
        'id' => $connection->id,
    ]);
})->with([
    fn () => User::factory()->operator()->create(),
    fn () => User::factory()->viewer()->create(),
]);

it('still forbids operators even if they somehow receive a manage permission', function () {
    $operator = User::factory()->operator()->create();
    $operator->givePermissionTo(Permissions::OrganizationsUpdate);

    expect($operator->hasRole(Role::Admin))->toBeFalse();

    actingAs($operator)
        ->post(route('organizations.database-connections.store', $this->organization), [
            'type' => DatabaseConnectionType::Pgsql->value,
            'database_name' => 'blocked',
            'username' => 'blocked',
            'password' => 'blocked',
        ])
        ->assertForbidden();
});

it('allows admins to test a database connection', function () {
    $admin = User::factory()->admin()->create();
    $connection = OrganizationDatabaseConnection::factory()->pgsql()->create([
        'organization_id' => $this->organization->id,
    ]);

    $this->mock(OrganizationDatabaseConnectionTester::class, function ($mock) use ($connection) {
        $mock->shouldReceive('test')
            ->once()
            ->with(Mockery::on(fn ($model) => $model->is($connection)))
            ->andReturn([
                'ok' => true,
                'message' => 'Connection successful for pgsql.',
                'latency_ms' => 12,
            ]);
    });

    actingAs($admin)
        ->post(route('organizations.database-connections.test', [$this->organization, $connection]))
        ->assertRedirect(route('organizations.database-connections.index', $this->organization));
});

it('forbids operators from testing database connections', function () {
    $operator = User::factory()->operator()->create();
    $connection = OrganizationDatabaseConnection::factory()->pgsql()->create([
        'organization_id' => $this->organization->id,
    ]);

    actingAs($operator)
        ->post(route('organizations.database-connections.test', [$this->organization, $connection]))
        ->assertForbidden();
});
