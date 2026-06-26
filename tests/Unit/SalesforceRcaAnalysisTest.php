<?php

require_once dirname(__DIR__, 2).'/.github/agents/scripts/lib/rca-analysis.php';

test('extracts structured RCA and solution from internal comment text', function () {
    $text = 'Root Cause Analysis (RCA): The store configuration was missing at the EMR end, which prevented redemption. Solution: Store configured correctly.';

    $extracted = rcaExtractFromText($text);

    expect($extracted)->not->toBeNull()
        ->and($extracted['rca'])->toContain('missing')
        ->and($extracted['solution'])->toContain('configured correctly');
});

test('classifies configuration RCA as valid issue', function () {
    $result = rcaClassify('The store configuration was missing/incorrect at the EMR end, which prevented the coupon redemption process.');

    expect($result['verdict'])->toBe('valid_issue');
});

test('classifies duplicate ticket RCA as no issue', function () {
    $result = rcaClassify('Duplicate ticket. Same issue already reported in case 00136223. No new issue found.');

    expect($result['verdict'])->toBe('no_issue');
});

test('classifies user error RCA as no issue', function () {
    $result = rcaClassify('User error - customer entered expired coupon code. Working as expected.');

    expect($result['verdict'])->toBe('no_issue');
});

test('marks fetch errors as inconclusive salesforce case missing', function () {
    $result = rcaAnalyzeCaseData([
        'case_number' => '00999999',
        'subject' => 'Missing case',
        'fetch_error' => "Case number '00999999' Salesforce mein nahi mila.",
    ], 2);

    expect($result['verdict'])->toBe('inconclusive')
        ->and($result['verdict_label'])->toBe('Salesforce se case nahi mila');
});

test('csv only mode still analyzes internal comments from csv row data', function () {
    $result = rcaAnalyzeCaseData([
        'case_number' => '00136223',
        'subject' => 'Coupon redemption failing at store',
        'status' => 'Resolved',
        'internal_comments' => 'Root Cause Analysis (RCA): API timeout between Zwing and ERP caused sync failure. Solution: Re-triggered sync.',
    ], 2);

    expect($result['verdict'])->toBe('valid_issue')
        ->and($result['status'])->toBe('Resolved');
});
