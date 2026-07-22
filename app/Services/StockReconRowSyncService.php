<?php

namespace App\Services;

use App\Enums\DatabaseConnectionType;
use App\Models\Organization;
use App\Models\OrganizationDatabaseConnection;
use App\Models\StockReconSession;
use App\Support\Sprefcode;
use App\Support\StockReconciliationConnectionQueries;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockReconRowSyncService
{
    public function __construct(
        private readonly OrganizationDatabaseConnector $connector,
    ) {}

    /**
     * Re-fetch stock qty for one comparison key and replace only that local row.
     *
     * @return array{
     *     site_code: string,
     *     icode: string,
     *     batch_no: string,
     *     sprefcode: string,
     *     stock_point_name: string|null,
     *     zwing_qty: float|null,
     *     erp_qty: float|null,
     *     match_status: string|null,
     *     removed: bool,
     *     zwing_query_ms: int|null,
     *     erp_query_ms: int|null,
     * }
     */
    public function sync(
        StockReconSession $session,
        string $siteCode,
        string $icode,
        string $batchNo,
        string $sprefcode,
    ): array {
        if (($session->source ?? 'csv') !== 'connection') {
            throw new RuntimeException('Row sync is only available for connection sessions.');
        }

        $siteCode = trim($siteCode);
        $icode = trim($icode);
        $batchNo = trim($batchNo);
        $sprefcodeInt = Sprefcode::parse($sprefcode);

        $includeZwing = $session->zwing_file_name !== null;
        $includeErp = $session->erp_file_name !== null;

        $zwingRow = null;
        $erpRow = null;
        $zwingQueryMs = null;
        $erpQueryMs = null;
        $stockPointName = null;

        if ($includeZwing) {
            $startedAt = hrtime(true);
            $zwingRow = $this->fetchZwingRow($session, $siteCode, $icode, $batchNo, $sprefcodeInt);
            $zwingQueryMs = (int) max(0, (hrtime(true) - $startedAt) / 1_000_000);
            $this->replaceSideRows(
                table: 'zwing_stock_reconsile',
                session: $session,
                siteCode: $siteCode,
                icode: $icode,
                batchNo: $batchNo,
                sprefcode: $sprefcodeInt,
                row: $zwingRow,
            );
            $stockPointName = $zwingRow['stock_point_name'] ?? $stockPointName;
        }

        if ($includeErp) {
            $startedAt = hrtime(true);
            $erpRow = $this->fetchErpRow($session, $siteCode, $icode, $batchNo, $sprefcodeInt);
            $erpQueryMs = (int) max(0, (hrtime(true) - $startedAt) / 1_000_000);
            $this->replaceSideRows(
                table: 'erp_stock_reconsile',
                session: $session,
                siteCode: $siteCode,
                icode: $icode,
                batchNo: $batchNo,
                sprefcode: $sprefcodeInt,
                row: $erpRow,
            );
            $stockPointName = $erpRow['stock_point_name'] ?? $stockPointName;
        }

        $zwingQty = $zwingRow !== null ? (float) $zwingRow['qty'] : null;
        $erpQty = $erpRow !== null ? (float) $erpRow['qty'] : null;

        if (! $includeZwing) {
            $zwingQty = $this->existingQty('zwing_stock_reconsile', $session->id, $siteCode, $icode, $batchNo, $sprefcodeInt);
        }

        if (! $includeErp) {
            $erpQty = $this->existingQty('erp_stock_reconsile', $session->id, $siteCode, $icode, $batchNo, $sprefcodeInt);
        }

        if ($stockPointName === null) {
            $stockPointName = $this->existingStockPointName($session->id, $siteCode, $icode, $batchNo, $sprefcodeInt);
        }

        $removed = $zwingQty === null && $erpQty === null;

        return [
            'site_code' => $siteCode,
            'icode' => $icode,
            'batch_no' => $batchNo,
            'sprefcode' => (string) $sprefcodeInt,
            'stock_point_name' => $stockPointName,
            'zwing_qty' => $zwingQty,
            'erp_qty' => $erpQty,
            'match_status' => $removed ? null : $this->matchStatus($zwingQty, $erpQty),
            'removed' => $removed,
            'zwing_query_ms' => $zwingQueryMs,
            'erp_query_ms' => $erpQueryMs,
        ];
    }

    /**
     * @return array{barcode: string, stock_point_name: string, qty: float}|null
     */
    private function fetchZwingRow(
        StockReconSession $session,
        string $siteCode,
        string $icode,
        string $batchNo,
        int $sprefcode,
    ): ?array {
        $organization = Organization::query()->find($session->organization_id);

        if ($organization === null || blank($organization->db_name)) {
            throw new RuntimeException(
                'Organization MySQL database name is missing. Attach a Zwing vendor with db_name first.',
            );
        }

        $runtimeName = $this->connector->openMysqlSshDatabase((string) $organization->db_name);

        try {
            return $this->aggregateStockRows(
                $runtimeName,
                StockReconciliationConnectionQueries::MYSQL_STOCK_ROW,
                [$siteCode, $icode, $sprefcode, $batchNo],
                $sprefcode,
            );
        } finally {
            $this->connector->close($runtimeName);
        }
    }

    /**
     * @return array{barcode: string, stock_point_name: string, qty: float}|null
     */
    private function fetchErpRow(
        StockReconSession $session,
        string $siteCode,
        string $icode,
        string $batchNo,
        int $sprefcode,
    ): ?array {
        $pgsqlConnection = $this->resolvePgsqlConnection($session);

        if ($pgsqlConnection === null) {
            throw new RuntimeException('Postgres connection is required for ERP row sync.');
        }

        $runtimeName = $this->connector->open($pgsqlConnection);

        try {
            return $this->aggregateStockRows(
                $runtimeName,
                StockReconciliationConnectionQueries::PGSQL_STOCK_ROW,
                [$siteCode, $icode, $batchNo, '%'.$sprefcode.'%'],
                $sprefcode,
            );
        } finally {
            $this->connector->close($runtimeName);
        }
    }

    /**
     * @param  list<mixed>  $bindings
     * @return array{barcode: string, stock_point_name: string, qty: float}|null
     */
    private function aggregateStockRows(
        string $runtimeName,
        string $sql,
        array $bindings,
        int $sprefcode,
    ): ?array {
        $barcode = '';
        $stockPointName = '';
        $qty = 0.0;
        $matched = 0;

        $this->connector->eachRow($runtimeName, $sql, $bindings, function (array $record) use (
            &$barcode,
            &$stockPointName,
            &$qty,
            &$matched,
            $sprefcode,
        ): void {
            if (! $this->isValidRemoteRow($record, $sprefcode)) {
                return;
            }

            if ($matched === 0) {
                $barcode = trim((string) ($record['barcode'] ?? ''));
                $stockPointName = trim((string) $record['stock_point_name']);
            }

            $qty += (float) $record['qty'];
            $matched++;
        });

        if ($matched === 0) {
            return null;
        }

        return [
            'barcode' => $barcode,
            'stock_point_name' => $stockPointName,
            'qty' => $qty,
        ];
    }

    /**
     * @param  array{barcode: string, stock_point_name: string, qty: float}|null  $row
     */
    private function replaceSideRows(
        string $table,
        StockReconSession $session,
        string $siteCode,
        string $icode,
        string $batchNo,
        int $sprefcode,
        ?array $row,
    ): void {
        DB::table($table)
            ->where('session_id', $session->id)
            ->where('site_code', $siteCode)
            ->where('icode', $icode)
            ->where('batch_no', $batchNo)
            ->where('sprefcode', $sprefcode)
            ->delete();

        if ($row === null) {
            return;
        }

        $now = now()->toDateTimeString();

        DB::table($table)->insert([
            'session_id' => $session->id,
            'batch_no' => $batchNo,
            'v_id' => $session->v_id,
            'barcode' => $row['barcode'],
            'icode' => $icode,
            'stock_point_name' => $row['stock_point_name'],
            'site_code' => $siteCode,
            'sprefcode' => $sprefcode,
            'qty' => $row['qty'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function existingQty(
        string $table,
        int $sessionId,
        string $siteCode,
        string $icode,
        string $batchNo,
        int $sprefcode,
    ): ?float {
        $qty = DB::table($table)
            ->where('session_id', $sessionId)
            ->where('site_code', $siteCode)
            ->where('icode', $icode)
            ->where('batch_no', $batchNo)
            ->where('sprefcode', $sprefcode)
            ->value('qty');

        return $qty === null ? null : (float) $qty;
    }

    private function existingStockPointName(
        int $sessionId,
        string $siteCode,
        string $icode,
        string $batchNo,
        int $sprefcode,
    ): ?string {
        $name = DB::table('zwing_stock_reconsile')
            ->where('session_id', $sessionId)
            ->where('site_code', $siteCode)
            ->where('icode', $icode)
            ->where('batch_no', $batchNo)
            ->where('sprefcode', $sprefcode)
            ->value('stock_point_name');

        if (is_string($name) && $name !== '') {
            return $name;
        }

        $name = DB::table('erp_stock_reconsile')
            ->where('session_id', $sessionId)
            ->where('site_code', $siteCode)
            ->where('icode', $icode)
            ->where('batch_no', $batchNo)
            ->where('sprefcode', $sprefcode)
            ->value('stock_point_name');

        return is_string($name) && $name !== '' ? $name : null;
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
     * @param  array<string, mixed>  $record
     */
    private function isValidRemoteRow(array $record, int $expectedSprefcode): bool
    {
        if (! isset($record['icode']) || trim((string) $record['icode']) === '') {
            return false;
        }

        foreach (['site_code', 'stock_point_name'] as $col) {
            if (! isset($record[$col]) || trim((string) $record[$col]) === '') {
                return false;
            }
        }

        if (! isset($record['qty']) || ! is_numeric(trim((string) $record['qty']))) {
            return false;
        }

        return Sprefcode::parse(trim((string) ($record['sprefcode'] ?? ''))) === $expectedSprefcode;
    }

    private function matchStatus(?float $zwingQty, ?float $erpQty): string
    {
        if ($zwingQty === null) {
            return 'erp_only';
        }

        if ($erpQty === null) {
            return 'zwing_only';
        }

        return $zwingQty == $erpQty ? 'matched' : 'qty_mismatch';
    }
}
