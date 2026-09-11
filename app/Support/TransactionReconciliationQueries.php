<?php

namespace App\Support;

use App\Enums\TransactionReconType;
use RuntimeException;

final class TransactionReconciliationQueries
{
    public const MYSQL_PACKET = <<<'SQL'
SELECT
    id AS txn_id,
    packet_code AS code,
    creation_mode AS type,
    CASE
        WHEN status = 'TRANSFERRED' THEN 'SUCCESS'
        WHEN status = 'SEALED' THEN 'SUCCESS'
        WHEN status = 'VOID' THEN 'VOID'
        ELSE 'Unknown'
    END AS status
FROM packets
WHERE status IN ('TRANSFERRED', 'SEALED', 'VOID')
SQL;

    public const PGSQL_PACKET = <<<'SQL'
SELECT
    intgrefid AS txn_id,
    packetno AS code,
    packetcreationmode AS type,
    CASE
        WHEN status = 'C' THEN 'SUCCESS'
        WHEN status = 'V' THEN 'VOID'
        ELSE 'Unknown'
    END AS status
FROM psite_packet
WHERE intgrefid IS NOT NULL
SQL;

    public const MYSQL_GRN = <<<'SQL'
SELECT
    grn_no AS txn_id,
    grn_no AS code,
    DATE(created_at) AS date,
    status
FROM grn
WHERE status IN ('posted', 'void')
  AND (ref_doc_no IS NULL OR ref_doc_no = '')
SQL;

    public const MYSQL_GRN_HEADERS = <<<'SQL'
SELECT
    grn_no AS txn_id,
    grn_no AS code,
    DATE(created_at) AS date,
    status
FROM grn_headers
WHERE status IN ('Complete', 'Void')
  AND (ref_doc_no IS NULL OR ref_doc_no = '')
SQL;

    public const PGSQL_GRN = <<<'SQL'
SELECT
    docno AS txn_id,
    docno AS code,
    DATE(docdt) AS date
FROM psite_grc
WHERE createdby = 'ZPOS'
SQL;

    public const MYSQL_GRT = <<<'SQL'
SELECT
    id AS txn_id,
    grt_no AS code,
    status
FROM grt_headers
WHERE status IN ('POST', 'VOID')
SQL;

    public const PGSQL_GRT = <<<'SQL'
SELECT
    intgrefid AS txn_id,
    docno AS code,
    'POST' AS status
FROM psite_grt
WHERE intgrefid IS NOT NULL
SQL;

    public const MYSQL_CASH = <<<'SQL'
SELECT
    CONCAT(stores.store_reference_code, '|', cash_transactions.doc_no) AS txn_id,
    cash_transactions.doc_no AS code,
    stores.store_reference_code AS type,
    cash_transactions.status AS status,
    stores.store_reference_code AS site_id,
    cash_transactions.`date` AS txn_date,
    cash_transactions.amount AS amount
FROM cash_transactions
LEFT JOIN stores ON cash_transactions.store_id = stores.store_id
WHERE stores.store_type = 'COCO'
  AND in_Cash_point_type = 'Store-Cash'
  AND cash_transactions.request_to != 'Petty Cash'
UNION ALL
SELECT
    CONCAT(stores.store_reference_code, '|', store_expenses.doc_no) AS txn_id,
    store_expenses.doc_no AS code,
    stores.store_reference_code AS type,
    store_expenses.status AS status,
    stores.store_reference_code AS site_id,
    store_expenses.date AS txn_date,
    store_expenses.amount AS amount
FROM store_expenses
LEFT JOIN stores ON store_expenses.store_id = stores.store_id
WHERE stores.store_type = 'COCO'
SQL;

    public const PGSQL_CASH = <<<'SQL'
SELECT
    CONCAT(ref_admsite_code, '|', scheme_docno) AS txn_id,
    scheme_docno AS code,
    ref_admsite_code AS type,
    'APPROVED' AS status,
    ref_admsite_code AS site_id,
    DATE(entdt) AS txn_date,
    damount AS amount
FROM finpost
LEFT JOIN main.hrdemp hd ON hd.ecode = finpost.release_ecode
WHERE hd.fname LIKE '%ZPOS%'
  AND damount IS NOT NULL
  AND enttype = 'PJN'
SQL;

    public static function isAvailable(TransactionReconType $type): bool
    {
        return match ($type) {
            TransactionReconType::Packet, TransactionReconType::Grn, TransactionReconType::Grt, TransactionReconType::Cash => true,
            default => false,
        };
    }

    public static function mysql(TransactionReconType $type, bool $grnTableExists = true): string
    {
        return match ($type) {
            TransactionReconType::Packet => self::MYSQL_PACKET,
            TransactionReconType::Grn => $grnTableExists ? self::MYSQL_GRN : self::MYSQL_GRN_HEADERS,
            TransactionReconType::Grt => self::MYSQL_GRT,
            TransactionReconType::Cash => self::MYSQL_CASH,
            default => throw new RuntimeException("Zwing query not configured for {$type->value}."),
        };
    }

    public static function pgsql(TransactionReconType $type): string
    {
        return match ($type) {
            TransactionReconType::Packet => self::PGSQL_PACKET,
            TransactionReconType::Grn => self::PGSQL_GRN,
            TransactionReconType::Grt => self::PGSQL_GRT,
            TransactionReconType::Cash => self::PGSQL_CASH,
            default => throw new RuntimeException("ERP query not configured for {$type->value}."),
        };
    }
}
