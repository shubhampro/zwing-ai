<?php

use App\Jobs\ParseStockReconciliationCsv;
use App\Models\StockReconSession;
use App\Models\StockReconZwingLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page from index', function () {
    $this->get(route('stock-transaction-reconciliation.index'))
        ->assertRedirect(route('login'));
});

test('guests are redirected to the login page from create', function () {
    $this->get(route('stock-transaction-reconciliation.create'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the sessions list', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('stock-transaction-reconciliation.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('stock-transaction-reconciliation/index')
            ->has('sessions'));
});

test('index only shows the authenticated users own sessions', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    StockReconSession::create([
        'user_id' => $other->id,
        'name' => 'other-session',
        'v_id' => 1,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->get(route('stock-transaction-reconciliation.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('stock-transaction-reconciliation/index')
            ->has('sessions', 0));
});

test('authenticated users can visit the new reconciliation page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('stock-transaction-reconciliation.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('stock-transaction-reconciliation/create'));
});

test('authenticated users can view a session show page', function () {
    $user = User::factory()->create();
    $session = StockReconSession::create([
        'user_id' => $user->id,
        'name' => 'test-session',
        'v_id' => 1,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->get(route('stock-transaction-reconciliation.show', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('stock-transaction-reconciliation/show')
            ->has('session'));
});

test('users cannot view another users session', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $session = StockReconSession::create([
        'user_id' => $other->id,
        'name' => 'other-user-session',
        'v_id' => 1,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->get(route('stock-transaction-reconciliation.show', $session))
        ->assertForbidden();
});

test('guests cannot upload a stock reconciliation csv', function () {
    $file = UploadedFile::fake()->createWithContent('stock.csv', "a\nb\n");

    $this->post(route('stock-transaction-reconciliation.csv'), [
        'zwing_csv' => $file,
    ])
        ->assertRedirect(route('login'));
});

test('csv upload creates a session and dispatches a job', function () {
    Queue::fake();
    Storage::fake('local');

    $user = User::factory()->create();
    $csvContent = "batch_no,barcode,icode,site_code,sprefcode,stock_point_name,qty\nB1,A1,I1,SC1,SPR1,Store1,10\n";
    $zwingFile = UploadedFile::fake()->createWithContent('zwing.csv', $csvContent);
    $erpFile = UploadedFile::fake()->createWithContent('erp.csv', $csvContent);

    $this->actingAs($user)
        ->post(route('stock-transaction-reconciliation.csv'), [
            'name' => 'May 2026 stock check',
            'v_id' => 147,
            'zwing_csv' => $zwingFile,
            'erp_csv' => $erpFile,
        ])
        ->assertRedirect();

    $session = StockReconSession::where('user_id', $user->id)->firstOrFail();
    expect($session->name)->toBe('May 2026 stock check');
    expect($session->v_id)->toBe(147);
    expect($session->zwing_file_name)->toBe('zwing.csv');
    expect($session->status)->toBe('pending');

    Queue::assertPushed(ParseStockReconciliationCsv::class, fn ($job) => $job->sessionId === $session->id);
});

test('csv upload rejects a file missing required columns', function () {
    $user = User::factory()->create();
    $badFile = UploadedFile::fake()->createWithContent('bad.csv', "barcode,qty\nA1,10\n");
    $goodFile = UploadedFile::fake()->createWithContent(
        'good.csv',
        "batch_no,barcode,icode,site_code,sprefcode,stock_point_name,qty\nB1,A1,I1,SC1,SPR1,Store1,10\n",
    );

    $this->actingAs($user)
        ->post(route('stock-transaction-reconciliation.csv'), [
            'name' => 'Bad session',
            'v_id' => 1,
            'zwing_csv' => $badFile,
            'erp_csv' => $goodFile,
        ])
        ->assertSessionHasErrors('zwing_csv');
});

test('csv upload requires name and v_id', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent('stock.csv', "a\nb\n");

    $this->actingAs($user)
        ->post(route('stock-transaction-reconciliation.csv'), [
            'zwing_csv' => $file,
        ])
        ->assertSessionHasErrors(['name', 'v_id']);
});

test('csv upload name must be unique', function () {
    Queue::fake();
    Storage::fake('local');

    $user = User::factory()->create();
    StockReconSession::create(['user_id' => $user->id, 'name' => 'duplicate', 'v_id' => 1, 'status' => 'pending']);

    $file = UploadedFile::fake()->createWithContent('stock.csv', "a\nb\n");

    $this->actingAs($user)
        ->post(route('stock-transaction-reconciliation.csv'), [
            'name' => 'duplicate',
            'v_id' => 1,
            'zwing_csv' => $file,
            'erp_csv' => $file,
        ])
        ->assertSessionHasErrors('name');
});

test('authenticated users can delete their own session', function () {
    $user = User::factory()->create();
    $session = StockReconSession::create([
        'user_id' => $user->id,
        'name' => 'to-delete',
        'v_id' => 1,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->delete(route('stock-transaction-reconciliation.destroy', $session))
        ->assertRedirect(route('stock-transaction-reconciliation.index'));

    expect(StockReconSession::find($session->id))->toBeNull();
});

test('users cannot delete another users session', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $session = StockReconSession::create([
        'user_id' => $other->id,
        'name' => 'not-mine',
        'v_id' => 1,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->delete(route('stock-transaction-reconciliation.destroy', $session))
        ->assertForbidden();

    expect(StockReconSession::find($session->id))->not->toBeNull();
});

test('guests cannot delete a session', function () {
    $user = User::factory()->create();
    $session = StockReconSession::create([
        'user_id' => $user->id,
        'name' => 'guest-attempt',
        'v_id' => 1,
        'status' => 'pending',
    ]);

    $this->delete(route('stock-transaction-reconciliation.destroy', $session))
        ->assertRedirect(route('login'));
});

test('csv upload validates file type', function () {
    $user = User::factory()->create();
    $pdf = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');
    $csv = UploadedFile::fake()->createWithContent(
        'good.csv',
        "batch_no,barcode,icode,site_code,sprefcode,stock_point_name,qty\nB1,A1,I1,SC1,SPR1,Store1,10\n",
    );

    $this->actingAs($user)
        ->post(route('stock-transaction-reconciliation.csv'), [
            'name' => 'Test session',
            'v_id' => 1,
            'zwing_csv' => $pdf,
            'erp_csv' => $csv,
        ])
        ->assertSessionHasErrors('zwing_csv');
});

test('csv upload requires at least one stock file', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('stock-transaction-reconciliation.csv'), [
            'name' => 'Test session',
            'v_id' => 1,
        ])
        ->assertSessionHasErrors('zwing_csv');
});

test('csv upload accepts only zwing stock file', function () {
    Queue::fake();
    Storage::fake('local');

    $user = User::factory()->create();
    $csvContent = "batch_no,barcode,icode,site_code,sprefcode,stock_point_name,qty\nB1,A1,I1,SC1,SPR1,Store1,10\n";
    $zwingFile = UploadedFile::fake()->createWithContent('zwing.csv', $csvContent);

    $this->actingAs($user)
        ->post(route('stock-transaction-reconciliation.csv'), [
            'name' => 'Zwing only session',
            'v_id' => 147,
            'zwing_csv' => $zwingFile,
        ])
        ->assertRedirect();

    $session = StockReconSession::where('user_id', $user->id)->firstOrFail();
    expect($session->zwing_file_name)->toBe('zwing.csv');
    expect($session->erp_file_name)->toBeNull();

    Queue::assertPushed(ParseStockReconciliationCsv::class);
});

test('csv upload accepts optional log files', function () {
    Queue::fake();
    Storage::fake('local');

    $user = User::factory()->create();
    $stockCsv = "batch_no,barcode,icode,site_code,sprefcode,stock_point_name,qty\nB1,A1,I1,SC1,SPR1,Store1,10\n";
    $logCsv = "site_code,icode,batch_no,sprefcode,doc_no,enttype,qty\nSC1,I1,B1,SPR1,D1,GRN,10\n";

    $this->actingAs($user)
        ->post(route('stock-transaction-reconciliation.csv'), [
            'name' => 'With logs session',
            'v_id' => 147,
            'zwing_csv' => UploadedFile::fake()->createWithContent('zwing.csv', $stockCsv),
            'zwing_log_csv' => UploadedFile::fake()->createWithContent('zwing-logs.csv', $logCsv),
        ])
        ->assertRedirect();

    $session = StockReconSession::where('user_id', $user->id)->firstOrFail();
    expect($session->zwing_log_file_name)->toBe('zwing-logs.csv');
    expect($session->zwing_log_row_count)->toBe(1);

    Queue::assertPushed(ParseStockReconciliationCsv::class, fn ($job) => $job->zwingLogPath !== '');
});

test('csv upload rejects log file missing required columns', function () {
    $user = User::factory()->create();
    $stockCsv = "batch_no,barcode,icode,site_code,sprefcode,stock_point_name,qty\nB1,A1,I1,SC1,SPR1,Store1,10\n";
    $badLogCsv = "site_code,doc_no,qty\nSC1,D1,1\n";

    $this->actingAs($user)
        ->post(route('stock-transaction-reconciliation.csv'), [
            'name' => 'Bad log session',
            'v_id' => 1,
            'zwing_csv' => UploadedFile::fake()->createWithContent('zwing.csv', $stockCsv),
            'zwing_log_csv' => UploadedFile::fake()->createWithContent('bad-log.csv', $badLogCsv),
        ])
        ->assertSessionHasErrors('zwing_log_csv');
});

test('report applies icode search, stock point filter, and difference filters', function () {
    $user = User::factory()->create();
    $session = StockReconSession::create([
        'user_id' => $user->id,
        'name' => 'filter-test',
        'v_id' => 1,
        'status' => 'completed',
    ]);

    $now = now()->toDateTimeString();

    DB::table('zwing_stock_reconsile')->insert([
        ['session_id' => $session->id, 'v_id' => 1, 'batch_no' => 'B1', 'barcode' => 'A', 'icode' => 'ND-SEARCH-001', 'stock_point_name' => 'Packet', 'site_code' => '10', 'sprefcode' => 3, 'qty' => 10, 'created_at' => $now, 'updated_at' => $now],
        ['session_id' => $session->id, 'v_id' => 1, 'batch_no' => 'B2', 'barcode' => 'A', 'icode' => 'ND-SEARCH-002', 'stock_point_name' => 'Shelf', 'site_code' => '20', 'sprefcode' => 1, 'qty' => 20, 'created_at' => $now, 'updated_at' => $now],
    ]);

    DB::table('erp_stock_reconsile')->insert([
        ['session_id' => $session->id, 'v_id' => 1, 'batch_no' => 'B1', 'barcode' => 'A', 'icode' => 'ND-SEARCH-001', 'stock_point_name' => 'Packet', 'site_code' => '10', 'sprefcode' => 3, 'qty' => 12, 'created_at' => $now, 'updated_at' => $now],
        ['session_id' => $session->id, 'v_id' => 1, 'batch_no' => 'B2', 'barcode' => 'A', 'icode' => 'ND-SEARCH-002', 'stock_point_name' => 'SALE', 'site_code' => '20', 'sprefcode' => 1, 'qty' => 20, 'created_at' => $now, 'updated_at' => $now],
    ]);

    $this->actingAs($user)
        ->get(route('stock-transaction-reconciliation.report', [
            'stockReconSession' => $session,
            'icode_query' => '001',
            'site_code' => '10',
            'stock_point' => 'Packet',
            'difference' => 'non_zero',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('stock-transaction-reconciliation/report')
            ->has('rows', 1)
            ->where('rows.0.icode', 'ND-SEARCH-001')
            ->where('rows.0.site_code', '10')
            ->where('rows.0.match_status', 'qty_mismatch')
            ->where('filters.icode_query', '001')
            ->where('filters.site_code', '10')
            ->where('filters.stock_point', 'Packet')
            ->where('filters.difference', 'non_zero')
            ->where('siteCodeOptions', ['10', '20'])
            ->where('stockPointOptions', ['Packet', 'SALE', 'Shelf'])
            ->reloadOnly(['summary', 'rows', 'pagination', 'filter', 'filters'], function (Assert $reload) {
                $reload
                    ->has('rows', 1)
                    ->where('rows.0.icode', 'ND-SEARCH-001')
                    ->where('filters.icode_query', '001')
                    ->where('pagination.total', 1)
                    ->missing('siteCodeOptions');
            }));
});

test('report summary and match statuses cover matched mismatch and one-sided rows', function () {
    $user = User::factory()->create();
    $session = StockReconSession::create([
        'user_id' => $user->id,
        'name' => 'status-parity',
        'v_id' => 1,
        'status' => 'completed',
    ]);

    $now = now()->toDateTimeString();

    DB::table('zwing_stock_reconsile')->insert([
        ['session_id' => $session->id, 'v_id' => 1, 'batch_no' => 'B1', 'barcode' => 'A', 'icode' => 'MATCH-1', 'stock_point_name' => 'Store', 'site_code' => '10', 'sprefcode' => 1, 'qty' => 5, 'created_at' => $now, 'updated_at' => $now],
        ['session_id' => $session->id, 'v_id' => 1, 'batch_no' => 'B2', 'barcode' => 'A', 'icode' => 'MISMATCH-1', 'stock_point_name' => 'Store', 'site_code' => '10', 'sprefcode' => 2, 'qty' => 8, 'created_at' => $now, 'updated_at' => $now],
        ['session_id' => $session->id, 'v_id' => 1, 'batch_no' => 'B3', 'barcode' => 'A', 'icode' => 'ZWING-ONLY', 'stock_point_name' => 'Store', 'site_code' => '10', 'sprefcode' => 3, 'qty' => 3, 'created_at' => $now, 'updated_at' => $now],
    ]);

    DB::table('erp_stock_reconsile')->insert([
        ['session_id' => $session->id, 'v_id' => 1, 'batch_no' => 'B1', 'barcode' => 'A', 'icode' => 'MATCH-1', 'stock_point_name' => 'Store', 'site_code' => '10', 'sprefcode' => 1, 'qty' => 5, 'created_at' => $now, 'updated_at' => $now],
        ['session_id' => $session->id, 'v_id' => 1, 'batch_no' => 'B2', 'barcode' => 'A', 'icode' => 'MISMATCH-1', 'stock_point_name' => 'Store', 'site_code' => '10', 'sprefcode' => 2, 'qty' => 9, 'created_at' => $now, 'updated_at' => $now],
        ['session_id' => $session->id, 'v_id' => 1, 'batch_no' => 'B4', 'barcode' => 'A', 'icode' => 'ERP-ONLY', 'stock_point_name' => 'Store', 'site_code' => '10', 'sprefcode' => 4, 'qty' => 2, 'created_at' => $now, 'updated_at' => $now],
    ]);

    $this->actingAs($user)
        ->get(route('stock-transaction-reconciliation.report', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('stock-transaction-reconciliation/report')
            ->where('summary.total', 4)
            ->where('summary.matched', 1)
            ->where('summary.qty_mismatch', 1)
            ->where('summary.zwing_only', 1)
            ->where('summary.erp_only', 1)
            ->where('pagination.total', 4)
            ->where('pagination.per_page', 100)
            ->where('pagination.current_page', 1)
            ->where('pagination.last_page', 1)
            ->has('rows', 4)
            ->where('rows', fn ($rows) => collect($rows)->pluck('match_status')->sort()->values()->all() === [
                'erp_only',
                'matched',
                'qty_mismatch',
                'zwing_only',
            ]));
});

test('report pagination totals match filtered row counts', function () {
    $user = User::factory()->create();
    $session = StockReconSession::create([
        'user_id' => $user->id,
        'name' => 'pagination-parity',
        'v_id' => 1,
        'status' => 'completed',
    ]);

    $now = now()->toDateTimeString();
    $zwingRows = [];
    $erpRows = [];

    for ($i = 1; $i <= 3; $i++) {
        $zwingRows[] = [
            'session_id' => $session->id,
            'v_id' => 1,
            'batch_no' => "B{$i}",
            'barcode' => 'A',
            'icode' => sprintf('SKU-%03d', $i),
            'stock_point_name' => 'Store',
            'site_code' => '10',
            'sprefcode' => $i,
            'qty' => $i,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $erpRows[] = [
            'session_id' => $session->id,
            'v_id' => 1,
            'batch_no' => "B{$i}",
            'barcode' => 'A',
            'icode' => sprintf('SKU-%03d', $i),
            'stock_point_name' => 'Store',
            'site_code' => '10',
            'sprefcode' => $i,
            'qty' => $i === 2 ? 99 : $i,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    DB::table('zwing_stock_reconsile')->insert($zwingRows);
    DB::table('erp_stock_reconsile')->insert($erpRows);

    $this->actingAs($user)
        ->get(route('stock-transaction-reconciliation.report', [
            'stockReconSession' => $session,
            'filter' => 'qty_mismatch',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('stock-transaction-reconciliation/report')
            ->where('summary.total', 3)
            ->where('summary.qty_mismatch', 1)
            ->where('pagination.total', 1)
            ->has('rows', 1)
            ->where('rows.0.icode', 'SKU-002')
            ->where('rows.0.match_status', 'qty_mismatch'));
});

test('report export streams csv with expected header and status rows', function () {
    $user = User::factory()->create();
    $session = StockReconSession::create([
        'user_id' => $user->id,
        'name' => 'Export Session',
        'v_id' => 1,
        'status' => 'completed',
    ]);

    $now = now()->toDateTimeString();

    DB::table('zwing_stock_reconsile')->insert([
        'session_id' => $session->id,
        'v_id' => 1,
        'batch_no' => 'B1',
        'barcode' => 'A',
        'icode' => 'EXPORT-1',
        'stock_point_name' => 'Store',
        'site_code' => '10',
        'sprefcode' => 1,
        'qty' => 10,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('erp_stock_reconsile')->insert([
        'session_id' => $session->id,
        'v_id' => 1,
        'batch_no' => 'B1',
        'barcode' => 'A',
        'icode' => 'EXPORT-1',
        'stock_point_name' => 'Store',
        'site_code' => '10',
        'sprefcode' => 1,
        'qty' => 7,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $response = $this->actingAs($user)
        ->get(route('stock-transaction-reconciliation.report.export', [
            'stockReconSession' => $session,
            'difference' => 'non_zero',
        ]))
        ->assertOk();

    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();
    $lines = array_values(array_filter(preg_split("/\r\n|\n|\r/", $csv) ?: []));

    expect($lines[0])->toBe('site_code,icode,batch_no,sprefcode,stock_point_name,zwing_qty,erp_qty,difference,status');
    expect($lines[1])->toBe('10,EXPORT-1,B1,1,Store,10,7,3,qty_mismatch');
});

test('authenticated users can view zwing logs page', function () {
    $user = User::factory()->create();
    $session = StockReconSession::create([
        'user_id' => $user->id,
        'name' => 'logs-session',
        'v_id' => 1,
        'status' => 'completed',
    ]);

    $this->actingAs($user)
        ->get(route('stock-transaction-reconciliation.zwing-logs', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('stock-transaction-reconciliation/zwing-logs')
            ->has('rows'));
});

test('zwing logs page applies site code filter', function () {
    $user = User::factory()->create();
    $session = StockReconSession::create([
        'user_id' => $user->id,
        'name' => 'logs-filter-session',
        'v_id' => 1,
        'status' => 'completed',
    ]);

    StockReconZwingLog::create([
        'stock_recon_session_id' => $session->id,
        'v_id' => 1,
        'site_code' => '10',
        'icode' => 'SKU-A',
        'batch_no' => 'B1',
        'sprefcode' => 'SPR1',
        'doc_no' => 'D1',
        'enttype' => 'GRN',
        'qty' => 5,
    ]);

    StockReconZwingLog::create([
        'stock_recon_session_id' => $session->id,
        'v_id' => 1,
        'site_code' => '20',
        'icode' => 'SKU-B',
        'batch_no' => 'B2',
        'sprefcode' => 'SPR2',
        'doc_no' => 'D2',
        'enttype' => 'GRN',
        'qty' => 8,
    ]);

    $this->actingAs($user)
        ->get(route('stock-transaction-reconciliation.zwing-logs', [
            'stockReconSession' => $session,
            'site_code' => '10',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('stock-transaction-reconciliation/zwing-logs')
            ->has('rows', 1)
            ->where('rows.0.site_code', '10')
            ->where('site_code', '10')
            ->where('siteCodeOptions', ['10', '20']));
});
