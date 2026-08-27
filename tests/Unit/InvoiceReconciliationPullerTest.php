<?php

use App\Models\InvoiceReconSession;
use App\Services\InvoiceReconciliationPuller;

test('maps invoice query columns onto recon insert row', function () {
    $session = new InvoiceReconSession;
    $session->id = 4;
    $session->v_id = 147;
    $puller = new InvoiceReconciliationPuller;
    $now = '2026-08-27 10:00:00';

    $row = $puller->mapInsertRow([
        'invoice_id' => 'PMM1',
        'ref_id' => '22-21',
        'total_amount' => '550.5',
        'status' => 'Success',
    ], $session, $now);

    expect($row)->toMatchArray([
        'session_id' => 4,
        'v_id' => 147,
        'invoice_id' => 'PMM1',
        'ref_id' => '22-21',
        'total_amount' => '550.5000',
        'status' => 'Success',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
});

test('rejects invoice rows without invoice id or amount', function () {
    $puller = new InvoiceReconciliationPuller;

    expect($puller->isValidRow([
        'invoice_id' => '',
        'ref_id' => '0',
        'total_amount' => 10,
        'status' => 'Success',
    ]))->toBeFalse()
        ->and($puller->isValidRow([
            'invoice_id' => 'PMM1',
            'ref_id' => '0',
            'total_amount' => 'x',
            'status' => 'Success',
        ]))->toBeFalse()
        ->and($puller->isValidRow([
            'invoice_id' => 'PMM1',
            'ref_id' => '0',
            'total_amount' => -12.5,
            'status' => 'Void',
        ]))->toBeTrue();
});
