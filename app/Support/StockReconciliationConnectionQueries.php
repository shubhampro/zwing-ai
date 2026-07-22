<?php

namespace App\Support;

use InvalidArgumentException;

final class StockReconciliationConnectionQueries
{
    public const MYSQL_STOCK = <<<'SQL'
SELECT
    stores.store_reference_code AS site_code,
    stock_point_summary.barcode AS barcode,
    im_sku_flat_table.ref_sku_code AS icode,
    batch.batch_no AS batch_no,
    stock_points.ref_stock_point_code AS sprefcode,
    stock_points.name AS stock_point_name,
    stock_point_summary.qty
FROM stock_point_summary
LEFT JOIN stores ON stores.store_id = stock_point_summary.store_id
LEFT JOIN stock_points ON stock_points.id = stock_point_summary.stock_point_id
LEFT JOIN im_sku_flat_table ON im_sku_flat_table.sku_code = stock_point_summary.sku_code
LEFT JOIN batch ON batch.id = stock_point_summary.batch_id
WHERE stores.status = 1
SQL;

    public const MYSQL_ACTIVE_SITE_CODES = <<<'SQL'
SELECT store_reference_code
FROM stores
WHERE status = 1
  AND store_reference_code IS NOT NULL
  AND TRIM(store_reference_code) <> ''
SQL;

    public const MYSQL_STOCK_LOGS = <<<'SQL'
SELECT
    stock_logs.transaction_type AS enttype,
    DATE(stock_logs.created_at) AS date,
    stock_logs.qty,
    stock_logs.document_number AS scheme_docno
FROM stock_logs
LEFT JOIN im_sku_flat_table ON im_sku_flat_table.sku_code = stock_logs.sku_code
LEFT JOIN stores ON stores.store_id = stock_logs.store_id
LEFT JOIN stock_points ON stock_points.id = stock_logs.stock_point_id
WHERE im_sku_flat_table.ref_item_code = ?
  AND stock_points.ref_stock_point_code = ?
  AND stores.store_reference_code = ?
SQL;

    public const PGSQL_STOCK_LOGS = <<<'SQL'
SELECT
    enttype,
    DATE(entdt) AS date,
    qty,
    scheme_docno
FROM invstock
WHERE admsite_code = ?
  AND icode = ?
  AND psite_stockpoint_code LIKE ?
SQL;

    public const MYSQL_STOCK_ROW = <<<'SQL'
SELECT
    stores.store_reference_code AS site_code,
    stock_point_summary.barcode AS barcode,
    im_sku_flat_table.ref_sku_code AS icode,
    batch.batch_no AS batch_no,
    stock_points.ref_stock_point_code AS sprefcode,
    stock_points.name AS stock_point_name,
    stock_point_summary.qty
FROM stock_point_summary
LEFT JOIN stores ON stores.store_id = stock_point_summary.store_id
LEFT JOIN stock_points ON stock_points.id = stock_point_summary.stock_point_id
LEFT JOIN im_sku_flat_table ON im_sku_flat_table.sku_code = stock_point_summary.sku_code
LEFT JOIN batch ON batch.id = stock_point_summary.batch_id
WHERE stores.status = 1
  AND stores.store_reference_code = ?
  AND im_sku_flat_table.ref_sku_code = ?
  AND stock_points.ref_stock_point_code = ?
  AND COALESCE(batch.batch_no, '') = ?
SQL;

    public const PGSQL_STOCK_ROW = <<<'SQL'
SELECT
    lv.sitecode AS site_code,
    main.invitem.barcode,
    lv.icode,
    lv.batch_serial_no AS batch_no,
    lv.stockpointcode AS sprefcode,
    lv.stockpointname AS stock_point_name,
    lv.closingstockqty AS qty
FROM ginview."lv_realtimestock_stkpoint" AS lv
LEFT JOIN main.invitem ON lv.icode = main.invitem.icode
WHERE lv.sitecode = ?
  AND lv.icode = ?
  AND COALESCE(lv.batch_serial_no, '') = ?
  AND lv.stockpointcode LIKE ?
SQL;

    /**
     * Build ERP realtime stock query with dynamic site codes from MySQL stores.
     *
     * @param  list<string|int>  $siteCodes
     * @return array{0: string, 1: list<string|int>}
     */
    public static function pgsqlStock(array $siteCodes): array
    {
        if ($siteCodes === []) {
            throw new InvalidArgumentException('At least one site code is required for ERP stock pull.');
        }

        $placeholders = implode(', ', array_fill(0, count($siteCodes), '?'));

        $sql = <<<SQL
SELECT
    lv.sitecode AS site_code,
    main.invitem.barcode,
    lv.icode,
    lv.batch_serial_no AS batch_no,
    lv.stockpointcode AS sprefcode,
    lv.stockpointname AS stock_point_name,
    lv.closingstockqty AS qty
FROM ginview."lv_realtimestock_stkpoint" AS lv
LEFT JOIN main.invitem ON lv.icode = main.invitem.icode
WHERE lv.sitecode IN ({$placeholders})
ORDER BY lv.sitename, lv.icode, lv.stockpointname
SQL;

        return [$sql, array_values($siteCodes)];
    }
}
