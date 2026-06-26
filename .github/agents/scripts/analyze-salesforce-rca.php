#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Parses Salesforce case CSV exports, extracts Internal RCA text, and classifies each ticket.
 *
 * Usage:
 *   php analyze-salesforce-rca.php /path/to/salesforce-cases.csv
 *   php analyze-salesforce-rca.php --format=summary /path/to/file.csv
 *   php analyze-salesforce-rca.php --fetch /path/to/case-numbers.csv
 */

require_once __DIR__.'/lib/rca-analysis.php';
require_once __DIR__.'/lib/salesforce-client.php';

/** @return array{format: string, path: string, fetch: bool}|null */
function parseArgs(array $argv): ?array
{
    $format = 'json';
    $path = null;
    $fetch = false;

    for ($i = 1; $i < count($argv); $i++) {
        if ($argv[$i] === '--fetch') {
            $fetch = true;
        } elseif (str_starts_with($argv[$i], '--format=')) {
            $format = substr($argv[$i], 9);
        } elseif (! str_starts_with($argv[$i], '--')) {
            $path = $argv[$i];
        }
    }

    if ($path === null) {
        fwrite(STDERR, "Usage: php analyze-salesforce-rca.php [--fetch] [--format=json|summary] /path/to/file.csv\n");

        return null;
    }

    if (! is_readable($path)) {
        fwrite(STDERR, "Error: Cannot read file: {$path}\n");

        return null;
    }

    if (! in_array($format, ['json', 'summary'], true)) {
        fwrite(STDERR, "Error: Invalid format '{$format}'. Use json or summary.\n");

        return null;
    }

    return ['format' => $format, 'path' => $path, 'fetch' => $fetch];
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
 * @param  array<string, string>  $columnMap
 * @param  list<string>  $row
 * @param  list<string>  $rawHeaders
 * @return array<string, mixed>
 */
function analyzeRow(array $columnMap, array $row, array $rawHeaders, int $rowNumber): array
{
    $values = [];
    foreach ($rawHeaders as $index => $header) {
        $values[trim($header)] = trim((string) ($row[$index] ?? ''));
    }

    $get = static function (string $field) use ($columnMap, $values): string {
        if (! isset($columnMap[$field])) {
            return '';
        }

        return trim((string) ($values[$columnMap[$field]] ?? ''));
    };

    return rcaAnalyzeCaseData([
        'case_number' => $get('case_number'),
        'subject' => $get('subject'),
        'description' => $get('description'),
        'status' => $get('status'),
        'account_name' => $get('account_name'),
        'internal_comments' => $get('internal_comments'),
        'rca' => $get('rca'),
    ], $rowNumber);
}

/**
 * @param  list<list<string>>  $rows
 * @return list<array<string, mixed>>
 */
function analyzeRowsFromCsv(array $rows, bool $fetchFromSalesforce): array
{
    if ($rows === []) {
        throw new RuntimeException('File is empty.');
    }

    $rawHeaders = $rows[0];
    $columnMap = rcaMapColumns($rawHeaders);

    if (! isset($columnMap['case_number'])) {
        throw new RuntimeException('CSV must include a Case Number column.');
    }

    if ($fetchFromSalesforce && ! isset($columnMap['subject'])) {
        throw new RuntimeException('Fetch mode requires Case Number and Subject columns in CSV.');
    }

    $client = null;
    if ($fetchFromSalesforce) {
        $client = SalesforceClient::fromEnvironment();
    }

    $results = [];
    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        if (count(array_filter($row, static fn ($value) => trim((string) $value) !== '')) === 0) {
            continue;
        }

        if ($fetchFromSalesforce) {
            $values = [];
            foreach ($rawHeaders as $index => $header) {
                $values[trim($header)] = trim((string) ($row[$index] ?? ''));
            }

            $caseNumber = trim((string) ($values[$columnMap['case_number']] ?? ''));
            $csvSubject = trim((string) ($values[$columnMap['subject']] ?? ''));

            if ($caseNumber === '') {
                $results[] = rcaAnalyzeCaseData([
                    'case_number' => '',
                    'subject' => $csvSubject,
                    'fetch_error' => 'Row mein Case Number empty hai.',
                ], $i + 1);

                continue;
            }

            $caseData = $client->enrichCaseFromSalesforce($caseNumber, $csvSubject);
            $results[] = rcaAnalyzeCaseData($caseData, $i + 1);

            continue;
        }

        $results[] = analyzeRow($columnMap, $row, $rawHeaders, $i + 1);
    }

    return $results;
}

