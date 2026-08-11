<?php

use App\Enums\TransactionReconType;
use App\Support\TransactionReconciliationQueries;

test('zwing packet query includes void status', function () {
    $sql = TransactionReconciliationQueries::mysql(TransactionReconType::Packet);

    expect($sql)
        ->toContain("WHEN status = 'VOID' THEN 'VOID'")
        ->toContain("WHERE status IN ('TRANSFERRED', 'SEALED', 'VOID')");
});

test('grt queries map id, docno, and status', function () {
    $zwing = TransactionReconciliationQueries::mysql(TransactionReconType::Grt);
    $erp = TransactionReconciliationQueries::pgsql(TransactionReconType::Grt);

    expect($zwing)
        ->toContain('id AS txn_id')
        ->toContain('grt_no AS code')
        ->toContain('status')
        ->toContain("WHERE status IN ('POST', 'VOID')")
        ->and($erp)
        ->toContain('intgrefid AS txn_id')
        ->toContain('docno AS code')
        ->toContain("'POST' AS status")
        ->toContain('FROM psite_grt')
        ->and(TransactionReconciliationQueries::isAvailable(TransactionReconType::Grt))->toBeTrue()
        ->and(TransactionReconciliationQueries::isAvailable(TransactionReconType::Grn))->toBeFalse();
});
