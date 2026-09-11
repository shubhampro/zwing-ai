<?php

use App\Models\ReportReconSession;
use App\Services\ReportConsolidationPuller;

test('maps invoice and mop query columns onto insert row', function () {
    $session = new ReportReconSession;
    $session->id = 4;
    $session->v_id = 147;
    $puller = new ReportConsolidationPuller;
    $now = '2026-08-27 10:00:00';

    $row = $puller->mapInsertRow([
        'invoice_no' => 'PMM1',
        'store_name' => ' Phoenix Mall ',
        'date' => '2026-08-02',
        'total' => '550.5',
    ], $session, $now);

    expect($row)->toMatchArray([
        'session_id' => 4,
        'v_id' => 147,
        'invoice_no' => 'PMM1',
        'store_name' => 'Phoenix Mall',
        'date' => '2026-08-02',
        'total' => '550.5000',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
});

test('maps missing store name as null', function () {
    $session = new ReportReconSession;
    $session->id = 4;
    $session->v_id = 147;
    $puller = new ReportConsolidationPuller;

    $row = $puller->mapInsertRow([
        'invoice_no' => 'PMM1',
        'date' => '2026-08-02',
        'total' => '550.5',
    ], $session, '2026-08-27 10:00:00');

    expect($row['store_name'])->toBeNull();
});

test('rejects rows without invoice no or amount', function () {
    $puller = new ReportConsolidationPuller;

    expect($puller->isValidRow([
        'invoice_no' => '',
        'total' => 10,
    ]))->toBeFalse()
        ->and($puller->isValidRow([
            'invoice_no' => 'PMM1',
            'total' => 'x',
        ]))->toBeFalse()
        ->and($puller->isValidRow([
            'invoice_no' => 'PMM1',
            'total' => -12.5,
        ]))->toBeTrue()
        ->and($puller->isValidRow([
            'invoice_no' => 'PMM1',
            'date' => null,
            'total' => 0,
        ]))->toBeTrue()
        ->and($puller->isValidRow([
            'invoice_no' => 'PMM1',
            'total' => null,
        ]))->toBeFalse();
});
