<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\StockReconSession;
use App\Support\StockReconciliationConnectionQueries;
use RuntimeException;

class StockReconQtySumService
{
    public function __construct(
        private readonly OrganizationDatabaseConnector $connector,
    ) {}

    /**
     * @return array{
     *     store_id: int|string,
     *     stock_point_id: int|string,
     *     sku_code: int|string,
     *     stock_logs_qty_sum: float,
     *     stock_point_summary_qty_sum: float,
     *     query_ms: int,
     * }
     */
    public function forSku(
        StockReconSession $session,
        string $siteCode,
        string $icode,
        string $sprefcode,
    ): array {
        if (($session->source ?? 'csv') !== 'connection') {
            throw new RuntimeException('Qty sums are only available for connection sessions.');
        }

        if ($session->zwing_file_name === null) {
            throw new RuntimeException('Zwing connection is not included in this session.');
        }

        $organization = Organization::query()->find($session->organization_id);

        if ($organization === null || blank($organization->db_name)) {
            throw new RuntimeException(
                'Organization MySQL database name is missing. Attach a Zwing vendor with db_name first.',
            );
        }

        $runtimeName = $this->connector->openMysqlSshDatabase((string) $organization->db_name);
        $startedAt = hrtime(true);

        try {
            $storeId = $this->firstColumn(
                $runtimeName,
                StockReconciliationConnectionQueries::MYSQL_RESOLVE_STORE_ID,
                [$siteCode],
                'store_id',
            );

            if ($storeId === null) {
                throw new RuntimeException("Store not found for site_code [{$siteCode}].");
            }

            $stockPointId = $this->firstColumn(
                $runtimeName,
                StockReconciliationConnectionQueries::MYSQL_RESOLVE_STOCK_POINT_ID,
                [$sprefcode, $storeId],
                'id',
            );

            if ($stockPointId === null) {
                throw new RuntimeException(
                    "Stock point not found for sprefcode [{$sprefcode}] and store_id [{$storeId}].",
                );
            }

            $skuCode = $this->firstColumn(
                $runtimeName,
                StockReconciliationConnectionQueries::MYSQL_RESOLVE_SKU_CODE,
                [$icode],
                'sku_code',
            );

            if ($skuCode === null) {
                throw new RuntimeException("SKU not found for icode [{$icode}].");
            }

            $stockLogsQtySum = $this->sumQty(
                $runtimeName,
                StockReconciliationConnectionQueries::MYSQL_STOCK_LOGS_QTY_SUM,
                [$storeId, $stockPointId, $skuCode],
            );

            $stockPointSummaryQtySum = $this->sumQty(
                $runtimeName,
                StockReconciliationConnectionQueries::MYSQL_STOCK_POINT_SUMMARY_QTY_SUM,
                [$storeId, $stockPointId, $skuCode],
            );

            return [
                'store_id' => $storeId,
                'stock_point_id' => $stockPointId,
                'sku_code' => $skuCode,
                'stock_logs_qty_sum' => $stockLogsQtySum,
                'stock_point_summary_qty_sum' => $stockPointSummaryQtySum,
                'query_ms' => (int) max(0, (hrtime(true) - $startedAt) / 1_000_000),
            ];
        } finally {
            $this->connector->close($runtimeName);
        }
    }

    /**
     * @param  list<mixed>  $bindings
     */
    private function firstColumn(string $runtimeName, string $sql, array $bindings, string $column): mixed
    {
        $value = null;

        $this->connector->eachRow($runtimeName, $sql, $bindings, function (array $row) use (&$value, $column): void {
            if ($value !== null) {
                return;
            }

            $value = $row[$column] ?? null;
        });

        return $value;
    }

    /**
     * @param  list<mixed>  $bindings
     */
    private function sumQty(string $runtimeName, string $sql, array $bindings): float
    {
        $sum = 0.0;

        $this->connector->eachRow($runtimeName, $sql, $bindings, function (array $row) use (&$sum): void {
            $sum = (float) ($row['qty_sum'] ?? 0);
        });

        return $sum;
    }
}
