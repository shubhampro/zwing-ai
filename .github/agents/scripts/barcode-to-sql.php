#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Reads values from a CSV column and builds a SQL IN-clause query.
 * The CSV header name is used as the WHERE column — no fixed column name required.
 *
 * Usage:
 *   php barcode-to-sql.php --table=stock_items /path/to/file.csv
 *   php barcode-to-sql.php --table=stock_items --column=icode /path/to/file.csv
 */

/** @return array{table: string, column: ?string, path: string}|null */
function parseArgs(array $argv): ?array
{
    $table = null;
    $column = null;
    $path = null;

    for ($i = 1; $i < count($argv); $i++) {
        if (str_starts_with($argv[$i], '--table=')) {
            $table = substr($argv[$i], 8);
        } elseif (str_starts_with($argv[$i], '--column=')) {
            $column = substr($argv[$i], 9);
        } elseif (! str_starts_with($argv[$i], '--')) {
            $path = $argv[$i];
        }
    }

    if ($table === null || $path === null) {
        fwrite(STDERR, "Usage: php barcode-to-sql.php --table=<table_name> [--column=<column_name>] /path/to/file.csv\n");

        return null;
    }

    if (! is_readable($path)) {
        fwrite(STDERR, "Error: Cannot read file: {$path}\n");

        return null;
    }

    if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
        fwrite(STDERR, "Error: Invalid table name. Use only letters, numbers, and underscores.\n");

        return null;
    }

    if ($column !== null && ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
        fwrite(STDERR, "Error: Invalid column name. Use only letters, numbers, and underscores.\n");

        return null;
    }

    return ['table' => $table, 'column' => $column, 'path' => $path];
}

function normalizeHeader(string $header): string
{
    $clean = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $header) ?? $header;

    return strtolower(trim($clean));
}

/** @param list<string> $barcodes */
function formatInClause(array $barcodes): string
{
    $quoted = array_map(
        static fn (string $barcode): string => "'".str_replace("'", "''", $barcode)."'",
        $barcodes,
    );

    return '('.implode(',', $quoted).')';
}

/** @return list<list<string>> */
function readCsvRows(string $path): array
{
    $handle = fopen($path, 'r');
    if ($handle === false) {
        throw new RuntimeException("Cannot open file: {$path}");
    }

    $rows = [];
    while (($row = fgetcsv($handle)) !== false) {
        $rows[] = $row;
    }
    fclose($handle);

    return $rows;
}

/**
 * @param  list<string>  $rawHeaders
 * @param  list<string>  $normalizedHeaders
 * @return array{column: string, sql_column: string}
 */
function resolveValueColumn(array $rawHeaders, array $normalizedHeaders, ?string $requestedColumn): array
{
    if ($normalizedHeaders === []) {
        throw new RuntimeException('CSV has no header row.');
    }

    if ($requestedColumn !== null) {
        $normalized = strtolower($requestedColumn);
        $index = array_search($normalized, $normalizedHeaders, true);
        if ($index === false) {
            throw new RuntimeException("Column '{$requestedColumn}' not found in CSV headers: ".implode(', ', $rawHeaders));
        }

        return [
            'column' => $normalized,
            'sql_column' => trim((string) $rawHeaders[$index]),
        ];
    }

    return [
        'column' => $normalizedHeaders[0],
        'sql_column' => trim((string) $rawHeaders[0]),
    ];
}

function escapeSqlIdentifier(string $identifier): string
{
    return '`'.str_replace('`', '``', $identifier).'`';
}

function main(array $argv): int
{
    $args = parseArgs($argv);
    if ($args === null) {
        return 1;
    }

    try {
        $rows = readCsvRows($args['path']);
    } catch (RuntimeException $e) {
        fwrite(STDERR, 'Error: '.$e->getMessage()."\n");

        return 1;
    }

    if ($rows === []) {
        echo json_encode([
            'file' => basename($args['path']),
            'error' => 'File is empty',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";

        return 1;
    }

    $rawHeaders = $rows[0];
    $normalizedHeaders = array_map(normalizeHeader(...), $rawHeaders);

    try {
        $columnInfo = resolveValueColumn($rawHeaders, $normalizedHeaders, $args['column']);
    } catch (RuntimeException $e) {
        fwrite(STDERR, 'Error: '.$e->getMessage()."\n");

        return 1;
    }

    $columnIndex = array_search($columnInfo['column'], $normalizedHeaders, true);
    if ($columnIndex === false) {
        fwrite(STDERR, "Error: Column index not found for '{$columnInfo['column']}'.\n");

        return 1;
    }

    $barcodes = [];
    $skippedEmpty = 0;
    $skippedDuplicate = 0;
    $seen = [];

    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        $raw = (string) ($row[$columnIndex] ?? '');
        $trimmed = trim($raw);

        if ($trimmed === '') {
            $skippedEmpty++;

            continue;
        }

        if (isset($seen[$trimmed])) {
            $skippedDuplicate++;

            continue;
        }

        $seen[$trimmed] = true;
        $barcodes[] = $trimmed;
    }

    if ($barcodes === []) {
        echo json_encode([
            'file' => basename($args['path']),
            'table' => $args['table'],
            'column' => $columnInfo['sql_column'],
            'error' => 'No valid values found in CSV',
            'skipped_empty' => $skippedEmpty,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";

        return 1;
    }

    $inClause = formatInClause($barcodes);
    $tableSql = escapeSqlIdentifier($args['table']);
    $columnSql = escapeSqlIdentifier($columnInfo['sql_column']);
    $query = "SELECT * FROM {$tableSql} WHERE {$columnSql} IN {$inClause}";

    echo json_encode([
        'file' => basename($args['path']),
        'table' => $args['table'],
        'column' => $columnInfo['sql_column'],
        'total_barcodes' => count($barcodes),
        'skipped_empty' => $skippedEmpty,
        'skipped_duplicate' => $skippedDuplicate,
        'in_clause' => $inClause,
        'sql_query' => $query,
        'barcodes' => $barcodes,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";

    return 0;
}

exit(main($argv));
