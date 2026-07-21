<?php

use App\Models\User;

test('artisan command resets two factor for a user by email', function () {
    $user = User::factory()->withTwoFactor()->create([
        'email' => 'locked-out@example.com',
    ]);

    $this->artisan('user:reset-two-factor', ['email' => 'locked-out@example.com'])
        ->expectsOutputToContain('Two-factor authentication reset for [locked-out@example.com]')
        ->assertSuccessful();

    $user->refresh();

    expect($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull()
        ->and($user->hasEnabledTwoFactorAuthentication())->toBeFalse();
});

test('artisan command fails when user email is unknown', function () {
    $this->artisan('user:reset-two-factor', ['email' => 'missing@example.com'])
        ->expectsOutputToContain('No user found with email [missing@example.com]')
        ->assertFailed();
});

test('artisan command is a no-op when user has no two factor', function () {
    User::factory()->create([
        'email' => 'plain@example.com',
    ]);

    $this->artisan('user:reset-two-factor', ['email' => 'plain@example.com'])
        ->expectsOutputToContain('has no two-factor authentication to reset')
        ->assertSuccessful();
});
