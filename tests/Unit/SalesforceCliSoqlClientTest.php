<?php

use App\Exceptions\SalesforceQueryException;
use App\Services\Salesforce\SalesforceCliSoqlClient;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

uses(TestCase::class);

it('parses successful salesforce cli json records', function () {
    Process::fake([
        '*' => Process::result(json_encode([
            'status' => 0,
            'result' => [
                'done' => true,
                'records' => [
                    ['Id' => '001xx000000AcmeAAA', 'Name' => 'Acme Retail'],
                ],
            ],
        ])),
    ]);

    $records = app(SalesforceCliSoqlClient::class)->query('SELECT Id, Name FROM Account');

    expect($records)->toHaveCount(1)
        ->and($records[0]['Name'])->toBe('Acme Retail');
});

it('fails when salesforce cli exits with an error', function () {
    Process::fake([
        '*' => Process::result(
            output: json_encode([
                'status' => 1,
                'message' => 'No authorization information found',
            ]),
            errorOutput: '',
            exitCode: 1,
        ),
    ]);

    app(SalesforceCliSoqlClient::class)->query('SELECT Id FROM Account');
})->throws(SalesforceQueryException::class, 'Salesforce query failed: No authorization information found');

it('fails when salesforce cli returns incomplete results', function () {
    Process::fake([
        '*' => Process::result(json_encode([
            'status' => 0,
            'result' => [
                'done' => false,
                'records' => [],
            ],
        ])),
    ]);

    app(SalesforceCliSoqlClient::class)->query('SELECT Id FROM Account');
})->throws(SalesforceQueryException::class, 'Salesforce query result is incomplete');
