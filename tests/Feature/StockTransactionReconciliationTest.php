<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('stock-transaction-reconciliation.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit stock transaction reconciliation', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('stock-transaction-reconciliation.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('stock-transaction-reconciliation/index'));
});
