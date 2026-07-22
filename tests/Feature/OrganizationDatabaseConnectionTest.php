<?php

use App\Enums\DatabaseConnectionType;
use App\Models\Organization;
use App\Models\OrganizationDatabaseConnection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('stores database_name and password encrypted and decrypts on read', function () {
    $databaseName = 'org_billing_prod';
    $password = 's3cret-pass';

    $connection = OrganizationDatabaseConnection::factory()->create([
        'database_name' => $databaseName,
        'password' => $password,
    ]);

    $raw = DB::table('organization_database_connections')
        ->where('id', $connection->id)
        ->first(['database_name', 'password']);

    expect($raw->database_name)->not->toBe($databaseName)
        ->and($raw->password)->not->toBe($password)
        ->and($connection->fresh()->database_name)->toBe($databaseName)
        ->and($connection->fresh()->password)->toBe($password);
});

it('casts type to DatabaseConnectionType enum', function () {
    $connection = OrganizationDatabaseConnection::factory()->pgsql()->create();

    expect($connection->fresh()->type)->toBe(DatabaseConnectionType::Pgsql);
});

it('allows one connection per organization and type', function () {
    $organization = Organization::factory()->create();

    OrganizationDatabaseConnection::factory()->pgsql()->create([
        'organization_id' => $organization->id,
    ]);

    expect(fn () => OrganizationDatabaseConnection::factory()->pgsql()->create([
        'organization_id' => $organization->id,
    ]))->toThrow(QueryException::class);
});

it('allows same type on different organizations', function () {
    OrganizationDatabaseConnection::factory()->pgsql()->create();
    OrganizationDatabaseConnection::factory()->pgsql()->create();

    expect(OrganizationDatabaseConnection::query()->count())->toBe(2);
});

it('allows mysql and pgsql on same organization', function () {
    $organization = Organization::factory()->create();

    OrganizationDatabaseConnection::factory()->mysql()->create([
        'organization_id' => $organization->id,
    ]);

    OrganizationDatabaseConnection::factory()->pgsql()->create([
        'organization_id' => $organization->id,
    ]);

    expect($organization->databaseConnections()->count())->toBe(2);
});

it('filters active connections of a given type', function () {
    $organization = Organization::factory()->create();

    OrganizationDatabaseConnection::factory()->pgsql()->create([
        'organization_id' => $organization->id,
    ]);

    OrganizationDatabaseConnection::factory()->mysql()->inactive()->create([
        'organization_id' => $organization->id,
    ]);

    $activePgsql = $organization->databaseConnections()
        ->active()
        ->ofType(DatabaseConnectionType::Pgsql)
        ->get();

    expect($activePgsql)->toHaveCount(1)
        ->and($activePgsql->first()->type)->toBe(DatabaseConnectionType::Pgsql);
});
