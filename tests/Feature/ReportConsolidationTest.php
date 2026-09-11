<?php

use App\Models\ReportReconSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page from index', function () {
    $this->get(route('report-consolidation.index'))
        ->assertRedirect(route('login'));
});

test('guests are redirected to the login page from create', function () {
    $this->get(route('report-consolidation.create'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the sessions list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('report-consolidation.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('report-consolidation/index')
            ->has('sessions'));
});

test('index only shows the authenticated users own sessions', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    ReportReconSession::factory()->for($other)->create([
        'name' => 'other-session',
    ]);

    $this->actingAs($user)
        ->get(route('report-consolidation.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('report-consolidation/index')
            ->has('sessions', 0));
});

test('authenticated users can visit the new consolidation page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('report-consolidation.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('report-consolidation/create')
            ->has('organizations'));
});

test('authenticated users can view a session show page', function () {
    $user = User::factory()->create();
    $session = ReportReconSession::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('report-consolidation.show', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('report-consolidation/show')
            ->has('session')
            ->where('session.date_from', '2026-08-01')
            ->where('session.date_to', '2026-08-31'));
});

test('users cannot view another users session', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $session = ReportReconSession::factory()->for($other)->create();

    $this->actingAs($user)
        ->get(route('report-consolidation.show', $session))
        ->assertForbidden();
});

test('authenticated users can delete their own session', function () {
    $user = User::factory()->create();
    $session = ReportReconSession::factory()->for($user)->create([
        'name' => 'to-delete',
    ]);

    $this->actingAs($user)
        ->delete(route('report-consolidation.destroy', $session))
        ->assertRedirect(route('report-consolidation.index'));

    expect(ReportReconSession::find($session->id))->toBeNull();
});

test('users cannot delete another users session', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $session = ReportReconSession::factory()->for($other)->create();

    $this->actingAs($user)
        ->delete(route('report-consolidation.destroy', $session))
        ->assertForbidden();

    expect(ReportReconSession::find($session->id))->not->toBeNull();
});

