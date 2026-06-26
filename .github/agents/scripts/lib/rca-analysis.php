<?php

declare(strict_types=1);

const RCA_COLUMN_ALIASES = [
    'case_number' => ['case number', 'case_number', 'casenumber', 'case #', 'case no', 'case no.'],
    'subject' => ['subject', 'case subject'],
    'description' => ['description', 'case description'],
    'status' => ['status', 'case status'],
    'account_name' => ['account name', 'account_name', 'account'],
    'internal_comments' => [
        'internal comments',
        'internal_comments',
        'internal notes',
        'internal_notes',
        'internal rca',
        'internal_rca',
        'activity feed',
        'case comments',
        'comments',
    ],
    'rca' => ['rca', 'root cause analysis', 'root cause', 'root_cause'],
];

const RCA_VALID_ISSUE_PATTERNS = [
    '/\b(missing|incorrect|misconfigured|not configured|configuration issue|config issue)\b/i',
    '/\b(bug|defect|failed|failure|broken|malfunction)\b/i',
    '/(?<!user )(?<!customer )(?<!client )\berror\b/i',
    '/\b(sync|mismatch|inconsisten|data issue|wrong data|incorrect data)\b/i',
    '/\b(timeout|down|unavailable|outage|not responding)\b/i',
    '/\b(permission|access denied|authorization|unauthorized)\b/i',
    '/\b(server|backend|api|integration|emr|zwing)\b.*\b(issue|problem|error|fail)/i',
    '/\b(prevented|blocked|unable to|could not|cannot|did not work)\b/i',
    '/\b(root cause|caused by|due to)\b/i',
];

const RCA_NO_ISSUE_PATTERNS = [
    '/\b(user error|user mistake|user unaware|customer error|client error)\b/i',
    '/\b(working as designed|working as expected|expected behavior|by design)\b/i',
    '/\b(not a bug|no bug|no issue found|no issue identified|no valid issue)\b/i',
    '/\b(duplicate ticket|duplicate case|already reported)\b/i',
    '/\b(training issue|need training|user training|educate the user)\b/i',
    '/\b(false alarm|false positive|non.?issue)\b/i',
    '/\b(incorrect usage|wrong usage|did not follow|misuse)\b/i',
    '/\b(cosmetic|enhancement request|feature request)\b/i',
    '/\b(no action required|no action needed|nothing to fix)\b/i',
];

function rcaNormalizeHeader(string $header): string
{
    $clean = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $header) ?? $header;

    return strtolower(trim($clean));
}

/**
 * @param  list<string>  $rawHeaders
 * @return array<string, string>
 */
function rcaMapColumns(array $rawHeaders): array
{
    $normalizedHeaders = array_map(rcaNormalizeHeader(...), $rawHeaders);
    $mapping = [];

    foreach (RCA_COLUMN_ALIASES as $field => $aliases) {
        foreach ($aliases as $alias) {
            $index = array_search($alias, $normalizedHeaders, true);
            if ($index !== false) {
                $mapping[$field] = trim((string) $rawHeaders[$index]);

                break;
            }
        }
    }

    return $mapping;
}

/**
 * @return array{rca: string, solution: ?string, source: string}|null
 */
function rcaExtractFromText(string $text): ?array
{
    $text = trim(preg_replace("/\r\n|\r/", "\n", $text) ?? $text);
    if ($text === '') {
        return null;
    }

    if (preg_match(
        '/(?:Root\s+Cause\s+Analysis\s*\(?RCA\)?|RCA)\s*:?\s*(.+)/is',
        $text,
        $matches,
    )) {
        $body = trim($matches[1]);
        $rca = $body;
        $solution = null;

        if (preg_match('/(.+?)\s*(?:Solution|Resolution|Fix|Action\s+Taken)\s*:\s*(.+)/is', $body, $splitMatch)) {
            $rca = trim($splitMatch[1]);
            $solution = trim($splitMatch[2]);
        }

        return [
            'rca' => $rca,
            'solution' => $solution,
            'source' => 'structured_block',
        ];
    }

    if (preg_match('/^RCA\s*:?\s*(.+)$/is', $text, $matches)) {
        return [
            'rca' => trim($matches[1]),
            'solution' => null,
            'source' => 'rca_prefix',
        ];
    }

    if (strlen($text) <= 5000) {
        return [
            'rca' => $text,
            'solution' => null,
            'source' => 'full_text',
        ];
    }

    return null;
}

/**
 * @return list<string>
 */
function rcaMatchedPatterns(string $text, array $patterns): array
{
    $matches = [];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text)) {
            $matches[] = $pattern;
        }
    }

    return $matches;
}

/**
 * @return array{
 *     verdict: string,
 *     verdict_label: string,
 *     confidence: string,
 *     valid_issue_signals: list<string>,
 *     no_issue_signals: list<string>,
 *     reasoning: string
 * }
 */
