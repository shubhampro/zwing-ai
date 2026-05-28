<?php

use App\Jobs\ParseStockReconciliationLogCsv;
use App\Models\StockReconErpLog;
use App\Models\StockReconSession;
use App\Models\StockReconZwingLog;
use App\Models\User;

test('log csv job inserts zwing and erp log rows', function () {
    $user = User::factory()->create();
    $session = StockReconSession::create([
        'user_id' => $user->id,
        'name' => 'log-parse-session',
        'v_id' => 288,
        'status' => 'processing',
    ]);

    $logHeader = "site_code,icode,batch_no,sprefcode,doc_no,enttype,qty\n";
    $zwingPath = tempnam(sys_get_temp_dir(), 'zwing-log');
    $erpPath = tempnam(sys_get_temp_dir(), 'erp-log');
    file_put_contents($zwingPath, $logHeader."SC1,I1,B1,SPR1,D1,GRN,5\nSC2,,B2,SPR2,D2,GRT,1\n");
    file_put_contents($erpPath, $logHeader."SC3,I3,B3,SPR3,D3,SST,2\n");

    (new ParseStockReconciliationLogCsv(
        sessionId: $session->id,
        zwingLogPath: $zwingPath,
        erpLogPath: $erpPath,
    ))->handle();

    $session->refresh();

    expect(StockReconZwingLog::where('stock_recon_session_id', $session->id)->count())->toBe(1);
    expect(StockReconErpLog::where('stock_recon_session_id', $session->id)->count())->toBe(1);
    expect($session->zwing_log_processed_rows)->toBe(1);
    expect($session->zwing_log_skipped_rows)->toBe(1);
    expect($session->erp_log_processed_rows)->toBe(1);

    unlink($zwingPath);
    unlink($erpPath);
});

test('erp log csv stores numeric suffix from sprefcode', function () {
    $user = User::factory()->create();
    $session = StockReconSession::create([
        'user_id' => $user->id,
        'name' => 'erp-sprefcode-parse',
        'v_id' => 288,
        'status' => 'processing',
    ]);

    $logHeader = "site_code,icode,batch_no,sprefcode,doc_no,enttype,qty\n";
    $erpPath = tempnam(sys_get_temp_dir(), 'erp-log');
    file_put_contents($erpPath, $logHeader."SC1,I1,B1,NDPL0003-1,D1,SAL,5\n");

    (new ParseStockReconciliationLogCsv(
        sessionId: $session->id,
        zwingLogPath: '',
        erpLogPath: $erpPath,
    ))->handle();

    $row = StockReconErpLog::query()
        ->where('stock_recon_session_id', $session->id)
        ->first();

    expect($row)->not->toBeNull();
    expect($row->sprefcode)->toBe('1');

    unlink($erpPath);
});

test('log row validation requires icode and numeric qty', function () {
    expect(ParseStockReconciliationLogCsv::isValidLogRow(['icode' => 'I1', 'qty' => '5']))->toBeTrue();
    expect(ParseStockReconciliationLogCsv::isValidLogRow(['icode' => 'I1']))->toBeFalse();
    expect(ParseStockReconciliationLogCsv::isValidLogRow(['icode' => '']))->toBeFalse();
    expect(ParseStockReconciliationLogCsv::isValidLogRow([]))->toBeFalse();
});
