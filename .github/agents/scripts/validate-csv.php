#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Validates reconciliation CSV files and outputs rows in SQL tuple format.
 *
 * Usage:
 *   php validate-csv.php --type=stock /path/to/file.csv
 *   php validate-csv.php --type=log /path/to/file.csv
 *   php validate-csv.php --type=invoice /path/to/file.csv
 */

const CSV_TYPES = [
    'stock' => ['batch_no', 'barcode', 'icode', 'site_code', 'sprefcode', 'stock_point_name', 'qty'],
    'log' => ['site_code', 'icode', 'batch_no', 'sprefcode', 'doc_no', 'enttype', 'qty'],
    'invoice' => ['invoice_id', 'total_amount', 'status'],
];

/** @return array{type: string, path: string}|null */
function parseArgs(array $argv): ?array
{
    $type = null;
    $path = null;

    for ($i = 1; $i < count($argv); $i++) {
        if (str_starts_with($argv[$i], '--type=')) {
            $type = substr($argv[$i], 7);
        } elseif (! str_starts_with($argv[$i], '--')) {
            $path = $argv[$i];
        }
    }

    if ($type === null || $path === null || ! isset(CSV_TYPES[$type])) {
        fwrite(STDERR, "Usage: php validate-csv.php --type=stock|log|invoice /path/to/file.csv\n");

        return null;
    }

    if (! is_readable($path)) {
        fwrite(STDERR, "Error: Cannot read file: {$path}\n");

        return null;
    }

    return ['type' => $type, 'path' => $path];
}

function normalizeHeader(string $header): string
{
    $clean = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $header) ?? $header;

    return strtolower(trim($clean));
}

/** @param list<string> $values */
function formatTuple(array $values): string
{
    return implode(',', array_map(
        static fn (string $v): string => "'".str_replace("'", "''", $v)."'",
        $values,
    ));
}

/**
 * @param  array<string, string>  $record
 * @return list<string>
 */
function rowValuesInHeaderOrder(array $headers, array $record): array
{
    $values = [];
    foreach ($headers as $header) {
        $values[] = trim((string) ($record[$header] ?? ''));
    }

    return $values;
}

/**
 * @param  array<string, string>  $record
 * @return list<string>
 */
function validateRow(string $type, array $record): array
{
    $errors = [];

    if ($type === 'stock') {
        if (trim((string) ($record['icode'] ?? '')) === '') {
            $errors[] = 'icode is empty';
        }
        foreach (['site_code', 'stock_point_name'] as $col) {
            if (trim((string) ($record[$col] ?? '')) === '') {
                $errors[] = "{$col} is empty";
            }
        }
        if (! isset($record['qty']) || ! is_numeric(trim((string) $record['qty']))) {
            $errors[] = 'qty is not numeric';
        }
    } elseif ($type === 'log') {
        if (trim((string) ($record['icode'] ?? '')) === '') {
            $errors[] = 'icode is empty';
        }
        if (! isset($record['qty']) || ! is_numeric(trim((string) $record['qty']))) {
            $errors[] = 'qty is not numeric';
        }
    } elseif ($type === 'invoice') {
        foreach (['invoice_id', 'total_amount', 'status'] as $col) {
            if (trim((string) ($record[$col] ?? '')) === '') {
                $errors[] = "{$col} is empty";
            }
        }
        if (isset($record['total_amount']) && ! is_numeric(trim((string) $record['total_amount']))) {
            $errors[] = 'total_amount is not numeric';
        }
    }

    return $errors;
}

/**
 * @param  array<string, string>  $record
 * @return list<array{column: string, issue: string, raw: string}>
 */
function detectBlankIssues(array $headers, array $record): array
{
    $issues = [];

    foreach ($headers as $column) {
        $raw = (string) ($record[$column] ?? '');

        if ($raw === '') {
            $issues[] = ['column' => $column, 'issue' => 'empty', 'raw' => ''];

            continue;
        }

        if (trim($raw) === '') {
            $issues[] = ['column' => $column, 'issue' => 'whitespace-only', 'raw' => $raw];

            continue;
        }

        if ($raw !== trim($raw)) {
            $issues[] = [
                'column' => $column,
                'issue' => 'leading-trailing whitespace',
                'raw' => $raw,
            ];
        }
    }

    return $issues;
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

function main(array $argv): int
{
    $args = parseArgs($argv);
    if ($args === null) {
        return 1;
    }

    $type = $args['type'];
    $path = $args['path'];
    $requiredColumns = CSV_TYPES[$type];

    try {
        $rows = readCsvRows($path);
    } catch (RuntimeException $e) {
        fwrite(STDERR, 'Error: '.$e->getMessage()."\n");

        return 1;
    }

    if ($rows === []) {
        echo json_encode([
            'file' => basename($path),
            'type' => $type,
            'error' => 'File is empty',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";

        return 1;
    }

    $rawHeaders = $rows[0];
    $normalizedHeaders = array_map(normalizeHeader(...), $rawHeaders);
    $missingColumns = array_values(array_diff($requiredColumns, $normalizedHeaders));

    $blankIssues = [];
    $invalidRows = [];
    $formattedRows = [];
    $validCount = 0;
    $blankRowCount = 0;

    for ($i = 1, $rowNum = 2; $i < count($rows); $i++, $rowNum++) {
        $row = $rows[$i];

        $isBlankRow = count(array_filter($row, static fn ($v) => trim((string) $v) !== '')) === 0;
        if ($isBlankRow) {
            $blankRowCount++;
            $blankIssues[] = [
                'row' => $rowNum,
                'column' => '(entire row)',
                'issue' => 'blank row',
                'raw' => '',
            ];

            continue;
        }

        /** @var array<string, string> $record */
        $record = [];
        foreach ($normalizedHeaders as $idx => $header) {
            $record[$header] = (string) ($row[$idx] ?? '');
        }

        foreach (detectBlankIssues($normalizedHeaders, $record) as $issue) {
            $blankIssues[] = array_merge(['row' => $rowNum], $issue);
        }

        $rowErrors = validateRow($type, $record);
        if ($rowErrors !== []) {
            $invalidRows[] = ['row' => $rowNum, 'reasons' => $rowErrors];

            continue;
        }

        $validCount++;
        $formattedRows[] = formatTuple(rowValuesInHeaderOrder($normalizedHeaders, $record));
    }

    $totalDataRows = count($rows) - 1;

    echo json_encode([
        'file' => basename($path),
        'type' => $type,
        'total_rows' => $totalDataRows,
        'valid_rows' => $validCount,
        'invalid_rows' => count($invalidRows),
        'blank_row_count' => $blankRowCount,
        'blank_space_issues' => count($blankIssues),
        'missing_columns' => $missingColumns,
        'blank_issues' => $blankIssues,
        'invalid_row_details' => $invalidRows,
        'formatted_output' => $formattedRows,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";

    return ($missingColumns === [] && count($invalidRows) === 0) ? 0 : 1;
}

exit(main($argv));
