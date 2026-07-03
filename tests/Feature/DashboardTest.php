<?php

use App\Models\InvoiceReconSession;
use App\Models\StockReconSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('stockSummary', null)
            ->where('invoiceSummary', null)
            ->where('expenseSummary', null)
            ->has('platform')
            ->where('platform.organizations_count', 0)
            ->where('platform.third_party_apis_count', 0)
            ->where('platform.completed_batches_count', 0)
            ->where('platform.transaction_checker_runs_count', 0)
            ->where('latestBatch', null));
});

test('dashboard shows latest completed reconciliation summaries with percentages', function () {
    $user = User::factory()->create();
    $now = now()->toDateTimeString();

    $stockSession = StockReconSession::create([
        'user_id' => $user->id,
        'name' => 'stock-dash',
        'v_id' => 1,
        'status' => 'completed',
        'reconciled_at' => now(),
    ]);

    DB::table('zwing_stock_reconsile')->insert([
        ['session_id' => $stockSession->id, 'v_id' => 1, 'batch_no' => 'B1', 'barcode' => 'A', 'icode' => 'I1', 'stock_point_name' => 'S', 'site_code' => 'SC', 'sprefcode' => 1, 'qty' => 10, 'created_at' => $now, 'updated_at' => $now],
        ['session_id' => $stockSession->id, 'v_id' => 1, 'batch_no' => 'B2', 'barcode' => 'A', 'icode' => 'I2', 'stock_point_name' => 'S', 'site_code' => 'SC', 'sprefcode' => 1, 'qty' => 5, 'created_at' => $now, 'updated_at' => $now],
    ]);

    DB::table('erp_stock_reconsile')->insert([
        ['session_id' => $stockSession->id, 'v_id' => 1, 'batch_no' => 'B1', 'barcode' => 'A', 'icode' => 'I1', 'stock_point_name' => 'S', 'site_code' => 'SC', 'sprefcode' => 1, 'qty' => 10, 'created_at' => $now, 'updated_at' => $now],
    ]);

    $invoiceSession = InvoiceReconSession::create([
        'user_id' => $user->id,
        'name' => 'invoice-dash',
        'v_id' => 1,
        'status' => 'completed',
        'reconciled_at' => now(),
    ]);

    DB::table('zwing_invoice_reconsile')->insert([
        ['session_id' => $invoiceSession->id, 'v_id' => 1, 'invoice_id' => 'INV-1', 'ref_id' => '1', 'total_amount' => 100, 'status' => 'paid', 'created_at' => $now, 'updated_at' => $now],
        ['session_id' => $invoiceSession->id, 'v_id' => 1, 'invoice_id' => 'INV-2', 'ref_id' => '2', 'total_amount' => 200, 'status' => 'paid', 'created_at' => $now, 'updated_at' => $now],
    ]);

    DB::table('erp_invoice_reconsile')->insert([
        ['session_id' => $invoiceSession->id, 'v_id' => 1, 'invoice_id' => 'INV-1', 'ref_id' => '1', 'total_amount' => 100, 'status' => 'paid', 'created_at' => $now, 'updated_at' => $now],
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('stockSummary')
            ->where('stockSummary.matched_percent', 50.0)
            ->where('stockSummary.zwing_only_percent', 50.0)
            ->has('invoiceSummary')
            ->where('invoiceSummary.matched_percent', 50.0)
            ->where('invoiceSummary.zwing_only_percent', 50.0)
            ->has('expenseSummary')
            ->has('platform'));
});
