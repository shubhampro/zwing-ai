<?php

use App\Enums\Role;
use App\Models\Invite;
use Spatie\Permission\PermissionRegistrar;

test('artisan command creates a single-use invite link', function () {
    $this->artisan('user:invite')
        ->expectsOutputToContain('Single-use invite created.')
        ->expectsOutputToContain('Role: operator')
        ->assertSuccessful();

    $invite = Invite::query()->first();

    expect($invite)->not->toBeNull()
        ->and($invite->email)->toBeNull()
        ->and($invite->role)->toBe(Role::Operator->value)
        ->and($invite->used_at)->toBeNull()
        ->and(strlen($invite->token))->toBe(64);
});

test('artisan command accepts a role option', function () {
    $this->artisan('user:invite', ['--role' => 'viewer'])
        ->expectsOutputToContain('Role: viewer')
        ->assertSuccessful();

    expect(Invite::query()->first()->role)->toBe(Role::Viewer->value);
});

test('artisan command can lock invite to an email and expiry', function () {
    $this->artisan('user:invite', [
        'email' => 'new.user@example.com',
        '--days' => '7',
    ])
        ->expectsOutputToContain('Email locked to: new.user@example.com')
        ->expectsOutputToContain('/invite/')
        ->assertSuccessful();

    $invite = Invite::query()->first();

    expect($invite->email)->toBe('new.user@example.com')
        ->and($invite->expires_at)->not->toBeNull()
        ->and($invite->expires_at->isFuture())->toBeTrue();
});

test('artisan command rejects invalid email', function () {
    $this->artisan('user:invite', ['email' => 'not-an-email'])
        ->expectsOutputToContain('Invalid email address')
        ->assertFailed();
});

test('artisan command tells you to seed when no roles exist', function () {
    Spatie\Permission\Models\Role::query()->delete();
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $this->artisan('user:invite', ['--role' => 'admin'])
        ->expectsOutputToContain('No roles found in the database. Run: php artisan db:seed --class=RolePermissionSeeder')
        ->assertFailed();
});
