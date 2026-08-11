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

    public static function isAvailable(TransactionReconType $type): bool
    {
        return match ($type) {
            TransactionReconType::Packet, TransactionReconType::Grt => true,
            default => false,
        };
    }

    public static function mysql(TransactionReconType $type): string
    {
        return match ($type) {
            TransactionReconType::Packet => self::MYSQL_PACKET,
            TransactionReconType::Grt => self::MYSQL_GRT,
            default => throw new RuntimeException("Zwing query not configured for {$type->value}."),
        };
    }

    public static function pgsql(TransactionReconType $type): string
    {
        return match ($type) {
            TransactionReconType::Packet => self::PGSQL_PACKET,
            TransactionReconType::Grt => self::PGSQL_GRT,
            default => throw new RuntimeException("ERP query not configured for {$type->value}."),
        };
    }
}