function rcaClassify(string $rca): array
{
    $validSignals = rcaMatchedPatterns($rca, RCA_VALID_ISSUE_PATTERNS);
    $noIssueSignals = rcaMatchedPatterns($rca, RCA_NO_ISSUE_PATTERNS);

    $validCount = count($validSignals);
    $noIssueCount = count($noIssueSignals);

    if ($validCount > 0 && $noIssueCount === 0) {
        return [
            'verdict' => 'valid_issue',
            'verdict_label' => 'Issue hai — valid technical/system problem',
            'confidence' => $validCount >= 2 ? 'high' : 'medium',
            'valid_issue_signals' => $validSignals,
            'no_issue_signals' => [],
            'reasoning' => 'RCA describes a concrete technical, configuration, or system-side failure.',
        ];
    }

    if ($noIssueCount > 0 && $validCount === 0) {
        return [
            'verdict' => 'no_issue',
            'verdict_label' => 'Issue nahi hai — user-side / expected behavior / duplicate',
            'confidence' => $noIssueCount >= 2 ? 'high' : 'medium',
            'valid_issue_signals' => [],
            'no_issue_signals' => $noIssueSignals,
            'reasoning' => 'RCA indicates no valid product/system defect (user error, duplicate, or expected behavior).',
        ];
    }

    if ($validCount > 0 && $noIssueCount > 0) {
        return [
            'verdict' => 'needs_review',
            'verdict_label' => 'Manual review chahiye — mixed signals',
            'confidence' => 'low',
            'valid_issue_signals' => $validSignals,
            'no_issue_signals' => $noIssueSignals,
            'reasoning' => 'RCA contains both issue and no-issue indicators; human review recommended.',
        ];
    }

    return [
        'verdict' => 'needs_review',
        'verdict_label' => 'Manual review chahiye — RCA unclear',
        'confidence' => 'low',
        'valid_issue_signals' => [],
        'no_issue_signals' => [],
        'reasoning' => 'RCA text found but no strong keyword signals; review subject/description for context.',
    ];
}

/**
 * @param  array{
 *     case_number?: string,
 *     subject?: string,
 *     description?: string,
 *     status?: string,
 *     account_name?: string,
 *     internal_comments?: string,
 *     rca?: string,
 *     fetch_error?: string|null,
 *     subject_mismatch?: bool
 * }  $caseData
 * @return array<string, mixed>
 */
function rcaAnalyzeCaseData(array $caseData, int $rowNumber): array
{
    if (($caseData['fetch_error'] ?? null) !== null) {
        return [
            'row' => $rowNumber,
            'case_number' => $caseData['case_number'] ?? '',
            'subject' => $caseData['subject'] ?? '',
            'status' => null,
            'account_name' => null,
            'description_excerpt' => null,
            'verdict' => 'inconclusive',
            'verdict_label' => 'Salesforce se case nahi mila',
            'confidence' => 'high',
            'rca' => null,
            'solution' => null,
            'rca_source' => null,
            'internal_comments' => null,
            'fetch_error' => $caseData['fetch_error'],
            'reasoning' => (string) $caseData['fetch_error'],
            'valid_issue_signals' => [],
            'no_issue_signals' => [],
        ];
    }

    $caseNumber = trim((string) ($caseData['case_number'] ?? ''));
    $subject = trim((string) ($caseData['subject'] ?? ''));
    $description = trim((string) ($caseData['description'] ?? ''));
    $status = trim((string) ($caseData['status'] ?? ''));
    $accountName = trim((string) ($caseData['account_name'] ?? ''));
    $dedicatedRca = trim((string) ($caseData['rca'] ?? ''));
    $internalComments = trim((string) ($caseData['internal_comments'] ?? ''));

    $rcaSourceText = $dedicatedRca !== '' ? $dedicatedRca : $internalComments;
    $extracted = rcaExtractFromText($rcaSourceText);

    if ($extracted === null && $internalComments !== '' && $dedicatedRca !== '') {
        $extracted = rcaExtractFromText($internalComments);
    }

    if ($extracted === null) {
        return [
            'row' => $rowNumber,
            'case_number' => $caseNumber,
            'subject' => $subject,
            'status' => $status,
            'account_name' => $accountName,
            'description_excerpt' => mb_substr($description, 0, 200),
            'verdict' => 'inconclusive',
            'verdict_label' => 'RCA nahi mila — inconclusive',
            'confidence' => 'high',
            'rca' => null,
            'solution' => null,
            'rca_source' => null,
            'internal_comments' => $internalComments !== '' ? $internalComments : null,
            'fetch_error' => null,
            'subject_mismatch' => (bool) ($caseData['subject_mismatch'] ?? false),
            'reasoning' => 'No Internal RCA or internal comments found in Salesforce or CSV.',
            'valid_issue_signals' => [],
            'no_issue_signals' => [],
        ];
    }

    $classification = rcaClassify($extracted['rca']);

    return [
        'row' => $rowNumber,
        'case_number' => $caseNumber,
        'subject' => $subject,
        'status' => $status,
        'account_name' => $accountName,
        'description_excerpt' => mb_substr($description, 0, 200),
        'rca' => $extracted['rca'],
        'solution' => $extracted['solution'],
        'rca_source' => $extracted['source'],
        'internal_comments' => $internalComments !== '' ? $internalComments : null,
        'fetch_error' => null,
        'subject_mismatch' => (bool) ($caseData['subject_mismatch'] ?? false),
        ...$classification,
    ];
}
