<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shares queue status with authenticated users', function () {
    $user = User::factory()->operator()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('queueStatus')
            ->where('queueStatus.available', true)
            ->where('queueStatus.waiting', 0)
            ->where('queueStatus.processing', 0)
            ->has('queueStatus.queues.default')
            ->has('queueStatus.queues.external-query')
            ->where('queueStatus.queues.default.pending', 0)
            ->where('queueStatus.queues.external-query.pending', 0));
});

it('shares queue status with admins and operators alike', function () {
    $admin = User::factory()->admin()->create();
    $operator = User::factory()->operator()->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('queueStatus.queues.external-query'));

    $this->actingAs($operator)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('queueStatus.queues.external-query'));
});

it('does not share queue status with guests', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('queueStatus', null));
});
