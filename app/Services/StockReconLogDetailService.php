<?php

namespace App\Services;

use App\Enums\DatabaseConnectionType;
use App\Models\Organization;
use App\Models\OrganizationDatabaseConnection;
use App\Models\StockReconErpLog;
use App\Models\StockReconSession;
use App\Models\StockReconZwingLog;
use App\Support\Sprefcode;
use App\Support\StockReconciliationConnectionQueries;
use RuntimeException;

class StockReconLogDetailService
{
    public function __construct(
        private readonly OrganizationDatabaseConnector $connector,
    ) {}

    /**
     * @return array{
     *     has_zwing_logs: bool,
     *     has_erp_logs: bool,
     *     zwing_query_ms: int|null,
     *     erp_query_ms: int|null,
     *     matched: array{zwing: list<array<string, mixed>>, erp: list<array<string, mixed>>},
     *     mismatch: array{zwing: list<array<string, mixed>>, erp: list<array<string, mixed>>},
     * }
     */
    public function forSku(
        StockReconSession $session,
        string $siteCode,
        string $icode,
        string $batchNo,
        string $sprefcode,
    ): array {
        if (($session->source ?? 'csv') === 'connection') {
            return $this->forConnectionSku($session, $siteCode, $icode, $sprefcode);
        }

        $hasZwingLogs = $session->zwing_log_file_name !== null;
        $hasErpLogs = $session->erp_log_file_name !== null;

        $zwingRows = $hasZwingLogs
            ? $this->fetchCsvLogs(StockReconZwingLog::class, $session->id, $siteCode, $icode, $batchNo, $sprefcode)
            : [];

        $erpRows = $hasErpLogs
            ? $this->fetchCsvLogs(StockReconErpLog::class, $session->id, $siteCode, $icode, $batchNo, $sprefcode)
            : [];

        return $this->partitionByDocQty(
            zwingRows: $zwingRows,
            erpRows: $erpRows,
            hasZwingLogs: $hasZwingLogs,
            hasErpLogs: $hasErpLogs,
            zwingQueryMs: null,
            erpQueryMs: null,
        );
    }

    /**
     * @return array{
     *     has_zwing_logs: bool,
     *     has_erp_logs: bool,
     *     zwing_query_ms: int|null,
     *     erp_query_ms: int|null,
     *     matched: array{zwing: list<array<string, mixed>>, erp: list<array<string, mixed>>},
     *     mismatch: array{zwing: list<array<string, mixed>>, erp: list<array<string, mixed>>},
     * }
     */
    private function forConnectionSku(
        StockReconSession $session,
        string $siteCode,
        string $icode,
        string $sprefcode,
    ): array {
        $hasZwingLogs = $session->zwing_file_name !== null;
        $hasErpLogs = $session->erp_file_name !== null;
        $stockpointSuffix = Sprefcode::parse($sprefcode);

        $zwingRows = [];
        $erpRows = [];
        $zwingQueryMs = null;
        $erpQueryMs = null;

        if ($hasZwingLogs) {
            $startedAt = hrtime(true);
            $zwingRows = $this->fetchLiveZwingLogs($session, $siteCode, $icode, $stockpointSuffix);
            $zwingQueryMs = (int) max(0, (hrtime(true) - $startedAt) / 1_000_000);
        }

        if ($hasErpLogs) {
            $startedAt = hrtime(true);
            $erpRows = $this->fetchLiveErpLogs($session, $siteCode, $icode, $stockpointSuffix);
            $erpQueryMs = (int) max(0, (hrtime(true) - $startedAt) / 1_000_000);
        }

        return $this->partitionByDocQty(
            zwingRows: $zwingRows,
            erpRows: $erpRows,
            hasZwingLogs: $hasZwingLogs,
            hasErpLogs: $hasErpLogs,
            zwingQueryMs: $zwingQueryMs,
            erpQueryMs: $erpQueryMs,
        );
    }

    /**
     * @param  list<array{id: int, doc_no: string, qty: float, enttype: string}>  $zwingRows
     * @param  list<array{id: int, doc_no: string, qty: float, enttype: string}>  $erpRows
     * @return array{
     *     has_zwing_logs: bool,
     *     has_erp_logs: bool,
     *     zwing_query_ms: int|null,
     *     erp_query_ms: int|null,
     *     matched: array{zwing: list<array<string, mixed>>, erp: list<array<string, mixed>>},
     *     mismatch: array{zwing: list<array<string, mixed>>, erp: list<array<string, mixed>>},
     * }
     */
    private function partitionByDocQty(
        array $zwingRows,
        array $erpRows,
        bool $hasZwingLogs,
        bool $hasErpLogs,
        ?int $zwingQueryMs,
        ?int $erpQueryMs,
    ): array {
        $erpKeys = [];
        foreach ($erpRows as $row) {
            $erpKeys[$this->pairKey($row['doc_no'], $row['qty'])] = true;
        }

        $zwingKeys = [];
        foreach ($zwingRows as $row) {
            $zwingKeys[$this->pairKey($row['doc_no'], $row['qty'])] = true;
        }

        $matchedZwing = [];
        $matchedErp = [];
        $mismatchZwing = [];
        $mismatchErp = [];

        foreach ($zwingRows as $row) {
            $key = $this->pairKey($row['doc_no'], $row['qty']);
            if (isset($erpKeys[$key])) {
                $matchedZwing[] = $row;
            } else {
                $mismatchZwing[] = $row;
            }
        }

        foreach ($erpRows as $row) {
            $key = $this->pairKey($row['doc_no'], $row['qty']);
            if (isset($zwingKeys[$key])) {
                $matchedErp[] = $row;
            } else {
                $mismatchErp[] = $row;
            }
        }

        return [
            'has_zwing_logs' => $hasZwingLogs,
            'has_erp_logs' => $hasErpLogs,
            'zwing_query_ms' => $zwingQueryMs,
            'erp_query_ms' => $erpQueryMs,
            'matched' => [
                'zwing' => $matchedZwing,
                'erp' => $matchedErp,
            ],
            'mismatch' => [
                'zwing' => $mismatchZwing,
                'erp' => $mismatchErp,
            ],
        ];
    }

