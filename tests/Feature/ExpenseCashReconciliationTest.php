<?php

use App\Jobs\ParseExpenseCashReconciliationCsv;
use App\Models\ExpenseCashReconSession;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page from index', function () {
    $this->get(route('expense-cash-reconciliation.index'))
        ->assertRedirect(route('login'));
});

test('guests are redirected to the login page from create', function () {
    $this->get(route('expense-cash-reconciliation.create'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the sessions list', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('expense-cash-reconciliation.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('expense-cash-reconciliation/index')
            ->has('sessions'));
});

test('index only shows the authenticated users own sessions', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    ExpenseCashReconSession::create([
        'user_id' => $other->id,
        'name' => 'other-session',
        'v_id' => 1,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->get(route('expense-cash-reconciliation.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('expense-cash-reconciliation/index')
            ->has('sessions', 0));
});

test('authenticated users can visit the new reconciliation page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('expense-cash-reconciliation.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('expense-cash-reconciliation/create'));
});

test('authenticated users can view a session show page', function () {
    $user = User::factory()->create();
    $session = ExpenseCashReconSession::create([
        'user_id' => $user->id,
        'name' => 'test-session',
        'v_id' => 1,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->get(route('expense-cash-reconciliation.show', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('expense-cash-reconciliation/show')
            ->has('session'));
});

test('users cannot view another users session', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $session = ExpenseCashReconSession::create([
        'user_id' => $other->id,
        'name' => 'other-user-session',
        'v_id' => 1,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->get(route('expense-cash-reconciliation.show', $session))
        ->assertForbidden();
});

test('guests cannot upload an expense cash reconciliation csv', function () {
    $file = UploadedFile::fake()->createWithContent('expense.csv', "a\nb\n");

    $this->post(route('expense-cash-reconciliation.csv'), [
        'zwing_csv' => $file,
    ])
        ->assertRedirect(route('login'));
});

test('csv upload creates a session and dispatches a job', function () {
    Queue::fake();
    Storage::fake('local');

    $user = User::factory()->create();
    $csvContent = "site_id,doc_no,date,amount,status\n101,EXP-001,2026-05-01,1500.00,paid\n";
    $zwingFile = UploadedFile::fake()->createWithContent('zwing.csv', $csvContent);
    $erpFile = UploadedFile::fake()->createWithContent('erp.csv', $csvContent);

    $this->actingAs($user)
        ->post(route('expense-cash-reconciliation.csv'), [
            'name' => 'May 2026 expense check',
            'v_id' => 147,
            'zwing_csv' => $zwingFile,
            'erp_csv' => $erpFile,
        ])
        ->assertRedirect();

    $session = ExpenseCashReconSession::where('user_id', $user->id)->firstOrFail();
    expect($session->name)->toBe('May 2026 expense check');
    expect($session->v_id)->toBe(147);
    expect($session->zwing_file_name)->toBe('zwing.csv');
    expect($session->status)->toBe('pending');

    Queue::assertPushed(ParseExpenseCashReconciliationCsv::class, fn ($job) => $job->sessionId === $session->id);
});

test('csv upload rejects a file missing required columns', function () {
    $user = User::factory()->create();
    $badFile = UploadedFile::fake()->createWithContent('bad.csv', "site_id,doc_no,amount,status\n101,EXP-1,1500.00,paid\n");
    $goodFile = UploadedFile::fake()->createWithContent(
        'good.csv',
        "site_id,doc_no,date,amount,status\n101,EXP-001,2026-05-01,1500.00,paid\n",
    );

    $this->actingAs($user)
        ->post(route('expense-cash-reconciliation.csv'), [
            'name' => 'Bad session',
            'v_id' => 1,
            'zwing_csv' => $badFile,
            'erp_csv' => $goodFile,
        ])
        ->assertSessionHasErrors('zwing_csv');
});

test('csv upload requires name and v_id', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent('expense.csv', "a\nb\n");

    $this->actingAs($user)
        ->post(route('expense-cash-reconciliation.csv'), [
            'zwing_csv' => $file,
        ])
        ->assertSessionHasErrors(['name', 'v_id']);
});

test('csv upload name must be unique', function () {
    Queue::fake();
    Storage::fake('local');

    $user = User::factory()->create();
    ExpenseCashReconSession::create(['user_id' => $user->id, 'name' => 'duplicate', 'v_id' => 1, 'status' => 'pending']);

    $file = UploadedFile::fake()->createWithContent('expense.csv', "a\nb\n");

    $this->actingAs($user)
        ->post(route('expense-cash-reconciliation.csv'), [
            'name' => 'duplicate',
            'v_id' => 1,
            'zwing_csv' => $file,
            'erp_csv' => $file,
        ])
        ->assertSessionHasErrors('name');
});

test('authenticated users can delete their own session', function () {
    $user = User::factory()->create();
    $session = ExpenseCashReconSession::create([
        'user_id' => $user->id,
        'name' => 'to-delete',
        'v_id' => 1,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->delete(route('expense-cash-reconciliation.destroy', $session))
        ->assertRedirect(route('expense-cash-reconciliation.index'));

    expect(ExpenseCashReconSession::find($session->id))->toBeNull();
});

test('users cannot delete another users session', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $session = ExpenseCashReconSession::create([
        'user_id' => $other->id,
        'name' => 'not-mine',
        'v_id' => 1,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->delete(route('expense-cash-reconciliation.destroy', $session))
        ->assertForbidden();

    expect(ExpenseCashReconSession::find($session->id))->not->toBeNull();
});

test('csv upload requires both files', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('expense-cash-reconciliation.csv'), [
            'name' => 'Test session',
            'v_id' => 1,
        ])
        ->assertSessionHasErrors(['zwing_csv', 'erp_csv']);
});

test('report compares rows by site_id and doc_no', function () {
    $user = User::factory()->create();
    $session = ExpenseCashReconSession::create([
        'user_id' => $user->id,
        'name' => 'comparison-test',
        'v_id' => 1,
        'status' => 'completed',
    ]);

    $now = now()->toDateTimeString();

    DB::table('zwing_expense_cash_reconsile')->insert([
        ['session_id' => $session->id, 'v_id' => 1, 'site_id' => '101', 'doc_no' => 'EXP-001', 'txn_date' => '2026-05-01', 'amount' => 100, 'status' => 'paid', 'created_at' => $now, 'updated_at' => $now],
        ['session_id' => $session->id, 'v_id' => 1, 'site_id' => '101', 'doc_no' => 'EXP-002', 'txn_date' => '2026-05-02', 'amount' => 200, 'status' => 'paid', 'created_at' => $now, 'updated_at' => $now],
    ]);

    DB::table('erp_expense_cash_reconsile')->insert([
        ['session_id' => $session->id, 'v_id' => 1, 'site_id' => '101', 'doc_no' => 'EXP-001', 'txn_date' => '2026-05-01', 'amount' => 100, 'status' => 'paid', 'created_at' => $now, 'updated_at' => $now],
        ['session_id' => $session->id, 'v_id' => 1, 'site_id' => '101', 'doc_no' => 'EXP-003', 'txn_date' => '2026-05-03', 'amount' => 300, 'status' => 'paid', 'created_at' => $now, 'updated_at' => $now],
    ]);

    $this->actingAs($user)
        ->get(route('expense-cash-reconciliation.report', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('expense-cash-reconciliation/report')
            ->where('summary.matched', 1)
            ->where('summary.zwing_only', 1)
            ->where('summary.erp_only', 1));

    $this->actingAs($user)
        ->get(route('expense-cash-reconciliation.report', ['expenseCashReconSession' => $session, 'filter' => 'zwing_only']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('rows.0.doc_no', 'EXP-002')
            ->where('rows.0.match_status', 'zwing_only'));
});

test('report applies doc search, site search, status filters, and difference filters', function () {
    $user = User::factory()->create();
    $session = ExpenseCashReconSession::create([
        'user_id' => $user->id,
        'name' => 'filter-test',
        'v_id' => 1,
        'status' => 'completed',
    ]);

    $now = now()->toDateTimeString();

    DB::table('zwing_expense_cash_reconsile')->insert([
        ['session_id' => $session->id, 'v_id' => 1, 'site_id' => '101', 'doc_no' => 'EXP-001', 'txn_date' => '2026-05-01', 'amount' => 100, 'status' => 'Success', 'created_at' => $now, 'updated_at' => $now],
        ['session_id' => $session->id, 'v_id' => 1, 'site_id' => '102', 'doc_no' => 'EXP-002', 'txn_date' => '2026-05-02', 'amount' => 200, 'status' => 'Void', 'created_at' => $now, 'updated_at' => $now],
    ]);

    DB::table('erp_expense_cash_reconsile')->insert([
        ['session_id' => $session->id, 'v_id' => 1, 'site_id' => '101', 'doc_no' => 'EXP-001', 'txn_date' => '2026-05-01', 'amount' => 120, 'status' => 'Success', 'created_at' => $now, 'updated_at' => $now],
        ['session_id' => $session->id, 'v_id' => 1, 'site_id' => '102', 'doc_no' => 'EXP-002', 'txn_date' => '2026-05-02', 'amount' => 200, 'status' => 'Paid', 'created_at' => $now, 'updated_at' => $now],
    ]);

    $this->actingAs($user)
        ->get(route('expense-cash-reconciliation.report', [
            'expenseCashReconSession' => $session,
            'site_query' => '101',
            'doc_query' => '001',
            'zwing_status' => 'Success',
            'erp_status' => 'Success',
            'difference' => 'non_zero',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('expense-cash-reconciliation/report')
            ->has('rows', 1)
            ->where('rows.0.doc_no', 'EXP-001')
            ->where('rows.0.match_status', 'amount_mismatch')
            ->where('filters.doc_query', '001')
            ->where('filters.site_query', '101')
            ->where('filters.zwing_status', 'Success')
            ->where('filters.erp_status', 'Success')
            ->where('filters.difference', 'non_zero')
            ->where('statusOptions.zwing', ['Success', 'Void'])
            ->where('statusOptions.erp', ['Paid', 'Success']));
});
