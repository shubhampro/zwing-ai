<?php

use App\Enums\Role;
use App\Models\Invite;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('valid invite shows registration form', function () {
    $invite = Invite::factory()->create();

    $this->get(route('invites.register', $invite->token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/invite-register')
            ->where('token', $invite->token)
            ->where('email', null),
        );
});

test('used invite cannot be opened', function () {
    $invite = Invite::factory()->used()->create();

    $this->get(route('invites.register', $invite->token))->assertNotFound();
});

test('expired invite cannot be opened', function () {
    $invite = Invite::factory()->expired()->create();

    $this->get(route('invites.register', $invite->token))->assertNotFound();
});

test('user can register with a valid invite once', function () {
    $invite = Invite::factory()->create();

    $response = $this->post(route('invites.register.store', $invite->token), [
        'name' => 'Invited User',
        'email' => 'invited@example.com',
        'password' => strongPassword(),
        'password_confirmation' => strongPassword(),
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticated();

    $user = User::query()->where('email', 'invited@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->hasRole(Role::Operator))->toBeTrue();

    $invite->refresh();
    expect($invite->used_at)->not->toBeNull()
        ->and($invite->used_by)->toBe($user->id);

    $this->post(route('logout'));

    $this->post(route('invites.register.store', $invite->token), [
        'name' => 'Second User',
        'email' => 'second@example.com',
        'password' => strongPassword(),
        'password_confirmation' => strongPassword(),
    ])->assertNotFound();

    expect(User::query()->where('email', 'second@example.com')->exists())->toBeFalse();
});

test('weak passwords are rejected on invite registration', function () {
    $invite = Invite::factory()->create();

    $this->post(route('invites.register.store', $invite->token), [
        'name' => 'Weak User',
        'email' => 'weak@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('password');

    expect(User::query()->where('email', 'weak@example.com')->exists())->toBeFalse();
});

test('invite role is assigned on registration', function () {
    $invite = Invite::factory()->role(Role::Viewer)->create();

    $this->post(route('invites.register.store', $invite->token), [
        'name' => 'Viewer User',
        'email' => 'viewer.invite@example.com',
        'password' => strongPassword(),
        'password_confirmation' => strongPassword(),
    ])->assertRedirect('/dashboard');

    $user = User::query()->where('email', 'viewer.invite@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole(Role::Viewer))->toBeTrue()
        ->and($user->hasRole(Role::Operator))->toBeFalse();
});

test('email-locked invite rejects a different email', function () {
    $invite = Invite::factory()->forEmail('locked@example.com')->create();

    $this->post(route('invites.register.store', $invite->token), [
        'name' => 'Wrong Email',
        'email' => 'other@example.com',
        'password' => strongPassword(),
        'password_confirmation' => strongPassword(),
    ])->assertSessionHasErrors('email');

    expect($invite->fresh()->used_at)->toBeNull();
});

test('email-locked invite accepts the matching email', function () {
    $invite = Invite::factory()->forEmail('locked@example.com')->create();

    $this->post(route('invites.register.store', $invite->token), [
        'name' => 'Locked User',
        'email' => 'locked@example.com',
        'password' => strongPassword(),
        'password_confirmation' => strongPassword(),
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticated();
    expect($invite->fresh()->used_at)->not->toBeNull();
});