/**
 * @param  list<array<string, mixed>>  $results
 * @param  array<string, string>  $columnMap
 * @param  list<string>  $missingColumns
 */
function printSummary(array $results, string $filename, array $columnMap, array $missingColumns, bool $fetchFromSalesforce): void
{
    $counts = [
        'valid_issue' => 0,
        'no_issue' => 0,
        'needs_review' => 0,
        'inconclusive' => 0,
    ];

    foreach ($results as $result) {
        $verdict = (string) ($result['verdict'] ?? 'inconclusive');
        if (isset($counts[$verdict])) {
            $counts[$verdict]++;
        }
    }

    echo "## Salesforce RCA Analysis\n\n";
    echo "**File:** `{$filename}`\n";
    echo '**Mode:** '.($fetchFromSalesforce ? 'Salesforce fetch (Case Number + Subject CSV)' : 'CSV only')."\n";
    echo '**Total cases:** '.count($results)."\n\n";

    echo "### Summary\n";
    echo "- ✅ Issue hai (valid_issue): {$counts['valid_issue']}\n";
    echo "- ❌ Issue nahi hai (no_issue): {$counts['no_issue']}\n";
    echo "- ⚠️ Manual review (needs_review): {$counts['needs_review']}\n";
    echo "- ❓ RCA missing (inconclusive): {$counts['inconclusive']}\n\n";

    if ($missingColumns !== []) {
        echo "### Missing recommended columns\n";
        foreach ($missingColumns as $column) {
            echo "- {$column}\n";
        }
        echo "\n";
    }

    if ($columnMap !== []) {
        echo "### Detected columns\n";
        foreach ($columnMap as $field => $header) {
            echo "- {$field}: `{$header}`\n";
        }
        echo "\n";
    }

    echo "### Case results\n";
    echo "| Row | Case # | Status | Verdict | Confidence | Subject |\n";
    echo "|-----|--------|--------|---------|------------|----------|\n";

    foreach ($results as $result) {
        $subject = str_replace('|', '\\|', (string) ($result['subject'] ?? ''));
        if (strlen($subject) > 50) {
            $subject = mb_substr($subject, 0, 47).'...';
        }

        echo sprintf(
            "| %d | %s | %s | %s | %s | %s |\n",
            (int) $result['row'],
            (string) ($result['case_number'] ?: '-'),
            (string) ($result['status'] ?? '-'),
            (string) ($result['verdict_label'] ?? $result['verdict']),
            (string) ($result['confidence'] ?? '-'),
            $subject,
        );
    }
}

function main(array $argv): int
{
    $args = parseArgs($argv);
    if ($args === null) {
        return 1;
    }

    try {
        $rows = readCsvRows($args['path']);
        $results = analyzeRowsFromCsv($rows, $args['fetch']);
    } catch (RuntimeException $e) {
        fwrite(STDERR, 'Error: '.$e->getMessage()."\n");

        return 1;
    }

    $rawHeaders = $rows[0];
    $columnMap = rcaMapColumns($rawHeaders);

    $recommended = $args['fetch']
        ? ['case_number', 'subject']
        : ['case_number', 'subject', 'internal_comments'];

    $missingColumns = array_values(array_filter(
        $recommended,
        static function (string $field) use ($columnMap, $args): bool {
            if ($field === 'internal_comments' && isset($columnMap['rca'])) {
                return false;
            }

            if ($args['fetch'] && $field === 'internal_comments') {
                return false;
            }

            return ! isset($columnMap[$field]);
        },
    ));

    $payload = [
        'file' => basename($args['path']),
        'mode' => $args['fetch'] ? 'salesforce_fetch' : 'csv_only',
        'total_cases' => count($results),
        'detected_columns' => $columnMap,
        'missing_recommended_columns' => $missingColumns,
        'summary' => [
            'valid_issue' => count(array_filter($results, static fn (array $r): bool => $r['verdict'] === 'valid_issue')),
            'no_issue' => count(array_filter($results, static fn (array $r): bool => $r['verdict'] === 'no_issue')),
            'needs_review' => count(array_filter($results, static fn (array $r): bool => $r['verdict'] === 'needs_review')),
            'inconclusive' => count(array_filter($results, static fn (array $r): bool => $r['verdict'] === 'inconclusive')),
        ],
        'cases' => $results,
    ];

    if ($args['format'] === 'summary') {
        printSummary($results, basename($args['path']), $columnMap, $missingColumns, $args['fetch']);

        return 0;
    }

    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";

    return 0;
}

exit(main($argv));
