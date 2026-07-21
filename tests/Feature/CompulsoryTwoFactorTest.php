<?php

use App\Http\Middleware\EnsureTwoFactorIsEnabled;
use App\Http\Middleware\PreventTwoFactorDisable;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    $this->withMiddleware([
        EnsureTwoFactorIsEnabled::class,
        PreventTwoFactorDisable::class,
    ]);
});

test('users without two factor are redirected to security settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('security.edit'))
        ->assertSessionHas('two_factor_enforcement_notice', true);
});

test('users without two factor can view security settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession([
            'auth.password_confirmed_at' => time(),
            'two_factor_enforcement_notice' => true,
        ])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/security')
            ->where('twoFactorRequired', true)
            ->where('twoFactorEnabled', false)
            ->where('twoFactorSetupMessage', 'Two-factor authentication is required. Enable 2FA below before you can use the rest of the app.')
            ->hasFlash('toast.type', 'warning')
            ->hasFlash('toast.message', 'Two-factor authentication is required. Enable 2FA below before you can use the rest of the app.'),
        );
});

test('users with two factor can access the dashboard', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('users cannot disable two factor authentication', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('two-factor.disable'))
        ->assertForbidden();

    expect($user->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();
});
