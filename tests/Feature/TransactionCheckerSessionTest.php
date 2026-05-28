<?php

use App\Models\Organization;
use App\Models\TransactionCheckerSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('transaction checker session stores summary on check', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create(['vendor_id' => 999]);

    $session = TransactionCheckerSession::factory()->create([
        'user_id' => $user->id,
        'org_id' => $org->id,
        'connection' => 'mysql_ssh',
        'transaction_type' => 'grn',
        'database' => 'zw_mn_999_test',
        'summary' => ['total' => 10, 'matched' => 8, 'mismatch' => 2, 'missing_details' => 0],
    ]);

    expect($session->summary['total'])->toBe(10)
        ->and($session->summary['mismatch'])->toBe(2)
        ->and($session->connection)->toBe('mysql_ssh')
        ->and($session->transaction_type)->toBe('grn');
});

test('transaction checker session belongs to user and org', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();

    $session = TransactionCheckerSession::factory()->create([
        'user_id' => $user->id,
        'org_id' => $org->id,
    ]);

    expect($session->user->id)->toBe($user->id)
        ->and($session->organization->id)->toBe($org->id);
});

test('transaction checker index page loads for authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/transaction-checker')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('transaction-checker/index')
            ->has('connections')
            ->has('transactionTypes')
            ->has('organizations')
        );
});

test('transaction checker index redirects unauthenticated user', function () {
    $this->get('/transaction-checker')->assertRedirect('/login');
});
