<?php

use App\Services\ChatAssistant\MessageIntentAnalyzer;

it('detects redeem intent from natural language', function () {
    $analyzer = app(MessageIntentAnalyzer::class);
    $models = [
        [
            'target_column' => 'is_redeemed',
            'field_schema' => [
                ['key' => 'store_name', 'type' => 'enum', 'options' => ['Mumbai Store']],
                ['key' => 'credit_note_amt', 'type' => 'number', 'options' => [2500]],
                ['key' => 'payment_mode', 'type' => 'enum', 'options' => ['Cash']],
                ['key' => 'validity_days', 'type' => 'number', 'options' => [30]],
            ],
        ],
        [
            'target_column' => 'customer_name',
            'field_schema' => [],
        ],
    ];

    $result = $analyzer->analyze(
        'Will Mumbai store 2500 cash credit note redeem in 30 days?',
        $models,
    );

    expect($result['type'])->toBe('predict')
        ->and($result['model']['target_column'])->toBe('is_redeemed')
        ->and($result['fields']['store_name'])->toBe('Mumbai Store')
        ->and($result['fields']['credit_note_amt'])->toBe(2500);
});

it('detects customer intent from natural language', function () {
    $analyzer = app(MessageIntentAnalyzer::class);
    $models = [
        [
            'target_column' => 'is_redeemed',
            'field_schema' => [],
        ],
        [
            'target_column' => 'customer_name',
            'field_schema' => [
                ['key' => 'store_name', 'type' => 'enum', 'options' => ['Pune Store']],
                ['key' => 'credit_note_amt', 'type' => 'number', 'options' => [1500]],
                ['key' => 'payment_mode', 'type' => 'enum', 'options' => ['Cash']],
                ['key' => 'validity_days', 'type' => 'number', 'options' => [30]],
            ],
        ],
    ];

    $result = $analyzer->analyze('Who is the customer for Pune store 1500 cash 30 days?', $models);

    expect($result['type'])->toBe('predict')
        ->and($result['model']['target_column'])->toBe('customer_name');
});
