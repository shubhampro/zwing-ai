<?php

use App\Services\TransactionReconciliationPuller;

test('maps cash query columns onto recon insert row', function () {
    $puller = new TransactionReconciliationPuller;
    $now = '2026-08-17 10:00:00';

    $row = $puller->mapInsertRow([
        'txn_id' => '101|CASH-1',
        'code' => 'CASH-1',
        'type' => '101',
        'status' => 'APPROVED',
        'site_id' => '101',
        'txn_date' => '2026-06-01 14:30:00',
        'amount' => '250.5',
    ], 9, $now);

    expect($row)->toMatchArray([
        'session_id' => 9,
        'txn_id' => '101|CASH-1',
        'code' => 'CASH-1',
        'type' => '101',
        'status' => 'APPROVED',
        'site_id' => '101',
        'txn_date' => '2026-06-01',
        'amount' => '250.5000',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
});

test('packet rows keep cash columns null', function () {
    $puller = new TransactionReconciliationPuller;

    $row = $puller->mapInsertRow([
        'txn_id' => '1',
        'code' => 'PCB1',
        'type' => 'Adhoc',
        'status' => 'SUCCESS',
    ], 1, '2026-08-17 10:00:00');

    expect($row['site_id'])->toBeNull()
        ->and($row['txn_date'])->toBeNull()
        ->and($row['amount'])->toBeNull();
});
