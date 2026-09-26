<?php

use App\Exceptions\SalesforceQueryException;
use App\Models\SfAccount;
use App\Models\SfAgent;
use App\Models\SfApplication;
use App\Models\SfGroup;
use App\Models\SfModule;
use App\Models\SfProduct;
use App\Models\SfSubModule;
use App\Models\SfType;
use App\Services\Salesforce\SalesforceAccountPuller;
use App\Services\Salesforce\SalesforceAgentPuller;
use App\Services\Salesforce\SalesforceSoqlClient;

test('artisan command pulls every master table with one describe', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('query')
            ->once()
            ->with(SalesforceAccountPuller::ACCOUNT_SOQL)
            ->andReturn([
                [
                    'Id' => '001xx000000AcmeAAA',
                    'Name' => 'Acme Retail',
                    'LastModifiedDate' => '2026-09-21T06:34:12.000+0000',
                ],
            ]);

        $mock->shouldReceive('describe')
            ->once()
            ->with('Case')
            ->andReturn(masterPicklistDescribe());

        $mock->shouldReceive('query')
            ->once()
            ->with(SalesforceAgentPuller::AGENT_SOQL)
            ->andReturn([
                [
                    'Id' => '005xx000000AgentAAA',
                    'Name' => 'Arindam Banerjee',
                    'IsActive' => true,
                ],
            ]);
    });

    $this->artisan('sf:pull-masters')
        ->expectsOutputToContain('sf_accounts')
        ->expectsOutputToContain('sf_products')
        ->expectsOutputToContain('sf_agents')
        ->expectsOutputToContain('Pulled 14 Salesforce master rows.')
        ->assertSuccessful();

    expect(SfAccount::query()->count())->toBe(1)
        ->and(SfProduct::query()->count())->toBe(2)
        ->and(SfApplication::query()->count())->toBe(2)
        ->and(SfModule::query()->count())->toBe(2)
        ->and(SfSubModule::query()->count())->toBe(2)
        ->and(SfType::query()->count())->toBe(2)
        ->and(SfGroup::query()->count())->toBe(2)
        ->and(SfAgent::query()->count())->toBe(1)
        ->and(SfAccount::query()->value('name'))->toBe('Acme Retail')
        ->and(SfAgent::query()->value('name'))->toBe('Arindam Banerjee');
});

test('artisan command dry run does not write master rows', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('query')
            ->once()
            ->with(SalesforceAccountPuller::ACCOUNT_SOQL)
            ->andReturn([
                [
                    'Id' => '001xx000000AcmeAAA',
                    'Name' => 'Acme Retail',
                    'LastModifiedDate' => '2026-09-21T06:34:12.000+0000',
                ],
            ]);
        $mock->shouldReceive('describe')->once()->andReturn(masterPicklistDescribe());
        $mock->shouldReceive('query')
            ->once()
            ->with(SalesforceAgentPuller::AGENT_SOQL)
            ->andReturn([
                [
                    'Id' => '005xx000000AgentAAA',
                    'Name' => 'Arindam Banerjee',
                    'IsActive' => true,
                ],
            ]);
    });

    $this->artisan('sf:pull-masters', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run: 14 Salesforce master rows (not saved).')
        ->assertSuccessful();

    expect(SfAccount::query()->count())->toBe(0)
        ->and(SfProduct::query()->count())->toBe(0)
        ->and(SfApplication::query()->count())->toBe(0)
        ->and(SfModule::query()->count())->toBe(0)
        ->and(SfSubModule::query()->count())->toBe(0)
        ->and(SfType::query()->count())->toBe(0)
        ->and(SfGroup::query()->count())->toBe(0)
        ->and(SfAgent::query()->count())->toBe(0);
});

test('artisan command fails when salesforce describe fails', function () {
    $this->mock(SalesforceSoqlClient::class, function ($mock) {
        $mock->shouldReceive('query')
            ->once()
            ->with(SalesforceAccountPuller::ACCOUNT_SOQL)
            ->andReturn([
                [
                    'Id' => '001xx000000AcmeAAA',
                    'Name' => 'Acme Retail',
                    'LastModifiedDate' => '2026-09-21T06:34:12.000+0000',
                ],
            ]);
        $mock->shouldReceive('describe')
            ->once()
            ->andThrow(new SalesforceQueryException('Salesforce describe failed: org not found'));
    });

    $this->artisan('sf:pull-masters')
        ->expectsOutputToContain('Salesforce describe failed: org not found')
        ->assertFailed();

    expect(SfAccount::query()->count())->toBe(1)
        ->and(SfProduct::query()->count())->toBe(0)
        ->and(SfAgent::query()->count())->toBe(0);
});

/**
 * @return array{fields: list<array{name: string, picklistValues: list<array{value: string, active: bool}>}>}
 */
function masterPicklistDescribe(): array
{
    return [
        'fields' => [
            [
                'name' => 'Product__c',
                'picklistValues' => [
                    ['value' => 'Zwing', 'active' => true],
                    ['value' => 'Ginesys', 'active' => true],
                ],
            ],
            [
                'name' => 'Application__c',
                'picklistValues' => [
                    ['value' => 'Zwing (Cloud POS)', 'active' => true],
                    ['value' => 'Ginesys (ERP)', 'active' => true],
                ],
            ],
            [
                'name' => 'Module__c',
                'picklistValues' => [
                    ['value' => 'Orders', 'active' => true],
                    ['value' => 'Wallet Service', 'active' => true],
                ],
            ],
            [
                'name' => 'Sub_module__c',
                'picklistValues' => [
                    ['value' => 'Order Processing', 'active' => true],
                    ['value' => 'Live Sync', 'active' => true],
                ],
            ],
            [
                'name' => 'Type',
                'picklistValues' => [
                    ['value' => 'Incident', 'active' => true],
                    ['value' => 'Service Request', 'active' => true],
                ],
            ],
            [
                'name' => 'Group__c',
                'picklistValues' => [
                    ['value' => 'Zwing-Tech', 'active' => true],
                    ['value' => 'ERP Helpdesk-L1', 'active' => true],
                ],
            ],
        ],
    ];
}