test('report compares invoice totals against mop totals', function () {
    $user = User::factory()->create();
    $session = ReportReconSession::factory()->for($user)->completed()->create([
        'name' => 'comparison-test',
    ]);

    $now = now()->toDateTimeString();

    DB::table('report_recon_invoices')->insert([
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_no' => 'INV-001', 'store_name' => 'Phoenix Mall', 'date' => '2026-08-02', 'total' => 100, 'created_at' => $now, 'updated_at' => $now],
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_no' => 'INV-002', 'store_name' => 'Downtown', 'date' => '2026-08-03', 'total' => 200, 'created_at' => $now, 'updated_at' => $now],
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_no' => 'INV-003', 'store_name' => 'Airport', 'date' => '2026-08-04', 'total' => 50, 'created_at' => $now, 'updated_at' => $now],
    ]);

    DB::table('report_recon_mops')->insert([
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_no' => 'INV-001', 'store_name' => 'Phoenix Mall', 'date' => '2026-08-02', 'total' => 100, 'created_at' => $now, 'updated_at' => $now],
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_no' => 'INV-002', 'store_name' => 'Downtown', 'date' => '2026-08-03', 'total' => 180, 'created_at' => $now, 'updated_at' => $now],
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_no' => 'INV-004', 'store_name' => 'Harbor', 'date' => '2026-08-05', 'total' => 75, 'created_at' => $now, 'updated_at' => $now],
    ]);

    $this->actingAs($user)
        ->get(route('report-consolidation.report', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('report-consolidation/report')
            ->where('summary.total', 4)
            ->where('summary.matched', 1)
            ->where('summary.amount_mismatch', 1)
            ->where('summary.invoice_only', 1)
            ->where('summary.mop_only', 1)
            ->where('summary.mismatch', 1));

    $this->actingAs($user)
        ->get(route('report-consolidation.report', ['reportReconSession' => $session, 'filter' => 'matched']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('rows.0.invoice_no', 'INV-001')
            ->where('rows.0.store_name', 'Phoenix Mall')
            ->where('rows.0.match_status', 'matched'));

    $this->actingAs($user)
        ->get(route('report-consolidation.report', ['reportReconSession' => $session, 'filter' => 'amount_mismatch']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('rows.0.invoice_no', 'INV-002')
            ->where('rows.0.match_status', 'amount_mismatch'));

    $this->actingAs($user)
        ->get(route('report-consolidation.report', ['reportReconSession' => $session, 'filter' => 'invoice_only']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('rows.0.invoice_no', 'INV-003')
            ->where('rows.0.match_status', 'invoice_only'));

    $this->actingAs($user)
        ->get(route('report-consolidation.report', ['reportReconSession' => $session, 'filter' => 'mop_only']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('rows.0.invoice_no', 'INV-004')
            ->where('rows.0.store_name', 'Harbor')
            ->where('rows.0.match_status', 'mop_only'));
});

test('report applies invoice search and difference filters', function () {
    $user = User::factory()->create();
    $session = ReportReconSession::factory()->for($user)->completed()->create([
        'name' => 'filter-test',
    ]);

    $now = now()->toDateTimeString();

    DB::table('report_recon_invoices')->insert([
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_no' => 'INV-SEARCH-001', 'date' => '2026-08-02', 'total' => 100, 'created_at' => $now, 'updated_at' => $now],
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_no' => 'INV-SEARCH-002', 'date' => '2026-08-03', 'total' => 200, 'created_at' => $now, 'updated_at' => $now],
    ]);

    DB::table('report_recon_mops')->insert([
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_no' => 'INV-SEARCH-001', 'date' => '2026-08-02', 'total' => 120, 'created_at' => $now, 'updated_at' => $now],
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_no' => 'INV-SEARCH-002', 'date' => '2026-08-03', 'total' => 200, 'created_at' => $now, 'updated_at' => $now],
    ]);

    $this->actingAs($user)
        ->get(route('report-consolidation.report', [
            'reportReconSession' => $session,
            'invoice_query' => '001',
            'difference' => 'non_zero',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('report-consolidation/report')
            ->has('rows', 1)
            ->where('rows.0.invoice_no', 'INV-SEARCH-001')
            ->where('rows.0.match_status', 'amount_mismatch')
            ->where('filters.invoice_query', '001')
            ->where('filters.difference', 'non_zero'));
});

test('report filters by store name', function () {
    $user = User::factory()->create();
    $session = ReportReconSession::factory()->for($user)->completed()->create([
        'name' => 'store-filter-test',
    ]);

    $now = now()->toDateTimeString();

    DB::table('report_recon_invoices')->insert([
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_no' => 'INV-001', 'store_name' => 'Phoenix Mall', 'date' => '2026-08-02', 'total' => 100, 'created_at' => $now, 'updated_at' => $now],
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_no' => 'INV-002', 'store_name' => 'Downtown', 'date' => '2026-08-03', 'total' => 200, 'created_at' => $now, 'updated_at' => $now],
    ]);

    DB::table('report_recon_mops')->insert([
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_no' => 'INV-001', 'store_name' => 'Phoenix Mall', 'date' => '2026-08-02', 'total' => 100, 'created_at' => $now, 'updated_at' => $now],
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_no' => 'INV-003', 'store_name' => 'Harbor', 'date' => '2026-08-05', 'total' => 75, 'created_at' => $now, 'updated_at' => $now],
    ]);

    $this->actingAs($user)
        ->get(route('report-consolidation.report', [
            'reportReconSession' => $session,
            'store' => 'Phoenix Mall',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('report-consolidation/report')
            ->has('rows', 1)
            ->where('rows.0.invoice_no', 'INV-001')
            ->where('rows.0.store_name', 'Phoenix Mall')
            ->where('filters.store', 'Phoenix Mall')
            ->where('stores', ['Downtown', 'Harbor', 'Phoenix Mall']));
});

test('export report respects store filter', function () {
    $user = User::factory()->create();
    $session = ReportReconSession::factory()->for($user)->completed()->create([
        'name' => 'store-export-test',
    ]);

    $now = now()->toDateTimeString();

    DB::table('report_recon_invoices')->insert([
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_no' => 'INV-001', 'store_name' => 'Phoenix Mall', 'date' => '2026-08-02', 'total' => 100, 'created_at' => $now, 'updated_at' => $now],
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_no' => 'INV-002', 'store_name' => 'Downtown', 'date' => '2026-08-03', 'total' => 200, 'created_at' => $now, 'updated_at' => $now],
    ]);

    DB::table('report_recon_mops')->insert([
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_no' => 'INV-001', 'store_name' => 'Phoenix Mall', 'date' => '2026-08-02', 'total' => 100, 'created_at' => $now, 'updated_at' => $now],
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_no' => 'INV-002', 'store_name' => 'Downtown', 'date' => '2026-08-03', 'total' => 200, 'created_at' => $now, 'updated_at' => $now],
    ]);

    $content = $this->actingAs($user)
        ->get(route('report-consolidation.report.export', [
            'reportReconSession' => $session,
            'store' => 'Downtown',
        ]))
        ->assertOk()
        ->streamedContent();

    expect($content)
        ->toContain('INV-002')
        ->toContain('Downtown')
        ->not->toContain('INV-001')
        ->not->toContain('Phoenix Mall');
});

test('report search matches store name', function () {
    $user = User::factory()->create();
    $session = ReportReconSession::factory()->for($user)->completed()->create([
        'name' => 'store-search-test',
    ]);

    $now = now()->toDateTimeString();

    DB::table('report_recon_invoices')->insert([
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_no' => 'INV-001', 'store_name' => 'Phoenix Mall', 'date' => '2026-08-02', 'total' => 100, 'created_at' => $now, 'updated_at' => $now],
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_no' => 'INV-002', 'store_name' => 'Downtown', 'date' => '2026-08-03', 'total' => 200, 'created_at' => $now, 'updated_at' => $now],
    ]);

    DB::table('report_recon_mops')->insert([
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_no' => 'INV-001', 'date' => '2026-08-02', 'total' => 100, 'created_at' => $now, 'updated_at' => $now],
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_no' => 'INV-002', 'date' => '2026-08-03', 'total' => 200, 'created_at' => $now, 'updated_at' => $now],
    ]);

    $this->actingAs($user)
        ->get(route('report-consolidation.report', [
            'reportReconSession' => $session,
            'invoice_query' => 'phoenix',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('rows.0.invoice_no', 'INV-001')
            ->where('rows.0.store_name', 'Phoenix Mall'));
});

test('export report streams a csv', function () {
    $user = User::factory()->create();
    $session = ReportReconSession::factory()->for($user)->completed()->create([
        'name' => 'export-test',
    ]);

    $now = now()->toDateTimeString();

    DB::table('report_recon_invoices')->insert([
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_no' => 'INV-001', 'store_name' => 'Phoenix Mall', 'date' => '2026-08-02', 'total' => 100, 'created_at' => $now, 'updated_at' => $now],
    ]);

    DB::table('report_recon_mops')->insert([
        ['session_id' => $session->id, 'v_id' => 1, 'invoice_no' => 'INV-001', 'date' => '2026-08-02', 'total' => 100, 'created_at' => $now, 'updated_at' => $now],
    ]);

    $response = $this->actingAs($user)
        ->get(route('report-consolidation.report.export', $session));

    $response->assertOk();

    $content = $response->streamedContent();

    expect($content)
        ->toContain('invoice_no')
        ->toContain('store_name')
        ->toContain('Phoenix Mall')
        ->toContain('INV-001')
        ->toContain('matched');
});
