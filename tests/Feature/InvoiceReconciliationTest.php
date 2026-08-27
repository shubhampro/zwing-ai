<?php

use App\Jobs\ParseInvoiceReconciliationCsv;
use App\Models\InvoiceReconSession;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page from index', function () {
    $this->get(route('invoice-reconciliation.index'))
        ->assertRedirect(route('login'));
});

test('guests are redirected to the login page from create', function () {
    $this->get(route('invoice-reconciliation.create'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the sessions list', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('invoice-reconciliation.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('invoice-reconciliation/index')
            ->has('sessions'));
});

test('index only shows the authenticated users own sessions', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    InvoiceReconSession::create([
        'user_id' => $other->id,
        'name' => 'other-session',
        'v_id' => 1,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->get(route('invoice-reconciliation.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('invoice-reconciliation/index')
            ->has('sessions', 0));
});

test('authenticated users can visit the new reconciliation page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('invoice-reconciliation.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('invoice-reconciliation/create')
            ->has('organizations'));
});

test('authenticated users can view a session show page', function () {
    $user = User::factory()->create();
    $session = InvoiceReconSession::create([
        'user_id' => $user->id,
        'name' => 'test-session',
        'v_id' => 1,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->get(route('invoice-reconciliation.show', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('invoice-reconciliation/show')
            ->has('session'));
});

test('users cannot view another users session', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $session = InvoiceReconSession::create([
        'user_id' => $other->id,
        'name' => 'other-user-session',
        'v_id' => 1,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->get(route('invoice-reconciliation.show', $session))
        ->assertForbidden();
});

test('guests cannot upload an invoice reconciliation csv', function () {
    $file = UploadedFile::fake()->createWithContent('invoice.csv', "a\nb\n");

    $this->post(route('invoice-reconciliation.csv'), [
        'zwing_csv' => $file,
    ])
        ->assertRedirect(route('login'));
});

test('csv upload creates a session and dispatches a job', function () {
    Queue::fake();
    Storage::fake('local');

    $user = User::factory()->create();
    $csvContent = "invoice_id,ref_id,total_amount,status\nINV-001,100,1500.00,paid\n";
    $zwingFile = UploadedFile::fake()->createWithContent('zwing.csv', $csvContent);
    $erpFile = UploadedFile::fake()->createWithContent('erp.csv', $csvContent);

    $this->actingAs($user)
        ->post(route('invoice-reconciliation.csv'), [
            'name' => 'May 2026 invoice check',
            'v_id' => 147,
            'zwing_csv' => $zwingFile,
            'erp_csv' => $erpFile,
        ])
        ->assertRedirect();

    $session = InvoiceReconSession::where('user_id', $user->id)->firstOrFail();
    expect($session->name)->toBe('May 2026 invoice check');
    expect($session->v_id)->toBe(147);
    expect($session->zwing_file_name)->toBe('zwing.csv');
    expect($session->status)->toBe('pending');

    Queue::assertPushed(ParseInvoiceReconciliationCsv::class, fn ($job) => $job->sessionId === $session->id);
});

test('csv upload rejects a file missing required columns', function () {
    $user = User::factory()->create();
    $badFile = UploadedFile::fake()->createWithContent('bad.csv', "invoice_id,status\nINV-1,paid\n");
    $goodFile = UploadedFile::fake()->createWithContent(
        'good.csv',
        "invoice_id,ref_id,total_amount,status\nINV-001,100,1500.00,paid\n",
    );

    $this->actingAs($user)
        ->post(route('invoice-reconciliation.csv'), [
            'name' => 'Bad session',
            'v_id' => 1,
            'zwing_csv' => $badFile,
            'erp_csv' => $goodFile,
        ])
        ->assertSessionHasErrors('zwing_csv');
});

test('csv upload requires name and v_id', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent('invoice.csv', "a\nb\n");

    $this->actingAs($user)
        ->post(route('invoice-reconciliation.csv'), [
            'zwing_csv' => $file,
        ])
        ->assertSessionHasErrors(['name', 'v_id']);
});

test('csv upload name must be unique', function () {
    Queue::fake();
    Storage::fake('local');

    $user = User::factory()->create();
    InvoiceReconSession::create(['user_id' => $user->id, 'name' => 'duplicate', 'v_id' => 1, 'status' => 'pending']);

    $file = UploadedFile::fake()->createWithContent('invoice.csv', "a\nb\n");

    $this->actingAs($user)
        ->post(route('invoice-reconciliation.csv'), [
            'name' => 'duplicate',
            'v_id' => 1,
            'zwing_csv' => $file,
            'erp_csv' => $file,
        ])
        ->assertSessionHasErrors('name');
});

test('authenticated users can delete their own session', function () {
    $user = User::factory()->create();
    $session = InvoiceReconSession::create([
        'user_id' => $user->id,
        'name' => 'to-delete',
        'v_id' => 1,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->delete(route('invoice-reconciliation.destroy', $session))
        ->assertRedirect(route('invoice-reconciliation.index'));

    expect(InvoiceReconSession::find($session->id))->toBeNull();
});

test('users cannot delete another users session', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $session = InvoiceReconSession::create([
        'user_id' => $other->id,
        'name' => 'not-mine',
        'v_id' => 1,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->delete(route('invoice-reconciliation.destroy', $session))
        ->assertForbidden();

    expect(InvoiceReconSession::find($session->id))->not->toBeNull();
});

test('csv upload requires both files', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('invoice-reconciliation.csv'), [
            'name' => 'Test session',
            'v_id' => 1,
        ])
        ->assertSessionHasErrors(['zwing_csv', 'erp_csv']);
});

test('report compares aggregated mop ref ids per invoice', function () {
    $user = User::factory()->create();
    $session = InvoiceReconSession::create([
        'user_id' => $user->id,
        'name' => 'comparison-test',
        'v_id' => 1,
        'status' => 'completed',
    ]);

    $now = now()->toDateTimeString();

    DB::table('zwing_invoice_reconsile')->insert([
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_id' => 'INV-001', 'ref_id' => '1', 'total_amount' => 100, 'status' => 'paid', 'created_at' => $now, 'updated_at' => $now],
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_id' => 'INV-001', 'ref_id' => '2', 'total_amount' => 100, 'status' => 'paid', 'created_at' => $now, 'updated_at' => $now],
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_id' => 'INV-001', 'ref_id' => '3', 'total_amount' => 100, 'status' => 'paid', 'created_at' => $now, 'updated_at' => $now],
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_id' => 'INV-002', 'ref_id' => '4', 'total_amount' => 200, 'status' => 'paid', 'created_at' => $now, 'updated_at' => $now],
    ]);

    DB::table('erp_invoice_reconsile')->insert([
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_id' => 'INV-001', 'ref_id' => '2', 'total_amount' => 100, 'status' => 'paid', 'created_at' => $now, 'updated_at' => $now],
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_id' => 'ERP-003', 'ref_id' => '5', 'total_amount' => 300, 'status' => 'paid', 'created_at' => $now, 'updated_at' => $now],
    ]);

    $this->actingAs($user)
        ->get(route('invoice-reconciliation.report', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('invoice-reconciliation/report')
            ->where('summary.matched', 0)
            ->where('summary.zwing_only', 1)
            ->where('summary.erp_only', 1)
            ->where('summary.mop_ref_mismatch', 1)
            ->where('summary.mismatch', 1));

    $this->actingAs($user)
        ->get(route('invoice-reconciliation.report', ['invoiceReconSession' => $session, 'filter' => 'matched']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('rows', 0));

    $this->actingAs($user)
        ->get(route('invoice-reconciliation.report', ['invoiceReconSession' => $session, 'filter' => 'zwing_only']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('rows.0.zwing_invoice_id', 'INV-002')
            ->where('rows.0.zwing_ref_id', '4')
            ->where('rows.0.match_status', 'invoice_not_in_erp'));

    $this->actingAs($user)
        ->get(route('invoice-reconciliation.report', ['invoiceReconSession' => $session, 'filter' => 'mop_ref_mismatch']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('rows.0.invoice_id', 'INV-001')
            ->where('rows.0.zwing_ref_id', '1-2-3')
            ->where('rows.0.erp_ref_id', '2')
            ->where('rows.0.match_status', 'mop_ref_mismatch'));

    $this->actingAs($user)
        ->get(route('invoice-reconciliation.report', ['invoiceReconSession' => $session, 'filter' => 'mismatch']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.mismatch', 1)
            ->has('rows', 1)
            ->where('rows.0.match_status', 'mop_ref_mismatch'));
});

test('report shows mop ref mismatch when zwing has 22-21 and erp has only 22', function () {
    $user = User::factory()->create();
    $session = InvoiceReconSession::create([
        'user_id' => $user->id,
        'name' => 'partial-ref-test',
        'v_id' => 1,
        'status' => 'completed',
    ]);

    $now = now()->toDateTimeString();

    DB::table('zwing_invoice_reconsile')->insert([
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_id' => 'PMM3001252800002', 'ref_id' => '22-21', 'total_amount' => 55000, 'status' => 'Void', 'created_at' => $now, 'updated_at' => $now],
    ]);

    DB::table('erp_invoice_reconsile')->insert([
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_id' => 'PMM3001252800002', 'ref_id' => '22', 'total_amount' => 55000, 'status' => 'Void', 'created_at' => $now, 'updated_at' => $now],
    ]);

    $this->actingAs($user)
        ->get(route('invoice-reconciliation.report', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.matched', 0)
            ->where('summary.zwing_only', 0)
            ->where('summary.erp_only', 0)
            ->where('summary.mop_ref_mismatch', 1)
            ->where('summary.mismatch', 1));

    $this->actingAs($user)
        ->get(route('invoice-reconciliation.report', ['invoiceReconSession' => $session, 'filter' => 'mop_ref_mismatch']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('rows.0.zwing_ref_id', '21-22')
            ->where('rows.0.erp_ref_id', '22')
            ->where('rows.0.match_status', 'mop_ref_mismatch'));
});

test('report compares mop ref ids per invoice without cross-invoice matching', function () {
    $user = User::factory()->create();
    $session = InvoiceReconSession::create([
        'user_id' => $user->id,
        'name' => 'two-invoice-test',
        'v_id' => 1,
        'status' => 'completed',
    ]);

    $now = now()->toDateTimeString();

    DB::table('zwing_invoice_reconsile')->insert([
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_id' => 'PMM3001252600001', 'ref_id' => '22', 'total_amount' => 55000, 'status' => 'Success', 'created_at' => $now, 'updated_at' => $now],
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_id' => 'PMM3001252600002', 'ref_id' => '22-21', 'total_amount' => 55000, 'status' => 'Void', 'created_at' => $now, 'updated_at' => $now],
    ]);

    DB::table('erp_invoice_reconsile')->insert([
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_id' => 'PMM3001252600002', 'ref_id' => '22', 'total_amount' => 55000, 'status' => 'Void', 'created_at' => $now, 'updated_at' => $now],
    ]);

    $this->actingAs($user)
        ->get(route('invoice-reconciliation.report', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.matched', 0)
            ->where('summary.zwing_only', 1)
            ->where('summary.erp_only', 0)
            ->where('summary.mop_ref_mismatch', 1)
            ->where('summary.mismatch', 1));

    $this->actingAs($user)
        ->get(route('invoice-reconciliation.report', ['invoiceReconSession' => $session, 'filter' => 'zwing_only']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('rows.0.zwing_invoice_id', 'PMM3001252600001')
            ->where('rows.0.zwing_ref_id', '22')
            ->where('rows.0.match_status', 'invoice_not_in_erp'));

    $this->actingAs($user)
        ->get(route('invoice-reconciliation.report', ['invoiceReconSession' => $session, 'filter' => 'mop_ref_mismatch']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('rows.0.zwing_invoice_id', 'PMM3001252600002')
            ->where('rows.0.zwing_ref_id', '21-22')
            ->where('rows.0.erp_ref_id', '22')
            ->where('rows.0.match_status', 'mop_ref_mismatch'));
});

test('report applies invoice search, separate zwing and erp status filters, and difference filters', function () {
    $user = User::factory()->create();
    $session = InvoiceReconSession::create([
        'user_id' => $user->id,
        'name' => 'filter-test',
        'v_id' => 1,
        'status' => 'completed',
    ]);

    $now = now()->toDateTimeString();

    DB::table('zwing_invoice_reconsile')->insert([
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_id' => 'INV-SEARCH-001', 'ref_id' => '101', 'total_amount' => 100, 'status' => 'Success', 'created_at' => $now, 'updated_at' => $now],
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_id' => 'INV-SEARCH-002', 'ref_id' => '102', 'total_amount' => 200, 'status' => 'Void', 'created_at' => $now, 'updated_at' => $now],
    ]);

    DB::table('erp_invoice_reconsile')->insert([
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_id' => 'INV-SEARCH-001', 'ref_id' => '101', 'total_amount' => 120, 'status' => 'Success', 'created_at' => $now, 'updated_at' => $now],
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_id' => 'INV-SEARCH-002', 'ref_id' => '102', 'total_amount' => 200, 'status' => 'Paid', 'created_at' => $now, 'updated_at' => $now],
    ]);

    $this->actingAs($user)
        ->get(route('invoice-reconciliation.report', [
            'invoiceReconSession' => $session,
            'invoice_query' => '001',
            'zwing_status' => 'Success',
            'erp_status' => 'Success',
            'difference' => 'non_zero',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('invoice-reconciliation/report')
            ->has('rows', 1)
            ->where('rows.0.invoice_id', 'INV-SEARCH-001')
            ->where('rows.0.match_status', 'amount_mismatch')
            ->where('filters.invoice_query', '001')
            ->where('filters.zwing_status', 'Success')
            ->where('filters.erp_status', 'Success')
            ->where('filters.difference', 'non_zero')
            ->where('statusOptions.zwing', ['Success', 'Void'])
            ->where('statusOptions.erp', ['Paid', 'Success']));
});
