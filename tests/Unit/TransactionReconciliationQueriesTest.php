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

test('cash queries map site, doc, date, amount, and status without date range', function () {
    $zwing = TransactionReconciliationQueries::mysql(TransactionReconType::Cash);
    $erp = TransactionReconciliationQueries::pgsql(TransactionReconType::Cash);

    expect($zwing)
        ->toContain('CONCAT(stores.store_reference_code, \'|\', cash_transactions.doc_no) AS txn_id')
        ->toContain('cash_transactions.doc_no AS code')
        ->toContain('cash_transactions.amount AS amount')
        ->toContain('UNION ALL')
        ->toContain("stores.store_type = 'COCO'")
        ->toContain("in_Cash_point_type = 'Store-Cash'")
        ->toContain("cash_transactions.request_to != 'Petty Cash'")
        ->not->toContain('between')
        ->and($erp)
        ->toContain("CONCAT(ref_admsite_code, '|', scheme_docno) AS txn_id")
        ->toContain('scheme_docno AS code')
        ->toContain("'APPROVED' AS status")
        ->toContain('FROM finpost')
        ->toContain("enttype = 'PJN'")
        ->not->toContain('between')
        ->and(TransactionReconciliationQueries::isAvailable(TransactionReconType::Cash))->toBeTrue();
});