    /**
     * @return list<array{id: int, doc_no: string, qty: float, enttype: string}>
     */
    private function fetchLiveZwingLogs(
        StockReconSession $session,
        string $siteCode,
        string $icode,
        int $stockpointSuffix,
    ): array {
        $organization = Organization::query()->find($session->organization_id);

        if ($organization === null || blank($organization->db_name)) {
            throw new RuntimeException(
                'Organization MySQL database name is missing. Attach a Zwing vendor with db_name first.',
            );
        }

        $runtimeName = $this->connector->openMysqlSshDatabase((string) $organization->db_name);

        try {
            return $this->collectLogRows(
                $runtimeName,
                StockReconciliationConnectionQueries::MYSQL_STOCK_LOGS,
                [$icode, $stockpointSuffix, $siteCode],
            );
        } finally {
            $this->connector->close($runtimeName);
        }
    }

    /**
     * @return list<array{id: int, doc_no: string, qty: float, enttype: string}>
     */
    private function fetchLiveErpLogs(
        StockReconSession $session,
        string $siteCode,
        string $icode,
        int $stockpointSuffix,
    ): array {
        $pgsqlConnection = $this->resolvePgsqlConnection($session);

        if ($pgsqlConnection === null) {
            throw new RuntimeException('Postgres connection is required for ERP log lookup.');
        }

        $runtimeName = $this->connector->open($pgsqlConnection);

        try {
            return $this->collectLogRows(
                $runtimeName,
                StockReconciliationConnectionQueries::PGSQL_STOCK_LOGS,
                [$siteCode, $icode, '%'.$stockpointSuffix.'%'],
            );
        } finally {
            $this->connector->close($runtimeName);
        }
    }

    private function resolvePgsqlConnection(StockReconSession $session): ?OrganizationDatabaseConnection
    {
        if ($session->pgsql_connection_id !== null) {
            return OrganizationDatabaseConnection::query()->find($session->pgsql_connection_id);
        }

        if ($session->organization_id === null) {
            return null;
        }

        return OrganizationDatabaseConnection::query()
            ->where('organization_id', $session->organization_id)
            ->ofType(DatabaseConnectionType::Pgsql)
            ->active()
            ->first();
    }

    /**
     * @param  list<mixed>  $bindings
     * @return list<array{id: int, doc_no: string, qty: float, enttype: string}>
     */
    private function collectLogRows(string $runtimeName, string $sql, array $bindings): array
    {
        $rows = [];
        $index = 0;

        $this->connector->eachRow($runtimeName, $sql, $bindings, function (array $row) use (&$rows, &$index): void {
            $index++;
            $docNo = trim((string) ($row['scheme_docno'] ?? $row['doc_no'] ?? ''));

            $rows[] = [
                'id' => $index,
                'doc_no' => $docNo,
                'qty' => (float) ($row['qty'] ?? 0),
                'enttype' => (string) ($row['enttype'] ?? ''),
            ];
        });

        usort($rows, function (array $left, array $right): int {
            return [$left['doc_no'], $left['qty']] <=> [$right['doc_no'], $right['qty']];
        });

        return array_values($rows);
    }

    /**
     * @param  class-string<StockReconZwingLog|StockReconErpLog>  $modelClass
     * @return list<array{id: int, doc_no: string, qty: float, enttype: string}>
     */
    private function fetchCsvLogs(
        string $modelClass,
        int $sessionId,
        string $siteCode,
        string $icode,
        string $batchNo,
        string $sprefcode,
    ): array {
        return $modelClass::query()
            ->where('stock_recon_session_id', $sessionId)
            ->where('site_code', $siteCode)
            ->where('icode', $icode)
            ->where('batch_no', $batchNo)
            ->orderBy('doc_no')
            ->orderBy('qty')
            ->get(['id', 'doc_no', 'qty', 'enttype', 'sprefcode'])
            ->filter(fn ($row) => Sprefcode::matches((string) $row->sprefcode, $sprefcode))
            ->map(fn ($row) => [
                'id' => $row->id,
                'doc_no' => trim($row->doc_no),
                'qty' => (float) $row->qty,
                'enttype' => $row->enttype,
            ])
            ->values()
            ->all();
    }

    private function pairKey(string $docNo, float $qty): string
    {
        return trim($docNo).'|'.number_format($qty, 4, '.', '');
    }
}
