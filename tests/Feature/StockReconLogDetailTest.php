<?php

use App\Models\StockReconErpLog;
use App\Models\StockReconSession;
use App\Models\StockReconZwingLog;
use App\Models\User;
use App\Services\StockReconLogDetailService;

test('log detail service matches sprefcode by numeric suffix', function () {
    $user = User::factory()->create();
    $session = StockReconSession::create([
        'user_id' => $user->id,
        'name' => 'log-detail-sprefcode',
        'v_id' => 100,
        'zwing_log_file_name' => 'zwing-logs.csv',
        'erp_log_file_name' => 'erp-logs.csv',
        'status' => 'completed',
    ]);

    StockReconZwingLog::create([
        'stock_recon_session_id' => $session->id,
        'v_id' => 100,
        'site_code' => '11',
        'icode' => 'SKU1',
        'batch_no' => '',
        'sprefcode' => '2',
        'doc_no' => 'DOC-A',
        'enttype' => 'IN',
        'qty' => 5,
    ]);

    StockReconErpLog::create([
        'stock_recon_session_id' => $session->id,
        'v_id' => 100,
        'site_code' => '11',
        'icode' => 'SKU1',
        'batch_no' => '',
        'sprefcode' => 'NDPL010-2',
        'doc_no' => 'DOC-B',
        'enttype' => 'STI',
        'qty' => 5,
    ]);

    $result = app(StockReconLogDetailService::class)->forSku(
        session: $session,
        siteCode: '11',
        icode: 'SKU1',
        batchNo: '',
        sprefcode: '2',
    );

    expect($result['mismatch']['zwing'])->toHaveCount(1);
    expect($result['mismatch']['erp'])->toHaveCount(1);
    expect($result['matched']['zwing'])->toBeEmpty();
});

test('log detail service splits matched and mismatch by doc_no and qty', function () {
    $user = User::factory()->create();
    $session = StockReconSession::create([
        'user_id' => $user->id,
        'name' => 'log-detail-service',
        'v_id' => 100,
        'zwing_log_file_name' => 'zwing-logs.csv',
        'erp_log_file_name' => 'erp-logs.csv',
        'status' => 'completed',
    ]);

    StockReconZwingLog::create([
        'stock_recon_session_id' => $session->id,
        'v_id' => 100,
        'site_code' => 'SC1',
        'icode' => 'SKU1',
        'batch_no' => 'B1',
        'sprefcode' => '1',
        'doc_no' => 'D1',
        'enttype' => 'GRN',
        'qty' => 5,
    ]);

    StockReconZwingLog::create([
        'stock_recon_session_id' => $session->id,
        'v_id' => 100,
        'site_code' => 'SC1',
        'icode' => 'SKU1',
        'batch_no' => 'B1',
        'sprefcode' => '1',
        'doc_no' => 'D2',
        'enttype' => 'GRT',
        'qty' => 3,
    ]);

    StockReconErpLog::create([
        'stock_recon_session_id' => $session->id,
        'v_id' => 100,
        'site_code' => 'SC1',
        'icode' => 'SKU1',
        'batch_no' => 'B1',
        'sprefcode' => '1',
        'doc_no' => 'D1',
        'enttype' => 'GRN',
        'qty' => 5,
    ]);

    StockReconErpLog::create([
        'stock_recon_session_id' => $session->id,
        'v_id' => 100,
        'site_code' => 'SC1',
        'icode' => 'SKU1',
        'batch_no' => 'B1',
        'sprefcode' => '1',
        'doc_no' => 'D3',
        'enttype' => 'SST',
        'qty' => 2,
    ]);

    $result = app(StockReconLogDetailService::class)->forSku(
        session: $session,
        siteCode: 'SC1',
        icode: 'SKU1',
        batchNo: 'B1',
        sprefcode: '1',
    );

    expect($result['matched']['zwing'])->toHaveCount(1);
    expect($result['matched']['zwing'][0]['doc_no'])->toBe('D1');
    expect($result['matched']['erp'])->toHaveCount(1);
    expect($result['mismatch']['zwing'])->toHaveCount(1);
    expect($result['mismatch']['zwing'][0]['doc_no'])->toBe('D2');
    expect($result['mismatch']['erp'])->toHaveCount(1);
    expect($result['mismatch']['erp'][0]['doc_no'])->toBe('D3');
});

test('authenticated user can fetch report log details json', function () {
    $user = User::factory()->create();
    $session = StockReconSession::create([
        'user_id' => $user->id,
        'name' => 'log-detail-api',
        'v_id' => 100,
        'zwing_log_file_name' => 'zwing-logs.csv',
        'status' => 'completed',
    ]);

    StockReconZwingLog::create([
        'stock_recon_session_id' => $session->id,
        'v_id' => 100,
        'site_code' => 'SC1',
        'icode' => 'SKU1',
        'batch_no' => 'B1',
        'sprefcode' => '1',
        'doc_no' => 'D1',
        'enttype' => 'GRN',
        'qty' => 10,
    ]);

    $this->actingAs($user)
        ->getJson(route('stock-transaction-reconciliation.report.log-details', $session).'?'.http_build_query([
            'site_code' => 'SC1',
            'icode' => 'SKU1',
            'batch_no' => 'B1',
            'sprefcode' => '1',
        ]))
        ->assertOk()
        ->assertJsonPath('has_zwing_logs', true)
        ->assertJsonPath('mismatch.zwing.0.doc_no', 'D1')
        ->assertJsonPath('zwing_query_ms', null)
        ->assertJsonPath('erp_query_ms', null);
});

test('users cannot fetch log details for another users session', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $session = StockReconSession::create([
        'user_id' => $other->id,
        'name' => 'other-session',
        'v_id' => 1,
        'status' => 'completed',
    ]);

    $this->actingAs($user)
        ->getJson(route('stock-transaction-reconciliation.report.log-details', $session).'?'.http_build_query([
            'site_code' => 'SC1',
            'icode' => 'SKU1',
            'batch_no' => '',
            'sprefcode' => '1',
        ]))
        ->assertForbidden();
});
